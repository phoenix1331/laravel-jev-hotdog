<?php

declare(strict_types=1);

namespace App\Hotdog;

/**
 * An upload converted to what Jev accepts: still PNG or JPEG within the
 * size limits. Held in memory only; never written to disk.
 */
final readonly class NormalisedImage
{
    public function __construct(
        public string $bytes,
        public string $mime,
        public int $width,
        public int $height,
        public int $originalWidth,
        public int $originalHeight,
        public ?string $originalName = null,
    ) {}

    public function base64(): string
    {
        return base64_encode($this->bytes);
    }

    /**
     * The asset `format` value Jev expects.
     */
    public function format(): string
    {
        return $this->mime === 'image/png' ? 'png' : 'jpeg';
    }
}
