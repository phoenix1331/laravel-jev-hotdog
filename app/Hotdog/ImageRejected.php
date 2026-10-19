<?php

declare(strict_types=1);

namespace App\Hotdog;

use RuntimeException;
use Throwable;

/**
 * An upload the normaliser cannot turn into something Jev accepts.
 * Messages are safe to show on screen.
 */
final class ImageRejected extends RuntimeException
{
    public static function unsupportedFormat(string $mime): self
    {
        return new self("That file type ({$mime}) is not supported. Try a JPEG, PNG, WebP, GIF or HEIC photo.");
    }

    public static function heicUnsupported(): self
    {
        return new self('HEIC photos need the PHP Imagick extension with HEIC support, which this server does not have. Export the photo as JPEG and try again.');
    }

    public static function unreadable(?Throwable $previous = null): self
    {
        return new self('That image could not be read. It may be damaged or incomplete.', previous: $previous);
    }

    public static function tooManyPixels(int $megapixels, int $limit): self
    {
        return new self("That image is {$megapixels} megapixels; the limit is {$limit}. Try a smaller photo.");
    }
}
