<?php

declare(strict_types=1);

use App\Hotdog\NormalisedImage;

it('exposes base64 bytes and the jev asset format', function (string $mime, string $format) {
    $image = new NormalisedImage('raw-bytes', $mime, 1568, 1176, 4032, 3024, 'phone.heic');

    expect($image->base64())->toBe(base64_encode('raw-bytes'))
        ->and($image->format())->toBe($format)
        ->and($image->originalName)->toBe('phone.heic');
})->with([
    'jpeg' => ['image/jpeg', 'jpeg'],
    'png' => ['image/png', 'png'],
]);
