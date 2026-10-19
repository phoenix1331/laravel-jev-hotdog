<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Hotdog\BoundingBox;
use App\Hotdog\ConfidenceBand;
use App\Hotdog\Contracts\HotdogDetector;
use App\Hotdog\HotdogResult;
use App\Hotdog\NormalisedImage;
use Closure;

/**
 * Answers with a fixed confidence (or a closure per image) and records
 * every image it was asked about.
 */
final class StubDetector implements HotdogDetector
{
    /** @var list<NormalisedImage> */
    public array $seen = [];

    public function __construct(private readonly Closure|float $answer) {}

    /**
     * Bind a stub as the app's detector and return it.
     */
    public static function bind(Closure|float $answer): self
    {
        $detector = new self($answer);
        app()->instance(HotdogDetector::class, $detector);

        return $detector;
    }

    public function detect(NormalisedImage $image): HotdogResult
    {
        $this->seen[] = $image;

        if ($this->answer instanceof Closure) {
            return ($this->answer)($image);
        }

        return HotdogResult::decide(
            new ConfidenceBand(0.4, 0.6),
            $this->answer,
            new BoundingBox(top: 0.2, bottom: 0.66, left: 0.19, right: 0.68, confidence: 0.9),
            321,
            'jev-1.14.0',
        );
    }
}
