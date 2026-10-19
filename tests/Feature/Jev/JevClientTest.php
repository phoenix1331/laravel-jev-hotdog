<?php

declare(strict_types=1);

use App\Jev\JevClient;
use App\Jev\JevError;
use App\Jev\JevException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

const JEV_BASE = 'https://api.typesafe.test/v1';

/** Response body from the Box page of docs.typesafe.ai. */
function documentedBoxResponse(): array
{
    return [
        'model' => 'jev-1.14.0',
        'answers' => [
            'mug' => [
                'type' => 'box',
                'box' => ['top' => 0.25, 'bottom' => 0.82, 'left' => 0.44, 'right' => 0.84],
                'confidence' => 0.92,
            ],
        ],
        'usage' => ['input_tokens' => 290, 'output_tokens' => 21],
    ];
}

function jevClient(?string $key = 'test-key', string $base = JEV_BASE): JevClient
{
    return new JevClient($key, $base, 5);
}

function catchJev(callable $call): JevException
{
    try {
        $call();
    } catch (JevException $e) {
        return $e;
    }

    throw new RuntimeException('Expected a JevException.');
}

beforeEach(fn () => Sleep::fake());

it('posts to systemone with the key, images header and json body', function () {
    Http::fake([JEV_BASE.'/systemone' => Http::response(documentedBoxResponse(), 200, ['x-typesafe-request-id' => 'req_123'])]);

    $response = jevClient()->systemOne(['model' => 'jev-preview', 'state' => 'x', 'questions' => []]);

    Http::assertSent(fn (Request $request) => $request->url() === JEV_BASE.'/systemone'
        && $request->method() === 'POST'
        && $request->hasHeader('Authorization', 'Bearer test-key')
        && $request->hasHeader('TypeSafe-Danger-Zone-Images', 'true')
        && $request->hasHeader('Content-Type', 'application/json')
        && $request['model'] === 'jev-preview');

    expect($response->model)->toBe('jev-1.14.0')
        ->and($response->answer('mug')['confidence'])->toBe(0.92)
        ->and($response->requestId)->toBe('req_123')
        ->and($response->latencyMs)->toBeGreaterThanOrEqual(0);
});

it('copes with a trailing slash on the base url', function () {
    Http::fake([JEV_BASE.'/systemone' => Http::response(documentedBoxResponse())]);

    expect(jevClient(base: JEV_BASE.'/')->systemOne([])->requestId)->toBeNull();
});

it('refuses to call jev without a key', function (?string $key) {
    Http::fake();

    expect(catchJev(fn () => jevClient($key)->systemOne([]))->error)->toBe(JevError::MissingKey);

    Http::assertNothingSent();
})->with(['null' => [null], 'empty' => [''], 'blank' => ['   ']]);

it('maps error statuses to friendly errors', function (int $status, JevError $error, array $body) {
    Http::fake([JEV_BASE.'/*' => Http::response($body, $status, ['x-typesafe-request-id' => 'req_err'])]);

    $e = catchJev(fn () => jevClient()->systemOne([]));

    expect($e->error)->toBe($error)
        ->and($e->status)->toBe($status)
        ->and($e->requestId)->toBe('req_err')
        ->and($e->getMessage())->toBe($error->message())
        ->and($e->getMessage())->not->toContain('test-key');
})->with([
    'missing images header or rejected image' => [400, JevError::ImageRejected, ['error' => 'image rejected']],
    'bad key' => [401, JevError::Unauthorised, []],
    'forbidden' => [403, JevError::Unauthorised, []],
    'too large' => [413, JevError::TooLarge, []],
    'validation (documented 422 body)' => [422, JevError::InvalidRequest, ['detail' => [['loc' => ['body', 'state'], 'msg' => 'Field required', 'type' => 'missing']]]],
    'server error' => [500, JevError::UnexpectedResponse, []],
]);

it('retries rate limited and overloaded responses, then succeeds', function () {
    Http::fake([JEV_BASE.'/systemone' => Http::sequence()
        ->push([], 429)
        ->push([], 529)
        ->push(documentedBoxResponse())]);

    expect(jevClient()->systemOne([])->model)->toBe('jev-1.14.0');

    Http::assertSentCount(3);
    Sleep::assertSleptTimes(2);
});

it('gives up as busy after the retries run out', function () {
    Http::fake([JEV_BASE.'/systemone' => Http::response([], 429)]);

    expect(catchJev(fn () => jevClient()->systemOne([]))->error)->toBe(JevError::Busy);

    Http::assertSentCount(3);
});

it('does not retry errors that will not go away', function () {
    Http::fake([JEV_BASE.'/systemone' => Http::response([], 401)]);

    catchJev(fn () => jevClient()->systemOne([]));

    Http::assertSentCount(1);
});

it('maps connection failures to timeout or unreachable', function (string $message, JevError $error) {
    Http::fake(fn () => throw new ConnectionException($message));

    $e = catchJev(fn () => jevClient()->systemOne([]));

    expect($e->error)->toBe($error)
        ->and($e->getPrevious())->toBeInstanceOf(ConnectionException::class);
})->with([
    'curl timeout' => ['cURL error 28: Operation timed out after 5001 milliseconds', JevError::Timeout],
    'dns failure' => ['cURL error 6: Could not resolve host: api.typesafe.test', JevError::Unreachable],
]);

it('rejects a success response without model and answers', function (mixed $body) {
    Http::fake([JEV_BASE.'/systemone' => Http::response($body)]);

    expect(catchJev(fn () => jevClient()->systemOne([]))->error)->toBe(JevError::UnexpectedResponse);
})->with([
    'not json' => ['<html>gateway</html>'],
    'no answers' => [['model' => 'jev-1.14.0']],
    'no model' => [['answers' => []]],
]);

it('rejects a missing answer by name', function () {
    Http::fake([JEV_BASE.'/systemone' => Http::response(documentedBoxResponse())]);

    expect(catchJev(fn () => jevClient()->systemOne([])->answer('cup'))->error)->toBe(JevError::UnexpectedResponse);
});

it('lists model names', function () {
    Http::fake([JEV_BASE.'/models' => Http::response(['models' => [
        ['name' => 'jev-latest', 'description' => 'General-purpose system one model.', 'release_date' => '2026-09-15'],
        ['name' => 'jev-preview', 'description' => 'Preview.', 'release_date' => '2026-10-14'],
        ['description' => 'nameless'],
        'junk',
    ]])]);

    expect(jevClient()->models())->toBe(['jev-latest', 'jev-preview']);

    Http::assertSent(fn (Request $request) => $request->method() === 'GET'
        && $request->hasHeader('TypeSafe-Danger-Zone-Images', 'true'));
});

it('rejects a malformed model list', function () {
    Http::fake([JEV_BASE.'/models' => Http::response(['data' => []])]);

    expect(catchJev(fn () => jevClient()->models())->error)->toBe(JevError::UnexpectedResponse);
});
