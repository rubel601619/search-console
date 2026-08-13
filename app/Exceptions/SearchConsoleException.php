<?php

namespace App\Exceptions;

use RuntimeException;

class SearchConsoleException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly string $errorCode = 'search_console_error',
        private readonly int $statusCode = 422,
        private readonly bool $retryable = false,
    ) {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function retryable(): bool
    {
        return $this->retryable;
    }
}
