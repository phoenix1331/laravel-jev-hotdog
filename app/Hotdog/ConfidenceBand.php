<?php

declare(strict_types=1);

namespace App\Hotdog;

use InvalidArgumentException;

/**
 * Maps a Noul probability to a verdict. Both bounds belong to the
 * "not sure" band, so exactly 0.60 is NOT SURE, never HOTDOG.
 */
final readonly class ConfidenceBand
{
    public function __construct(
        public float $min,
        public float $max,
    ) {
        if ($min < 0.0 || $max > 1.0 || $min > $max) {
            throw new InvalidArgumentException(
                "The not sure band must satisfy 0 <= min <= max <= 1, got {$min} to {$max}."
            );
        }
    }

    public function verdictFor(float $confidence): Verdict
    {
        if ($confidence > $this->max) {
            return Verdict::Hotdog;
        }

        if ($confidence < $this->min) {
            return Verdict::NotHotdog;
        }

        return Verdict::Unsure;
    }
}
