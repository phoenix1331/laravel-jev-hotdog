<?php

declare(strict_types=1);

use App\Hotdog\ImageNormaliser;
use App\Hotdog\ImageRejected;
use App\Providers\HotdogServiceProvider;
use Tests\Support\ImageFixtures as F;

function normaliser(string $driver, int $maxMegapixels = 50): ImageNormaliser
{
    if ($driver === 'imagick' && ! extension_loaded('imagick')) {
        test()->markTestSkipped('Imagick is not installed.');
    }

    return new ImageNormaliser(HotdogServiceProvider::imageManager($driver), $maxMegapixels);
}

/**
 * @return array{int, int}
 */
function dimensionsOf(string $bytes): array
{
    $size = getimagesizefromstring($bytes);

    return [$size[0], $size[1]];
}

dataset('drivers', ['gd', 'imagick']);

describe('target size', function () {
    it('fits every image inside 280 to 1568 per side', function (int $width, int $height, array $expected) {
        expect(ImageNormaliser::targetSize($width, $height))->toBe($expected);
    })->with([
        'already within limits' => [800, 600, [800, 600, 800, 600]],
        'exact limits untouched' => [1568, 280, [1568, 280, 1568, 280]],
        '12mp phone photo' => [4032, 3024, [1568, 1176, 1568, 1176]],
        '5000px landscape' => [5000, 3000, [1568, 941, 1568, 941]],
        'portrait' => [3000, 5000, [941, 1568, 941, 1568]],
        'tiny square scales up' => [100, 100, [280, 280, 280, 280]],
        'small landscape scales up' => [100, 50, [560, 280, 560, 280]],
        'one side under minimum' => [1000, 200, [1400, 280, 1400, 280]],
        'too thin to scale: padded' => [3000, 100, [1568, 52, 1568, 280]],
        'tiny and too thin: padded' => [1000, 10, [1000, 10, 1000, 280]],
        'one pixel line' => [1, 2000, [1, 1568, 280, 1568]],
    ]);

    it('never leaves a side outside the limits', function () {
        foreach ([[1, 1], [279, 279], [281, 5000], [9000, 9000], [1569, 1569], [6000, 299]] as [$w, $h]) {
            [, , $canvasWidth, $canvasHeight] = ImageNormaliser::targetSize($w, $h);

            expect($canvasWidth)->toBeGreaterThanOrEqual(280)->toBeLessThanOrEqual(1568)
                ->and($canvasHeight)->toBeGreaterThanOrEqual(280)->toBeLessThanOrEqual(1568)
                ->and($canvasWidth * $canvasHeight)->toBeGreaterThanOrEqual(78_400)->toBeLessThanOrEqual(2_458_624);
        }
    });
});

