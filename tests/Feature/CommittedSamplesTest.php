<?php

declare(strict_types=1);

use App\Hotdog\ImageNormaliser;
use App\Hotdog\Samples\SampleCatalogue;
use App\Providers\HotdogServiceProvider;
use Illuminate\Support\Facades\Process;
use Tests\Support\TemporarySamples;

function committedSamples(): SampleCatalogue
{
    return new SampleCatalogue(base_path('samples'));
}

it('lists every file in samples/ in expected.json', function () {
    $images = collect(glob(base_path('samples/*')))
        ->map(fn (string $path) => basename($path))
        ->reject(fn (string $file) => in_array($file, ['expected.json', 'CREDITS.md', 'generate-edge-cases.php'], true))
        ->values()
        ->all();

    expect(array_diff($images, array_keys(committedSamples()->all())))->toBe([]);
});

it('credits every committed image', function () {
    $credits = file_get_contents(base_path('samples/CREDITS.md'));

    foreach (array_keys(committedSamples()->available()) as $file) {
        expect($credits)->toContain("| `{$file}` |");
    }
});

it('normalises every committed sample into jev limits', function (string $driver) {
    if ($driver === 'imagick' && ! extension_loaded('imagick')) {
        $this->markTestSkipped('Imagick is not installed.');
    }

    $normaliser = new ImageNormaliser(HotdogServiceProvider::imageManager($driver));

    foreach (committedSamples()->available() as $sample) {
        if (str_ends_with($sample->file, '.heic') && ! $normaliser->supportsHeic()) {
            continue;
        }

        $image = $normaliser->normalise((string) file_get_contents($sample->path), $sample->file);

        expect(min($image->width, $image->height))->toBeGreaterThanOrEqual(280, $sample->file)
            ->and(max($image->width, $image->height))->toBeLessThanOrEqual(1568, $sample->file);
    }
})->with(['gd', 'imagick']);

it('generates each edge case with the size and format it is meant to test', function () {
    $out = TemporarySamples::make();

    Process::path(base_path())->run(['php', 'samples/generate-edge-cases.php', $out])->throw();

    $expected = [
        'edge-huge.jpg' => [5000, 3750, 'image/jpeg'],
        'edge-tiny.png' => [100, 100, 'image/png'],
        'edge-webp.webp' => [900, 600, 'image/webp'],
        'edge-animated.gif' => [400, 300, 'image/gif'],
    ];

    foreach ($expected as $file => [$width, $height, $mime]) {
        $size = getimagesize("{$out}/{$file}");

        expect([$size[0], $size[1], $size['mime']])->toBe([$width, $height, $mime]);
    }

    expect(file_get_contents("{$out}/edge-animated.gif"))->toContain('NETSCAPE2.0');
});
