<?php

declare(strict_types=1);

namespace App\Logging\ApiTraffic;

/**
 * One request/response pair of the API traffic, already masked and truncated, ready to be stored.
 */
final readonly class ApiTrafficRecord
{
    /**
     * @param array<array-key, mixed> $requestHeaders
     * @param array<array-key, mixed> $responseHeaders
     */
    public function __construct(
        public string $requestId,
        public string $method,
        public string $url,
        public ?string $route,
        public ?string $ipAddress,
        public ?string $userAgent,
        public array $requestHeaders,
        public ?string $requestBody,
        public int $responseStatus,
        public array $responseHeaders,
        public ?string $responseBody,
        public float $durationMs,
    ) {
    }
}
