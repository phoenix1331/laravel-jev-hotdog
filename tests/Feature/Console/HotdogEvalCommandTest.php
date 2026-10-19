<?php

declare(strict_types=1);

use App\Hotdog\BoundingBox;
use App\Hotdog\ConfidenceBand;
use App\Hotdog\HotdogResult;
use App\Hotdog\ImageRejected;
use App\Hotdog\NormalisedImage;
use App\Jev\JevError;
use App\Jev\JevException;
use Tests\Support\ImageFixtures;
use Tests\Support\StubDetector;
use Tests\Support\TemporarySamples;

beforeEach(function () {
    config(['jev.fake' => true, 'hotdog.image_driver' => 'gd', 'hotdog.samples_path' => TemporarySamples::make(json_encode([
        ['file' => 'classic.jpg', 'expected' => 'hotdog'],
        ['file' => 'banana.jpg', 'expected' => 'not_hotdog'],
        ['file' => 'side-on.jpg', 'expected' => 'hotdog', 'tricky' => true],
        ['file' => 'corn-dog.jpg', 'expected' => 'not_hotdog', 'tricky' => true],
        ['file' => 'wanted.jpg', 'expected' => 'hotdog'],
    ]), [
        'classic.jpg' => ImageFixtures::jpeg(400, 300),
        'banana.jpg' => ImageFixtures::jpeg(401, 300),
        'side-on.jpg' => ImageFixtures::jpeg(402, 300),
        'corn-dog.jpg' => ImageFixtures::jpeg(403, 300),
    ])]);
});

/**
 * A stub that answers per filename: confidence, or an exception to throw.
 *
 * @param  array<string, float|Throwable>  $answers
 */
function answersByFile(array $answers): StubDetector
{
    return StubDetector::bind(function (NormalisedImage $image) use ($answers) {
        $answer = $answers[$image->originalName] ?? 0.5;

        if ($answer instanceof Throwable) {
            throw $answer;
        }

        return HotdogResult::decide(new ConfidenceBand(0.4, 0.6), $answer, new BoundingBox(0.1, 0.9, 0.1, 0.9), 50, 'jev-1.14.0');
    });
}

it('reports overall and per-group accuracy, counting not sure as a fail', function () {
    answersByFile(['classic.jpg' => 0.95, 'banana.jpg' => 0.7, 'side-on.jpg' => 0.5, 'corn-dog.jpg' => 0.1]);

    $this->artisan('hotdog:eval')
        ->expectsOutputToContain('2 / 4 (50.0%)')
        ->expectsOutputToContain('1 / 2 (50.0%)')
        ->expectsOutputToContain('Not sure answers')
        ->expectsOutputToContain('jev-1.14.0')
        ->expectsOutputToContain('1 (see samples/CREDITS.md)')
        ->assertSuccessful();
});

it('passes every sample in fake mode, because the fake reads expected.json', function () {
    $this->artisan('hotdog:eval')
        ->expectsOutputToContain('4 / 4 (100.0%)')
        ->expectsOutputToContain('proves the plumbing, not the model')
        ->assertSuccessful();
});

it('runs only one group with --only', function (string $only, array $files) {
    $detector = answersByFile([]);

    $this->artisan('hotdog:eval', ['--only' => $only])->assertSuccessful();

    expect(array_map(fn ($image) => $image->originalName, $detector->seen))->toBe($files);
})->with([
    'tricky' => ['tricky', ['side-on.jpg', 'corn-dog.jpg']],
    'easy' => ['easy', ['classic.jpg', 'banana.jpg']],
]);

it('rejects bad options before running anything', function (array $options, string $message) {
    $detector = answersByFile([]);

    $this->artisan('hotdog:eval', $options)
        ->expectsOutputToContain($message)
        ->assertExitCode(2);

    expect($detector->seen)->toBeEmpty();
})->with([
    'unknown group' => [['--only' => 'hard'], '--only must be easy or tricky.'],
    'min not a number' => [['--min' => 'lots'], '--min must be a percentage from 0 to 100.'],
    'min over 100' => [['--min' => '150'], '--min must be a percentage from 0 to 100.'],
]);

it('fails when accuracy is below --min', function () {
    answersByFile(['classic.jpg' => 0.95, 'banana.jpg' => 0.95, 'side-on.jpg' => 0.95, 'corn-dog.jpg' => 0.95]);

    $this->artisan('hotdog:eval', ['--min' => '80'])
        ->expectsOutputToContain('Accuracy 50.0% is below the --min of 80%.')
        ->assertFailed();
});

it('passes at or above --min', function () {
    $this->artisan('hotdog:eval', ['--min' => '100'])->assertSuccessful();
});

it('warns about api credit in jev mode and stops if declined', function () {
    config(['jev.fake' => false]);
    $detector = answersByFile([]);

    $this->artisan('hotdog:eval')
        ->expectsOutputToContain('this sends 4 images to TypeSafe and uses API credit')
        ->expectsConfirmation('Run the eval?', 'no')
        ->expectsOutputToContain('Cancelled. Nothing was sent.')
        ->assertSuccessful();

    expect($detector->seen)->toBeEmpty();
});

it('runs in jev mode once confirmed, or straight away with --force', function (array $options, bool $asks) {
    config(['jev.fake' => false]);
    $detector = answersByFile([]);

    $command = $this->artisan('hotdog:eval', $options);

    if ($asks) {
        $command->expectsConfirmation('Run the eval?', 'yes');
    }

    $command->doesntExpectOutputToContain('proves the plumbing')->assertSuccessful()->run();

    expect($detector->seen)->toHaveCount(4);
})->with([
    'confirmed' => [[], true],
    'forced' => [['--force' => true], false],
]);

it('keeps going when one sample errors, counting it as a fail', function (Throwable $error, string $shown) {
    answersByFile(['classic.jpg' => 0.95, 'banana.jpg' => $error, 'side-on.jpg' => 0.95, 'corn-dog.jpg' => 0.1]);

    $this->artisan('hotdog:eval')
        ->expectsOutputToContain($shown)
        ->expectsOutputToContain('3 / 4 (75.0%)')
        ->assertSuccessful();
})->with([
    'jev busy' => [new JevException(JevError::Busy), 'FAIL busy'],
    'image rejected' => [ImageRejected::unreadable(), 'FAIL image rejected'],
]);

it('stops at the first sample when the key is missing or rejected', function (JevError $error) {
    $detector = answersByFile(['classic.jpg' => new JevException($error)]);

    $this->artisan('hotdog:eval', ['--force' => true])
        ->expectsOutputToContain($error->message())
        ->assertFailed();

    expect($detector->seen)->toHaveCount(1);
})->with([JevError::MissingKey, JevError::Unauthorised]);

it('has nothing to do without samples', function () {
    config(['hotdog.samples_path' => TemporarySamples::make()]);

    $this->artisan('hotdog:eval')
        ->expectsOutputToContain('No samples to run.')
        ->assertSuccessful();
});
