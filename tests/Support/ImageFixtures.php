<?php

declare(strict_types=1);

namespace Tests\Support;

use GdImage;
use Imagick;
use ImagickPixel;
use Intervention\Image\ImageManager;
use RuntimeException;

/**
 * Generates test images in memory, so no binary fixtures are committed and
 * every case states its own dimensions.
 */
final class ImageFixtures
{
    public const RED = [255, 0, 0];

    public const BLUE = [0, 0, 255];

    public const WHITE = [255, 255, 255];

    /**
     * Blue canvas with a red top-left quadrant, so rotation is detectable.
     */
    public static function jpeg(int $width, int $height, bool $progressive = false): string
    {
        $image = self::marked($width, $height);
        imageinterlace($image, $progressive);

        ob_start();
        imagejpeg($image, null, 90);

        return (string) ob_get_clean();
    }

    /**
     * A JPEG whose pixels are stored sideways with an EXIF orientation tag,
     * the way phone cameras save portrait photos.
     */
    public static function exifRotatedJpeg(int $storedWidth, int $storedHeight, int $orientation = 6): string
    {
        $jpeg = self::jpeg($storedWidth, $storedHeight);

        $tiff = 'MM'."\x00\x2A".pack('N', 8)
            .pack('n', 1)
            .pack('nnNn', 0x0112, 3, 1, $orientation)."\x00\x00"
            .pack('N', 0);
        $exif = "Exif\x00\x00".$tiff;
        $app1 = "\xFF\xE1".pack('n', strlen($exif) + 2).$exif;

        return substr($jpeg, 0, 2).$app1.substr($jpeg, 2);
    }

    public static function png(int $width, int $height, bool $transparent = false): string
    {
        $image = self::marked($width, $height);

        if ($transparent) {
            $image = imagecreatetruecolor($width, $height);
            imagesavealpha($image, true);
            imagefill($image, 0, 0, (int) imagecolorallocatealpha($image, 0, 0, 0, 127));
        }

        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    public static function webp(int $width, int $height): string
    {
        ob_start();
        imagewebp(self::marked($width, $height));

        return (string) ob_get_clean();
    }

    public static function bmp(int $width, int $height): string
    {
        ob_start();
        imagebmp(self::marked($width, $height));

        return (string) ob_get_clean();
    }

    /**
     * Two frames: solid red, then solid blue.
     */
    public static function animatedGif(int $width, int $height): string
    {
        $manager = ImageManager::gd();

        return (string) $manager->animate(function ($animation) use ($manager, $width, $height): void {
            $animation->add($manager->create($width, $height)->fill('ff0000'), 0.2);
            $animation->add($manager->create($width, $height)->fill('0000ff'), 0.2);
        })->toGif();
    }

    public static function heic(int $width, int $height): string
    {
        if (! class_exists(Imagick::class)) {
            throw new RuntimeException('Imagick is needed to generate a HEIC fixture.');
        }

        $image = new Imagick;
        $image->newImage($width, $height, new ImagickPixel('red'));
        $image->setImageFormat('heic');

        return $image->getImageBlob();
    }

    /**
     * A valid PNG signature and header claiming huge dimensions, with no
     * pixel data: enough for a size check, never decodable.
     */
    public static function pngHeaderOnly(int $width, int $height): string
    {
        $ihdr = pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0);

        return "\x89PNG\r\n\x1A\n"
            .pack('N', 13).'IHDR'.$ihdr.pack('N', crc32('IHDR'.$ihdr));
    }

    /**
     * @return array{int, int, int}
     */
    public static function colourAt(string $bytes, int $x, int $y): array
    {
        $image = imagecreatefromstring($bytes);

        if ($image === false) {
            throw new RuntimeException('Could not decode image.');
        }

        $rgb = imagecolorat($image, $x, $y);

        return [($rgb >> 16) & 0xFF, ($rgb >> 8) & 0xFF, $rgb & 0xFF];
    }

    /**
     * @param  array{int, int, int}  $expected
     */
    public static function isNear(array $actual, array $expected, int $tolerance = 40): bool
    {
        foreach ($expected as $i => $channel) {
            if (abs($actual[$i] - $channel) > $tolerance) {
                return false;
            }
        }

        return true;
    }

    private static function marked(int $width, int $height): GdImage
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, ...self::BLUE));
        imagefilledrectangle($image, 0, 0, intdiv($width, 2) - 1, intdiv($height, 2) - 1, (int) imagecolorallocate($image, ...self::RED));

        return $image;
    }
}