describe('normalise', function () {
    it('downscales a huge jpeg so the longest side is 1568', function (string $driver) {
        $image = normaliser($driver)->normalise(F::jpeg(5000, 3000), 'big.jpg');

        expect([$image->width, $image->height])->toBe([1568, 941])
            ->and(dimensionsOf($image->bytes))->toBe([1568, 941])
            ->and([$image->originalWidth, $image->originalHeight])->toBe([5000, 3000])
            ->and($image->mime)->toBe('image/jpeg')
            ->and($image->originalName)->toBe('big.jpg')
            ->and(strlen($image->bytes))->toBeLessThan(16 * 1024 * 1024);
    })->with('drivers');

    it('upscales a tiny png to the 280px minimum', function (string $driver) {
        $image = normaliser($driver)->normalise(F::png(100, 100));

        expect(dimensionsOf($image->bytes))->toBe([280, 280])
            ->and([$image->originalWidth, $image->originalHeight])->toBe([100, 100])
            ->and(getimagesizefromstring($image->bytes)['mime'])->toBe('image/jpeg');
    })->with('drivers');

    it('pads an image too thin to scale, centring it on white', function (string $driver) {
        $image = normaliser($driver)->normalise(F::png(3000, 100));

        expect(dimensionsOf($image->bytes))->toBe([1568, 280])
            ->and(F::isNear(F::colourAt($image->bytes, 5, 5), F::WHITE))->toBeTrue()
            ->and(F::isNear(F::colourAt($image->bytes, 1400, 150), F::BLUE))->toBeTrue();
    })->with('drivers');

    it('rotates pixels upright using the exif orientation', function (string $driver) {
        $image = normaliser($driver)->normalise(F::exifRotatedJpeg(400, 300, orientation: 6));

        expect(dimensionsOf($image->bytes))->toBe([300, 400])
            ->and([$image->originalWidth, $image->originalHeight])->toBe([300, 400])
            ->and(F::isNear(F::colourAt($image->bytes, 260, 20), F::RED))->toBeTrue()
            ->and(F::isNear(F::colourAt($image->bytes, 20, 20), F::BLUE))->toBeTrue();
    })->with('drivers');

    it('strips exif and other metadata', function (string $driver) {
        $image = normaliser($driver)->normalise(F::exifRotatedJpeg(400, 300, orientation: 1));

        expect($image->bytes)->not->toContain('Exif');
    })->with('drivers');

    it('re-encodes progressive jpegs as baseline', function (string $driver) {
        $input = F::jpeg(600, 400, progressive: true);
        $image = normaliser($driver)->normalise($input);

        expect($input)->toContain("\xFF\xC2")
            ->and($image->bytes)->toContain("\xFF\xC0")
            ->and($image->bytes)->not->toContain("\xFF\xC2");
    })->with('drivers');

    it('flattens transparency onto white', function (string $driver) {
        $image = normaliser($driver)->normalise(F::png(400, 400, transparent: true));

        expect(F::isNear(F::colourAt($image->bytes, 200, 200), F::WHITE))->toBeTrue();
    })->with('drivers');

    it('keeps only the first frame of an animated gif', function (string $driver) {
        $image = normaliser($driver)->normalise(F::animatedGif(320, 320));

        expect(dimensionsOf($image->bytes))->toBe([320, 320])
            ->and(F::isNear(F::colourAt($image->bytes, 160, 160), F::RED))->toBeTrue()
            ->and($image->bytes)->not->toContain('NETSCAPE2.0');
    })->with('drivers');

    it('converts webp to jpeg', function (string $driver) {
        $image = normaliser($driver)->normalise(F::webp(500, 400));

        expect(getimagesizefromstring($image->bytes)['mime'])->toBe('image/jpeg')
            ->and(dimensionsOf($image->bytes))->toBe([500, 400]);
    })->with('drivers');

    it('converts heic to jpeg when imagick supports it', function () {
        $normaliser = normaliser('imagick');

        if (! $normaliser->supportsHeic()) {
            $this->markTestSkipped('This Imagick build cannot decode HEIC.');
        }

        $heic = F::heic(400, 300) ?? $this->markTestSkipped('This machine cannot encode a HEIC fixture.');

        $image = $normaliser->normalise($heic, 'IMG_0001.HEIC');

        expect(getimagesizefromstring($image->bytes)['mime'])->toBe('image/jpeg')
            ->and(dimensionsOf($image->bytes))->toBe([400, 300])
            ->and(F::isNear(F::colourAt($image->bytes, 200, 150), F::RED))->toBeTrue();
    });

    it('explains that heic needs imagick on gd', function () {
        $heic = F::heic(400, 300) ?? $this->markTestSkipped('This machine cannot encode a HEIC fixture.');

        expect(normaliser('gd')->supportsHeic())->toBeFalse();

        normaliser('gd')->normalise($heic);
    })->throws(ImageRejected::class, 'HEIC photos need the PHP Imagick extension');
});

describe('rejections', function () {
    it('rejects formats outside the accepted list', function (string $driver, string $bytes, string $mime) {
        expect(fn () => normaliser($driver)->normalise($bytes))
            ->toThrow(ImageRejected::class, "That file type ({$mime}) is not supported.");
    })->with('drivers')->with([
        'bmp' => [F::bmp(300, 300), 'image/bmp'],
        'text' => ['definitely not a photo', 'text/plain'],
        'empty' => ['', 'application/x-empty'],
    ]);

    it('rejects a damaged image', function (string $driver) {
        normaliser($driver)->normalise(substr(F::jpeg(600, 400), 0, 300));
    })->with('drivers')->throws(ImageRejected::class, 'could not be read');

    it('refuses decompression bombs before decoding', function (string $driver) {
        normaliser($driver, maxMegapixels: 50)->normalise(F::pngHeaderOnly(10_000, 6_000));
    })->with('drivers')->throws(ImageRejected::class, 'That image is 60 megapixels; the limit is 50.');

    it('lets images at the megapixel limit through to decoding', function (string $driver) {
        expect(fn () => normaliser($driver, maxMegapixels: 60)->normalise(F::pngHeaderOnly(10_000, 6_000)))
            ->toThrow(ImageRejected::class, 'could not be read');
    })->with('drivers');
});
