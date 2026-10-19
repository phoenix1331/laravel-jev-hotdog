<?php

declare(strict_types=1);

namespace App\Hotdog\Detectors;

use App\Hotdog\BoundingBox;
use App\Hotdog\ConfidenceBand;
use App\Hotdog\Contracts\HotdogDetector;
use App\Hotdog\HotdogResult;
use App\Hotdog\NormalisedImage;
use App\Hotdog\Samples\SampleCatalogue;
use App\Hotdog\Verdict;

/**
 * Answers locally so the app works without a key. Known samples get the
 * answer from expected.json (tricky ones closer to the band, but still
 * right); anything else gets a deterministic answer from its bytes, so the
 * same photo always gives the same verdict.
 */
final class FakeHotdogDetector implements HotdogDetector
{
    public const MODEL = 'fake';

    public function __construct(
        private readonly ConfidenceBand $band,
        private readonly SampleCatalogue $samples,
    ) {}

    public function detect(NormalisedImage $image): HotdogResult
    {
        $seed = self::seed($image->bytes);
        $known = $image->originalName === null ? null : $this->samples->find($image->originalName);

        $confidence = $known === null
            ? $seed[0]
            : self::confidenceFor($known->expected, $known->tricky, $seed[0]);

        return HotdogResult::decide(
            band: $this->band,
            confidence: round($confidence, 4),
            box: self::plausibleBox($seed),
            latencyMs: 150 + (int) round($seed[4] * 300),
            model: self::MODEL,
        );
    }

    private static function confidenceFor(Verdict $expected, bool $tricky, float $jitter): float
    {
        return match (true) {
            $expected === Verdict::Hotdog && $tricky => 0.65 + 0.2 * $jitter,
            $expected === Verdict::Hotdog => 0.85 + 0.14 * $jitter,
            $expected === Verdict::NotHotdog && $tricky => 0.15 + 0.2 * $jitter,
            $expected === Verdict::NotHotdog => 0.01 + 0.14 * $jitter,
            default => 0.4 + 0.2 * $jitter,
        };
    }

    /**
     * A box covering a third to two thirds of the image, near the centre.
     *
     * @param  list<float>  $seed
     */
    private static function plausibleBox(array $seed): BoundingBox
    {
        $width = 0.35 + 0.35 * $seed[1];
        $height = 0.25 + 0.3 * $seed[2];
        $left = (1 - $width) * (0.3 + 0.4 * $seed[3]);
        $top = (1 - $height) * (0.3 + 0.4 * $seed[4]);

        return new BoundingBox(
            top: round($top, 4),
            bottom: round($top + $height, 4),
            left: round($left, 4),
            right: round($left + $width, 4),
            confidence: round(0.7 + 0.25 * $seed[1], 4),
        );
    }

    /**
     * Five stable fractions in [0, 1] derived from the image bytes.
     *
     * @return list<float>
     */
    private static function seed(string $bytes): array
    {
        return array_map(
            static fn (string $chunk): float => hexdec($chunk) / 0xFFFFFFFF,
            str_split(sha1($bytes), 8),
        );
    }
}
