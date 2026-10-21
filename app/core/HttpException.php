<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class HttpException extends RuntimeException
{
    /** @param array<string, string> $headers */
    public function __construct(
        private int $statusCode,
        string $message = '',
        private array $headers = [],
        private ?string $errorCode = null
    ) {
        parent::__construct($message);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    /** @return array<string, string> */
    public function headers(): array
    {
        return $this->headers;
    }

    public function errorCode(): ?string
    {
        return $this->errorCode;
    }
}
