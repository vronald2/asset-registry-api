<?php

declare(strict_types=1);

namespace App\Logging\ApiTraffic;

use App\Models\ApiRequestLog;

/**
 * Writes the API traffic log entries into the api_request_logs table.
 */
final readonly class DatabaseApiTrafficLogger implements ApiTrafficLogger
{
    public function log(ApiTrafficEntry $entry): void
    {
        ApiRequestLog::query()->create([
            'method' => $entry->method,
            'path' => $entry->path,
            'route' => $entry->route,
            'query' => $entry->query,
            'request_headers' => $entry->requestHeaders,
            'request_body' => $entry->requestBody,
            'response_status' => $entry->responseStatus,
            'response_headers' => $entry->responseHeaders,
            'response_body' => $entry->responseBody,
            'duration_ms' => $entry->durationMs,
            'ip' => $entry->ip,
            'user_agent' => $entry->userAgent,
        ]);
    }
}
