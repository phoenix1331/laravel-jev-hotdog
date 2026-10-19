<?php

declare(strict_types=1);

namespace App\Hotdog;

use finfo;
use ImagickException;
use Intervention\Image\Exceptions\RuntimeException as InterventionException;
use Intervention\Image\ImageManager;
use Intervention\Image\MediaType;

/**
 * Turns any reasonable upload into a still, upright, baseline JPEG inside
 * Jev's limits. Jev rejects out-of-range images rather than resizing them,
 * and strips EXIF without applying orientation, so all of that happens here.
 *
 * The manager must be built with autoOrientation and strip on, and
 * decodeAnimation off (first GIF frame only); see HotdogServiceProvider.
 */
final readonly class ImageNormaliser
{
    public const MAX_SIDE = 1568;

    public const MIN_SIDE = 280;

    public const JPEG_QUALITY = 85;

    private const ACCEPTED = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/heic', 'image/heif'];

    private const HEIC = ['image/heic', 'image/heif'];

    public function __construct(
        private ImageManager $manager,
        private int $maxInputMegapixels = 50,
    ) {}

    public function normalise(string $bytes, ?string $originalName = null): NormalisedImage
    {
        $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);

        if (in_array($mime, self::HEIC, true) && ! $this->supportsHeic()) {
            throw ImageRejected::heicUnsupported();
        }

        if (! in_array($mime, self::ACCEPTED, true)) {
            throw ImageRejected::unsupportedFormat($mime);
        }

        $this->guardPixelCount($bytes);

        try {
            /** @throws InterventionException|ImagickException Imagick lets ImagickException escape undeclared for some damaged files */
            $image = $this->manager->read($bytes);
        } catch (InterventionException|ImagickException $e) {
            throw ImageRejected::unreadable($e);
        }

        $originalWidth = $image->width();
        $originalHeight = $image->height();

        [$width, $height, $canvasWidth, $canvasHeight] = self::targetSize($originalWidth, $originalHeight);

        if ($width !== $originalWidth || $height !== $originalHeight) {
            $image->resize($width, $height);
        }

        if ($canvasWidth !== $width || $canvasHeight !== $height) {
            $image->resizeCanvas($canvasWidth, $canvasHeight, 'ffffff', 'center');
        }

        return new NormalisedImage(
            bytes: (string) $image->toJpeg(quality: self::JPEG_QUALITY, progressive: false, strip: true),
            mime: 'image/jpeg',
            width: $canvasWidth,
            height: $canvasHeight,
            originalWidth: $originalWidth,
            originalHeight: $originalHeight,
            originalName: $originalName,
        );
    }

    /**
     * Scale so the longest side is at most 1568 and the shortest at least
     * 280. When an extreme aspect ratio makes both impossible, keep the
     * downscale and pad the short side with white instead.
     *
     * @return array{int, int, int, int} image width, image height, canvas width, canvas height
     */
    public static function targetSize(int $width, int $height): array
    {
        $long = max($width, $height);
        $short = min($width, $height);

        $scale = min(1.0, self::MAX_SIDE / $long);

        if ($short * $scale < self::MIN_SIDE && $long * (self::MIN_SIDE / $short) <= self::MAX_SIDE) {
            $scale = self::MIN_SIDE / $short;
        }

        $targetWidth = max(1, min(self::MAX_SIDE, (int) round($width * $scale)));
        $targetHeight = max(1, min(self::MAX_SIDE, (int) round($height * $scale)));

        return [
            $targetWidth,
            $targetHeight,
            max($targetWidth, self::MIN_SIDE),
            max($targetHeight, self::MIN_SIDE),
        ];
    }

    public function supportsHeic(): bool
    {
        return $this->manager->driver()->supports(MediaType::IMAGE_HEIC);
    }

    /**
     * Decoding expands to roughly 4 bytes per pixel, so refuse decompression
     * bombs before decoding. HEIC headers are not readable here; Imagick
     * enforces its own resource limits for those.
     */
    private function guardPixelCount(string $bytes): void
    {
        $size = @getimagesizefromstring($bytes);

        if ($size === false) {
            return;
        }

        $megapixels = (int) ceil($size[0] * $size[1] / 1_000_000);

        if ($megapixels > $this->maxInputMegapixels) {
            throw ImageRejected::tooManyPixels($megapixels, $this->maxInputMegapixels);
        }
    }
}
