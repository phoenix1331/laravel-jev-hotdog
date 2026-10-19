<?php

declare(strict_types=1);

use App\Hotdog\ConfidenceBand;
use App\Hotdog\Detectors\FakeHotdogDetector;
use App\Hotdog\NormalisedImage;
use App\Hotdog\Samples\SampleCatalogue;
use App\Hotdog\Verdict;
use Tests\Support\TemporarySamples;

function fakeImage(string $bytes, ?string $name = null): NormalisedImage
{
    return new NormalisedImage($bytes, 'image/jpeg', 800, 600, 800, 600, $name);
}

function fakeDetector(?string $expectedJson = null): FakeHotdogDetector
{
    return new FakeHotdogDetector(
        new ConfidenceBand(0.4, 0.6),
        new SampleCatalogue(TemporarySamples::make($expectedJson)),
    );
}

$expected = json_encode([
    ['file' => 'classic.jpg', 'expected' => 'hotdog'],
    ['file' => 'half-eaten.jpg', 'expected' => 'hotdog', 'tricky' => true],
    ['file' => 'dachshund.jpg', 'expected' => 'not_hotdog', 'tricky' => true],
    ['file' => 'banana.jpg', 'expected' => 'not_hotdog'],
    ['file' => 'blurry.jpg', 'expected' => 'unsure'],
    ['file' => 'broken.jpg', 'expected' => 'sausage'],
    ['expected' => 'hotdog'],
    'not an entry',
]);

it('answers known samples from expected.json, tricky ones nearer the band', function (string $file, Verdict $verdict, float $min, float $max) use ($expected) {
    foreach (['a', 'b', 'c', 'd'] as $bytes) {
        $result = fakeDetector($expected)->detect(fakeImage($bytes, $file));

        expect($result->verdict)->toBe($verdict)
            ->and($result->confidence)->toBeGreaterThanOrEqual($min)->toBeLessThanOrEqual($max);
    }
})->with([
    'easy yes' => ['classic.jpg', Verdict::Hotdog, 0.85, 0.99],
    'tricky yes' => ['half-eaten.jpg', Verdict::Hotdog, 0.65, 0.85],
    'tricky no' => ['dachshund.jpg', Verdict::NotHotdog, 0.15, 0.35],
    'easy no' => ['banana.jpg', Verdict::NotHotdog, 0.01, 0.15],
    'not sure' => ['blurry.jpg', Verdict::Unsure, 0.4, 0.6],
]);

it('matches on the basename of the original filename', function () use ($expected) {
    expect(fakeDetector($expected)->detect(fakeImage('x', 'uploads/tmp/classic.jpg'))->verdict)
        ->toBe(Verdict::Hotdog);
});

it('ignores entries with an unknown verdict or no file', function () use ($expected) {
    $known = fakeDetector($expected)->detect(fakeImage('same-bytes', 'broken.jpg'));
    $unknown = fakeDetector()->detect(fakeImage('same-bytes', 'broken.jpg'));

    expect($known)->toEqual($unknown);
});

it('is deterministic for the same bytes and varies with different bytes', function () {
    $detector = fakeDetector();

    $first = $detector->detect(fakeImage('photo-one'));

    expect($detector->detect(fakeImage('photo-one')))->toEqual($first)
        ->and($detector->detect(fakeImage('photo-two'))->confidence)->not->toBe($first->confidence);
});

it('spreads unknown images across all three verdicts', function () {
    $detector = fakeDetector();

    $verdicts = collect(range(1, 60))
        ->map(fn (int $i) => $detector->detect(fakeImage("image-{$i}"))->verdict)
        ->unique();

    expect($verdicts)->toHaveCount(3);
});

it('returns a plausible box inside the image only for hotdogs', function () use ($expected) {
    $detector = fakeDetector($expected);

    foreach (range(1, 25) as $i) {
        $result = $detector->detect(fakeImage("hotdog-{$i}", 'classic.jpg'));

        expect($result->box)->not->toBeNull()
            ->and($result->box->width())->toBeGreaterThanOrEqual(0.35)->toBeLessThanOrEqual(0.7)
            ->and($result->box->height())->toBeGreaterThanOrEqual(0.25)->toBeLessThanOrEqual(0.55)
            ->and($result->box->left)->toBeGreaterThan(0.0)
            ->and($result->box->right)->toBeLessThan(1.0)
            ->and($result->box->confidence)->toBeGreaterThanOrEqual(0.7)->toBeLessThanOrEqual(0.95);
    }

    expect($detector->detect(fakeImage('x', 'banana.jpg'))->box)->toBeNull();
});

it('reports the fake model and a believable latency', function () {
    $result = fakeDetector()->detect(fakeImage('latency'));

    expect($result->model)->toBe(FakeHotdogDetector::MODEL)
        ->and($result->latencyMs)->toBeGreaterThanOrEqual(150)->toBeLessThanOrEqual(450);
});

it('treats a missing or malformed expected.json as no known samples', function (?string $json) {
    $result = fakeDetector($json)->detect(fakeImage('bytes', 'classic.jpg'));

    expect($result)->toEqual(fakeDetector()->detect(fakeImage('bytes')));
})->with([
    'missing' => [null],
    'malformed' => ['{not json'],
    'not a list' => ['"classic.jpg"'],
]);
