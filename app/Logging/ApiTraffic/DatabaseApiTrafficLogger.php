<?php

declare(strict_types=1);

namespace App\Logging\ApiTraffic;

use App\Models\ApiRequestLog;

/**
 * Stores the API traffic in the `api_request_logs` table.
 */
final readonly class DatabaseApiTrafficLogger implements ApiTrafficLogger
{
    public function log(ApiTrafficRecord $record): void
    {
        ApiRequestLog::query()->create([
            'request_id' => $record->requestId,
            'method' => $record->method,
            'url' => $record->url,
            'route' => $record->route,
            'ip_address' => $record->ipAddress,
            'user_agent' => $record->userAgent,
            'request_headers' => $record->requestHeaders,
            'request_body' => $record->requestBody,
            'response_status' => $record->responseStatus,
            'response_headers' => $record->responseHeaders,
            'response_body' => $record->responseBody,
            'duration_ms' => $record->durationMs,
        ]);
    }
}
