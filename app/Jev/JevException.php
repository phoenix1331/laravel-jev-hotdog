<?php

declare(strict_types=1);

namespace App\Jev;

use RuntimeException;
use Throwable;

final class JevException extends RuntimeException
{
    public function __construct(
        public readonly JevError $error,
        public readonly ?int $status = null,
        public readonly ?string $requestId = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($error->message(), 0, $previous);
    }
}
