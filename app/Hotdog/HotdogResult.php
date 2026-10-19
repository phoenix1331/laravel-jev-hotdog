<?php

declare(strict_types=1);

namespace App\Hotdog;

final readonly class HotdogResult
{
    public function __construct(
        public Verdict $verdict,
        public float $confidence,
        public ?BoundingBox $box,
        public int $latencyMs,
        public string $model,
    ) {}

    /**
     * Box always returns a box, even for a photo of a cat, so the box is
     * only kept when the Noul answer says HOTDOG.
     */
    public static function decide(
        ConfidenceBand $band,
        float $confidence,
        ?BoundingBox $box,
        int $latencyMs,
        string $model,
    ): self {
        $verdict = $band->verdictFor($confidence);

        return new self(
            verdict: $verdict,
            confidence: $confidence,
            box: $verdict === Verdict::Hotdog ? $box : null,
            latencyMs: $latencyMs,
            model: $model,
        );
    }
}
