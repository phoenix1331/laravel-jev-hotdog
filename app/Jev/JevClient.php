<?php

declare(strict_types=1);

namespace App\Jev;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * A thin client for TypeSafe's System One API, kept to the two calls this
 * app makes. Image input is a preview feature, so every call opts in with
 * the TypeSafe-Danger-Zone-Images header.
 */
final readonly class JevClient
{
    public const IMAGES_HEADER = 'TypeSafe-Danger-Zone-Images';

    public const REQUEST_ID_HEADER = 'x-typesafe-request-id';

    /** Rate limited or overloaded: worth a short, bounded retry. */
    private const RETRYABLE = [429, 529];

    /** @var list<int> milliseconds to wait before each retry */
    private const BACKOFF = [250, 750];

    public function __construct(
        private ?string $apiKey,
        private string $baseUrl,
        private int $timeoutSeconds = 30,
    ) {}

    /**
     * @param  array<string, mixed>  $payload  a SystemOneRequest body
     */
    public function systemOne(array $payload): JevResponse
    {
        $started = hrtime(true);

        $response = $this->send(fn (PendingRequest $http): Response => $http->post('systemone', $payload));
        $body = $response->json();

        if (! is_array($body) || ! is_string($body['model'] ?? null) || ! is_array($body['answers'] ?? null)) {
            throw new JevException(JevError::UnexpectedResponse, $response->status(), self::requestId($response));
        }

        return new JevResponse(
            model: $body['model'],
            answers: $body['answers'],
            latencyMs: (int) round((hrtime(true) - $started) / 1_000_000),
            requestId: self::requestId($response),
        );
    }

    /**
     * Model names and aliases the key can use.
     *
     * @return list<string>
     */
    public function models(): array
    {
        $response = $this->send(fn (PendingRequest $http): Response => $http->get('models'));
        $models = $response->json('models');

        if (! is_array($models)) {
            throw new JevException(JevError::UnexpectedResponse, $response->status(), self::requestId($response));
        }

        return array_values(array_filter(
            array_map(static fn (mixed $model): mixed => is_array($model) ? ($model['name'] ?? null) : null, $models),
            'is_string',
        ));
    }

    /**
     * @param  callable(PendingRequest): Response  $call
     */
    private function send(callable $call): Response
    {
        if ($this->apiKey === null || trim($this->apiKey) === '') {
            throw new JevException(JevError::MissingKey);
        }

        $http = Http::baseUrl(rtrim($this->baseUrl, '/').'/')
            ->withToken($this->apiKey)
            ->withHeaders([self::IMAGES_HEADER => 'true'])
            ->acceptJson()
            ->asJson()
            ->timeout($this->timeoutSeconds)
            ->retry(
                self::BACKOFF,
                when: static fn (Throwable $e): bool => $e instanceof RequestException
                    && in_array($e->response->status(), self::RETRYABLE, true),
                throw: false,
            );

        try {
            $response = $call($http);
        } catch (ConnectionException $e) {
            $error = str_contains($e->getMessage(), 'timed out') || str_contains($e->getMessage(), 'cURL error 28')
                ? JevError::Timeout
                : JevError::Unreachable;

            throw new JevException($error, previous: $e);
        }

        if ($response->failed()) {
            throw new JevException(JevError::fromStatus($response->status()), $response->status(), self::requestId($response));
        }

        return $response;
    }

    private static function requestId(Response $response): ?string
    {
        $id = $response->header(self::REQUEST_ID_HEADER);

        return $id === '' ? null : $id;
    }
}
