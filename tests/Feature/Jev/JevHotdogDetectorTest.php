<?php

declare(strict_types=1);

use App\Hotdog\ConfidenceBand;
use App\Hotdog\Detectors\JevHotdogDetector;
use App\Hotdog\NormalisedImage;
use App\Hotdog\Verdict;
use App\Jev\JevClient;
use App\Jev\JevError;
use App\Jev\JevException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

const DETECTOR_BASE = 'https://api.typesafe.test/v1';

function jevDetector(): JevHotdogDetector
{
    return new JevHotdogDetector(
        new JevClient('test-key', DETECTOR_BASE, 5),
        new ConfidenceBand(0.4, 0.6),
        'jev-preview',
    );
}

function jpeg(): NormalisedImage
{
    return new NormalisedImage('jpeg-bytes', 'image/jpeg', 1568, 1176, 4032, 3024, 'lunch.jpg');
}

function fakeAnswers(mixed $noul, mixed $box = null, mixed $boxConfidence = 0.9): void
{
    $answers = ['is_hotdog' => ['type' => 'noul', 'noul' => $noul]];

    if ($box !== null) {
        $answers['hotdog'] = ['type' => 'box', 'box' => $box, 'confidence' => $boxConfidence];
    }

    Http::fake([DETECTOR_BASE.'/systemone' => Http::response(
        ['model' => 'jev-1.14.0', 'answers' => $answers, 'usage' => ['input_tokens' => 1200, 'output_tokens' => 9]],
        200,
        ['x-typesafe-request-id' => 'req_abc'],
    )]);
}

const EDGES = ['top' => 0.2, 'bottom' => 0.66, 'left' => 0.19, 'right' => 0.68];

it('sends one request with the image as an asset and both questions', function () {
    fakeAnswers(0.97, EDGES);

    jevDetector()->detect(jpeg());

    Http::assertSentCount(1);
    Http::assertSent(function (Request $request) {
        $reference = $request['state']['photo'];
        $asset = $request['assets'][$reference] ?? null;

        return $request->hasHeader('TypeSafe-Danger-Zone-Images', 'true')
            && $request['model'] === 'jev-preview'
            && Str::isUuid($reference)
            && $asset === ['type' => 'image', 'format' => 'jpeg', 'data_base64' => base64_encode('jpeg-bytes')]
            && $request['questions']['is_hotdog'] === ['type' => 'noul', 'instructions' => 'Does `photo` contain a hot dog (a sausage served in a bun)?']
            && $request['questions']['hotdog'] === ['type' => 'box', 'instructions' => 'the hot dog in `photo`'];
    });
});

it('uses a fresh asset reference per request and png format for png images', function () {
    $detector = jevDetector();
    $png = new NormalisedImage('png-bytes', 'image/png', 300, 300, 300, 300);

    $first = $detector->payload($png);
    $second = $detector->payload($png);

    expect(array_key_first($first['assets']))->not->toBe(array_key_first($second['assets']))
        ->and($first['assets'][$first['state']['photo']]['format'])->toBe('png');
});

it('returns hotdog with the box and the versioned model that answered', function () {
    fakeAnswers(0.97, EDGES, 0.92);

    $result = jevDetector()->detect(jpeg());

    expect($result->verdict)->toBe(Verdict::Hotdog)
        ->and($result->confidence)->toBe(0.97)
        ->and($result->model)->toBe('jev-1.14.0')
        ->and($result->box?->toCssPercentages())->toBe(['top' => '20%', 'left' => '19%', 'width' => '49%', 'height' => '46%'])
        ->and($result->box?->confidence)->toBe(0.92);
});

it('ignores the box when the verdict is not hotdog', function (float $noul, Verdict $verdict) {
    fakeAnswers($noul, EDGES);

    $result = jevDetector()->detect(jpeg());

    expect($result->verdict)->toBe($verdict)->and($result->box)->toBeNull();
})->with([
    'not sure' => [0.55, Verdict::Unsure],
    'not hotdog' => [0.02, Verdict::NotHotdog],
]);

it('accepts integer answers and a null box confidence', function () {
    fakeAnswers(1, ['top' => 0, 'bottom' => 1, 'left' => 0, 'right' => 1], null);

    $result = jevDetector()->detect(jpeg());

    expect($result->confidence)->toBe(1.0)
        ->and($result->box?->toPixels(10, 10))->toBe(['x' => 0, 'y' => 0, 'width' => 10, 'height' => 10])
        ->and($result->box?->confidence)->toBeNull();
});

it('keeps the verdict when the box is missing or malformed', function (mixed $box) {
    fakeAnswers(0.95, $box);

    $result = jevDetector()->detect(jpeg());

    expect($result->verdict)->toBe(Verdict::Hotdog)->and($result->box)->toBeNull();
})->with([
    'no box answer' => [null],
    'box not an object' => ['nope'],
    'missing edge' => [['top' => 0.1, 'bottom' => 0.5, 'left' => 0.1]],
    'inverted' => [['top' => 0.9, 'bottom' => 0.1, 'left' => 0.1, 'right' => 0.5]],
    'out of range' => [['top' => 0.1, 'bottom' => 1.2, 'left' => 0.1, 'right' => 0.5]],
]);

it('clamps a noul probability outside 0 to 1', function () {
    fakeAnswers(1.0000001);

    expect(jevDetector()->detect(jpeg())->confidence)->toBe(1.0);
});

it('fails clearly when the noul answer is missing or not a number', function () {
    Http::fake([DETECTOR_BASE.'/systemone' => Http::sequence()
        ->push(['model' => 'jev-1.14.0', 'answers' => ['hotdog' => ['type' => 'box']]])
        ->push(['model' => 'jev-1.14.0', 'answers' => ['is_hotdog' => ['type' => 'noul', 'noul' => 'yes']]])]);

    foreach ([1, 2] as $attempt) {
        try {
            jevDetector()->detect(jpeg());
            $this->fail('Expected a JevException.');
        } catch (JevException $e) {
            expect($e->error)->toBe(JevError::UnexpectedResponse);
        }
    }
});

it('logs the verdict, confidence and latency but never the image', function () {
    Log::spy();
    fakeAnswers(0.97, EDGES);

    jevDetector()->detect(jpeg());

    Log::shouldHaveReceived('info')->once()->withArgs(function (string $message, array $context) {
        return $message === 'hotdog.detected'
            && array_keys($context) === ['verdict', 'confidence', 'latency_ms', 'model', 'request_id']
            && $context['verdict'] === 'hotdog'
            && $context['request_id'] === 'req_abc'
            && ! str_contains(json_encode($context), base64_encode('jpeg-bytes'));
    });
});
