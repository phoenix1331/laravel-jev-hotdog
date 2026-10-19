<?php

declare(strict_types=1);

use App\Hotdog\ConfidenceBand;
use App\Hotdog\Verdict;

it('maps confidence to a verdict with both bounds inside the not sure band', function (float $confidence, Verdict $expected) {
    expect((new ConfidenceBand(0.4, 0.6))->verdictFor($confidence))->toBe($expected);
})->with([
    'certain no' => [0.0, Verdict::NotHotdog],
    'just below min' => [0.3999, Verdict::NotHotdog],
    'exactly min' => [0.4, Verdict::Unsure],
    'middle' => [0.5, Verdict::Unsure],
    'exactly max' => [0.6, Verdict::Unsure],
    'just above max' => [0.6001, Verdict::Hotdog],
    'certain yes' => [1.0, Verdict::Hotdog],
]);

it('allows a zero-width band', function () {
    $band = new ConfidenceBand(0.5, 0.5);

    expect($band->verdictFor(0.5))->toBe(Verdict::Unsure)
        ->and($band->verdictFor(0.51))->toBe(Verdict::Hotdog)
        ->and($band->verdictFor(0.49))->toBe(Verdict::NotHotdog);
});

it('rejects a band outside 0 to 1 or with min above max', function (float $min, float $max) {
    new ConfidenceBand($min, $max);
})->with([
    'negative min' => [-0.1, 0.6],
    'max above one' => [0.4, 1.1],
    'inverted' => [0.7, 0.3],
])->throws(InvalidArgumentException::class);
