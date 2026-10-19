<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Hotdog\Samples\SampleCatalogue;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use ImagickException;
use Intervention\Image\Exceptions\RuntimeException as InterventionException;
use Intervention\Image\ImageManager;

/**
 * Small JPEG previews of the samples for the "try one of these" row.
 * samples/ is not public, and the edge cases include a 5000px photo, so
 * thumbnails are generated once per file version and cached.
 */
final class SampleThumbnailController extends Controller
{
    public const SIZE = 480;

    public function __invoke(string $file, SampleCatalogue $samples, ImageManager $images): Response
    {
        $sample = $samples->find($file);

        abort_if($sample === null || $sample->file !== $file || ! $sample->exists(), 404);

        $key = sprintf('sample-thumbnail:%s:%d:%d', $sample->file, filemtime($sample->path), filesize($sample->path));

        try {
            $bytes = Cache::rememberForever($key, function () use ($images, $sample): string {
                /** @throws InterventionException|ImagickException Imagick lets ImagickException escape undeclared for some damaged files */
                $image = $images->read($sample->path);

                return (string) $image->scaleDown(self::SIZE, self::SIZE)->toJpeg(quality: 80);
            });
        } catch (InterventionException|ImagickException) {
            abort(404);
        }

        return response($bytes, 200, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
