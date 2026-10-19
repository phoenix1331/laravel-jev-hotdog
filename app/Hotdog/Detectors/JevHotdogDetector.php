<?php

declare(strict_types=1);

namespace App\Hotdog\Detectors;

use App\Hotdog\BoundingBox;
use App\Hotdog\ConfidenceBand;
use App\Hotdog\Contracts\HotdogDetector;
use App\Hotdog\HotdogResult;
use App\Hotdog\NormalisedImage;
use App\Jev\JevClient;
use App\Jev\JevError;
use App\Jev\JevException;
use App\Jev\JevResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Asks Noul and Box in one request: the image is billed once and the
 * Noul answer alone decides the verdict. Box always returns a box, so it
 * is only kept when the verdict is HOTDOG.
 */
final readonly class JevHotdogDetector implements HotdogDetector
{
    public const NOUL_QUESTION = 'is_hotdog';

    public const BOX_QUESTION = 'hotdog';

    public function __construct(
        private JevClient $client,
        private ConfidenceBand $band,
        private string $model,
    ) {}

    public function detect(NormalisedImage $image): HotdogResult
    {
        $response = $this->client->systemOne($this->payload($image));

        $result = HotdogResult::decide(
            band: $this->band,
            confidence: $this->confidence($response),
            box: $this->box($response),
            latencyMs: $response->latencyMs,
            model: $response->model,
        );

        Log::info('hotdog.detected', [
            'verdict' => $result->verdict->value,
            'confidence' => $result->confidence,
            'latency_ms' => $result->latencyMs,
            'model' => $result->model,
            'request_id' => $response->requestId,
        ]);

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(NormalisedImage $image): array
    {
        $reference = (string) Str::uuid();

        return [
            'model' => $this->model,
            'state' => ['photo' => $reference],
            'assets' => [
                $reference => [
                    'type' => 'image',
                    'format' => $image->format(),
                    'data_base64' => $image->base64(),
                ],
            ],
            'questions' => [
                self::NOUL_QUESTION => [
                    'type' => 'noul',
                    'instructions' => 'Does `photo` contain a hot dog (a sausage served in a bun)?',
                ],
                self::BOX_QUESTION => [
                    'type' => 'box',
                    'instructions' => 'the hot dog in `photo`',
                ],
            ],
        ];
    }

    private function confidence(JevResponse $response): float
    {
        $noul = $response->answer(self::NOUL_QUESTION)['noul'] ?? null;

        if (! is_int($noul) && ! is_float($noul)) {
            throw new JevException(JevError::UnexpectedResponse, requestId: $response->requestId);
        }

        return max(0.0, min(1.0, (float) $noul));
    }

    /**
     * A missing or malformed box should not cost the user their verdict.
     */
    private function box(JevResponse $response): ?BoundingBox
    {
        $answer = $response->answers[self::BOX_QUESTION] ?? null;
        $edges = is_array($answer) ? ($answer['box'] ?? null) : null;

        if (! is_array($edges)) {
            return null;
        }

        $confidence = $answer['confidence'] ?? null;

        try {
            return new BoundingBox(
                top: self::edge($edges, 'top'),
                bottom: self::edge($edges, 'bottom'),
                left: self::edge($edges, 'left'),
                right: self::edge($edges, 'right'),
                confidence: is_int($confidence) || is_float($confidence) ? (float) $confidence : null,
            );
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /**
     * @param  array<mixed>  $edges
     */
    private static function edge(array $edges, string $name): float
    {
        $value = $edges[$name] ?? null;

        if (! is_int($value) && ! is_float($value)) {
            throw new InvalidArgumentException("Box edge {$name} is missing.");
        }

        return (float) $value;
    }
}
