<?php

declare(strict_types=1);

namespace App\Hotdog;

use InvalidArgumentException;

/**
 * A Box answer: edges as fractions of the image, measured from the top-left.
 * Fractions survive resizing, so the same box fits the normalised image,
 * the original upload and the scaled preview on screen.
 */
final readonly class BoundingBox
{
    public function __construct(
        public float $top,
        public float $bottom,
        public float $left,
        public float $right,
        public ?float $confidence = null,
    ) {
        foreach (['top' => $top, 'bottom' => $bottom, 'left' => $left, 'right' => $right] as $edge => $value) {
            if ($value < 0.0 || $value > 1.0) {
                throw new InvalidArgumentException("Box edge {$edge} must be between 0 and 1, got {$value}.");
            }
        }

        if ($top > $bottom || $left > $right) {
            throw new InvalidArgumentException('Box edges are inverted: top must not exceed bottom, nor left exceed right.');
        }
    }

    public function width(): float
    {
        return $this->right - $this->left;
    }

    public function height(): float
    {
        return $this->bottom - $this->top;
    }

    /**
     * Inline style values for an absolutely positioned overlay.
     *
     * @return array{top: string, left: string, width: string, height: string}
     */
    public function toCssPercentages(): array
    {
        return [
            'top' => self::percent($this->top),
            'left' => self::percent($this->left),
            'width' => self::percent($this->width()),
            'height' => self::percent($this->height()),
        ];
    }

    /**
     * @return array{x: int, y: int, width: int, height: int}
     */
    public function toPixels(int $imageWidth, int $imageHeight): array
    {
        $x = (int) round($this->left * $imageWidth);
        $y = (int) round($this->top * $imageHeight);

        return [
            'x' => $x,
            'y' => $y,
            'width' => (int) round($this->right * $imageWidth) - $x,
            'height' => (int) round($this->bottom * $imageHeight) - $y,
        ];
    }

    private static function percent(float $fraction): string
    {
        return rtrim(rtrim(number_format($fraction * 100, 2, '.', ''), '0'), '.').'%';
    }
}
