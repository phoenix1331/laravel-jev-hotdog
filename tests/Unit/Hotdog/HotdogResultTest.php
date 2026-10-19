<?php

declare(strict_types=1);

use App\Hotdog\BoundingBox;
use App\Hotdog\ConfidenceBand;
use App\Hotdog\HotdogResult;
use App\Hotdog\Verdict;

beforeEach(function () {
    $this->band = new ConfidenceBand(0.4, 0.6);
    $this->box = new BoundingBox(0.2, 0.7, 0.1, 0.9, 0.88);
});

it('keeps the box for a hotdog', function () {
    $result = HotdogResult::decide($this->band, 0.97, $this->box, 412, 'jev-1.14.0');

    expect($result->verdict)->toBe(Verdict::Hotdog)
        ->and($result->confidence)->toBe(0.97)
        ->and($result->box)->toBe($this->box)
        ->and($result->latencyMs)->toBe(412)
        ->and($result->model)->toBe('jev-1.14.0');
});

it('drops the box unless the verdict is hotdog', function (float $confidence, Verdict $verdict) {
    $result = HotdogResult::decide($this->band, $confidence, $this->box, 300, 'jev-1.14.0');

    expect($result->verdict)->toBe($verdict)
        ->and($result->box)->toBeNull();
})->with([
    'not sure at the upper bound' => [0.6, Verdict::Unsure],
    'not hotdog' => [0.03, Verdict::NotHotdog],
]);

it('allows a hotdog without a box', function () {
    expect(HotdogResult::decide($this->band, 0.9, null, 10, 'fake')->box)->toBeNull();
});
