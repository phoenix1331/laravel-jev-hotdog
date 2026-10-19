<?php

declare(strict_types=1);

namespace App\Jev;

final readonly class JevResponse
{
    /**
     * @param  array<string, mixed>  $answers  keyed by the question names sent
     */
    public function __construct(
        public string $model,
        public array $answers,
        public int $latencyMs,
        public ?string $requestId = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function answer(string $question): array
    {
        $answer = $this->answers[$question] ?? null;

        if (! is_array($answer)) {
            throw new JevException(JevError::UnexpectedResponse, requestId: $this->requestId);
        }

        return $answer;
    }
}
