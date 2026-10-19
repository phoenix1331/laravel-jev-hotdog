<?php

declare(strict_types=1);

use App\Hotdog\BoundingBox;

it('converts edges to css percentages for the overlay', function () {
    $box = new BoundingBox(top: 0.25, bottom: 0.82, left: 0.44, right: 0.84, confidence: 0.92);

    expect($box->toCssPercentages())->toBe([
        'top' => '25%',
        'left' => '44%',
        'width' => '40%',
        'height' => '57%',
    ]);
});

it('keeps two decimal places and drops trailing zeros', function () {
    $box = new BoundingBox(top: 0.12346, bottom: 0.5, left: 0.0, right: 1.0);

    expect($box->toCssPercentages())->toBe([
        'top' => '12.35%',
        'left' => '0%',
        'width' => '100%',
        'height' => '37.65%',
    ]);
});

it('measures width and height as fractions', function () {
    $box = new BoundingBox(top: 0.2, bottom: 0.66, left: 0.19, right: 0.68);

    expect($box->width())->toEqualWithDelta(0.49, 1e-9)
        ->and($box->height())->toEqualWithDelta(0.46, 1e-9)
        ->and($box->confidence)->toBeNull();
});

it('maps the same box onto images of any size', function () {
    $box = new BoundingBox(top: 0.25, bottom: 0.75, left: 0.1, right: 0.6);

    expect($box->toPixels(1568, 1176))->toBe(['x' => 157, 'y' => 294, 'width' => 784, 'height' => 588])
        ->and($box->toPixels(4032, 3024))->toBe(['x' => 403, 'y' => 756, 'width' => 2016, 'height' => 1512]);
});

it('accepts a box covering the whole image', function () {
    expect((new BoundingBox(0.0, 1.0, 0.0, 1.0))->toPixels(300, 280))
        ->toBe(['x' => 0, 'y' => 0, 'width' => 300, 'height' => 280]);
});

it('rejects edges outside 0 to 1', function (float $top, float $bottom, float $left, float $right) {
    new BoundingBox($top, $bottom, $left, $right);
})->with([
    'negative top' => [-0.01, 0.5, 0.1, 0.5],
    'bottom past edge' => [0.1, 1.01, 0.1, 0.5],
    'negative left' => [0.1, 0.5, -0.2, 0.5],
    'right past edge' => [0.1, 0.5, 0.1, 1.5],
])->throws(InvalidArgumentException::class, 'must be between 0 and 1');

it('rejects inverted edges', function (float $top, float $bottom, float $left, float $right) {
    new BoundingBox($top, $bottom, $left, $right);
})->with([
    'top below bottom' => [0.8, 0.2, 0.1, 0.5],
    'left past right' => [0.1, 0.5, 0.9, 0.2],
])->throws(InvalidArgumentException::class, 'inverted');
