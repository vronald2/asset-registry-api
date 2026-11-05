<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * The client-facing description of an error: what {@see ApiExceptionRenderer} turns an exception into.
 */
final readonly class ApiError
{
    /**
     * @param array<string, list<string>> $errors field errors (validation only)
     * @param array<string, string|list<string>> $headers extra response headers (e.g. Allow, Retry-After)
     */
    public function __construct(
        public int $status,
        public ErrorCode $code,
        public string $message,
        public array $errors = [],
        public array $headers = [],
    ) {
    }
}
