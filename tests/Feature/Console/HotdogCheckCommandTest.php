<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

const CHECK_BASE = 'https://api.typesafe.test/v1';

beforeEach(function () {
    Sleep::fake();
    config([
        'jev.fake' => true,
        'jev.api_key' => 'test-key',
        'jev.model' => 'jev-preview',
        'jev.base_url' => CHECK_BASE,
        'hotdog.image_driver' => 'gd',
    ]);
});

function jevAnswers(array $models = ['jev-latest', 'jev-preview'], int $imageStatus = 200): void
{
    Http::fake([
        CHECK_BASE.'/models' => Http::response(['models' => array_map(fn ($name) => ['name' => $name, 'description' => '', 'release_date' => '2026-10-14'], $models)]),
        CHECK_BASE.'/systemone' => Http::response(
            $imageStatus === 200
                ? ['model' => 'jev-1.14.0', 'answers' => ['is_hotdog' => ['type' => 'noul', 'noul' => 0.01], 'hotdog' => ['type' => 'box', 'box' => ['top' => 0, 'bottom' => 1, 'left' => 0, 'right' => 1], 'confidence' => 0.2]], 'usage' => ['input_tokens' => 90, 'output_tokens' => 9]]
                : [],
            $imageStatus,
            ['x-typesafe-request-id' => 'req_check'],
        ),
    ]);
}

it('checks the key, then sends one tiny image request with the preview header', function () {
    jevAnswers();

    $this->artisan('hotdog:check')
        ->expectsOutputToContain('Key accepted')
        ->expectsOutputToContain('jev-latest, jev-preview')
        ->expectsOutputToContain('answered by jev-1.14.0')
        ->expectsOutputToContain('Set JEV_FAKE=false in .env to use it in the app.')
        ->assertSuccessful();

    Http::assertSentCount(2);
    Http::assertSent(function (Request $request) {
        if (! str_ends_with($request->url(), '/systemone')) {
            return false;
        }

        $reference = $request['state']['photo'];
        $size = getimagesizefromstring(base64_decode($request['assets'][$reference]['data_base64']));

        return $request->hasHeader('TypeSafe-Danger-Zone-Images', 'true')
            && $request['model'] === 'jev-preview'
            && $request['assets'][$reference]['format'] === 'png'
            && [$size[0], $size[1]] === [280, 280]
            && array_keys($request['questions']) === ['is_hotdog', 'hotdog'];
    });
});

it('says the app is already using jev when fake mode is off', function () {
    config(['jev.fake' => false]);
    jevAnswers();

    $this->artisan('hotdog:check')
        ->expectsOutputToContain('Jev is ready, and the app is using it.')
        ->assertSuccessful();
});

it('shows the settings without ever printing the key', function () {
    jevAnswers();

    $this->artisan('hotdog:check')
        ->expectsOutputToContain('jev-preview')
        ->expectsOutputToContain(CHECK_BASE)
        ->doesntExpectOutputToContain('test-key')
        ->assertSuccessful();
});

it('needs no request in fake mode without a key', function () {
    config(['jev.api_key' => '']);
    Http::fake();

    $this->artisan('hotdog:check')
        ->expectsOutputToContain('Fake mode works without one.')
        ->assertSuccessful();

    Http::assertNothingSent();
});

it('fails without a key when fake mode is off', function () {
    config(['jev.fake' => false, 'jev.api_key' => null]);
    Http::fake();

    $this->artisan('hotdog:check')
        ->expectsOutputToContain('No Jev API key is set.')
        ->assertFailed();

    Http::assertNothingSent();
});

it('stops at a rejected key without sending an image', function () {
    Http::fake([CHECK_BASE.'/models' => Http::response([], 401, ['x-typesafe-request-id' => 'req_401'])]);

    $this->artisan('hotdog:check')
        ->expectsOutputToContain('FAILED')
        ->expectsOutputToContain('Jev did not accept the API key.')
        ->expectsOutputToContain('Request id: req_401')
        ->assertFailed();

    Http::assertSentCount(1);
});

it('explains a rejected image request as a model or header problem', function () {
    jevAnswers(imageStatus: 400);

    $this->artisan('hotdog:check')
        ->expectsOutputToContain('Image request')
        ->expectsOutputToContain('check JEV_MODEL is jev-preview or jev-1.14.0')
        ->expectsOutputToContain('Request id: req_check')
        ->assertFailed();
});

it('warns about a text-only model before trying it', function () {
    config(['jev.model' => 'jev-latest']);
    jevAnswers(imageStatus: 400);

    $this->artisan('hotdog:check')
        ->expectsOutputToContain('jev-latest may not support images or Box questions.')
        ->assertFailed();
});

it('warns when an alias is not offered to this key, but accepts versioned ids', function (string $model, bool $warns) {
    config(['jev.model' => $model]);
    jevAnswers(models: ['jev-latest']);

    $command = $this->artisan('hotdog:check');

    $warns
        ? $command->expectsOutputToContain("{$model} is not in the list for this key.")
        : $command->doesntExpectOutputToContain('is not in the list');

    $command->assertSuccessful();
})->with([
    'alias missing' => ['jev-preview', true],
    'versioned id' => ['jev-1.14.0', false],
]);

it('copes with an empty model list', function () {
    jevAnswers(models: []);

    $this->artisan('hotdog:check')
        ->expectsOutputToContain('none listed')
        ->assertSuccessful();
});

it('reports a timeout clearly', function () {
    Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

    $this->artisan('hotdog:check')
        ->expectsOutputToContain('Jev took too long to answer.')
        ->assertFailed();
});
