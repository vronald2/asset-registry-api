<?php

declare(strict_types=1);

namespace App\Logging\ApiTraffic;

/**
 * A sanitized request/response pair, ready to be stored.
 */
final readonly class ApiTrafficEntry
{
    /**
     * @param array<array-key, mixed> $query
     * @param array<string, list<string|null>> $requestHeaders
     * @param array<string, list<string|null>> $responseHeaders
     */
    public function __construct(
        public string $method,
        public string $path,
        public ?string $route,
        public array $query,
        public array $requestHeaders,
        public ?string $requestBody,
        public int $responseStatus,
        public array $responseHeaders,
        public ?string $responseBody,
        public int $durationMs,
        public ?string $ip,
        public ?string $userAgent,
    ) {
    }
}
