<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One request of the /api/* traffic together with its response.
 *
 * @property array<array-key, mixed> $query
 * @property array<string, list<string|null>> $request_headers
 * @property array<string, list<string|null>> $response_headers
 */
#[Fillable([
    'method',
    'path',
    'route',
    'query',
    'request_headers',
    'request_body',
    'response_status',
    'response_headers',
    'response_body',
    'duration_ms',
    'ip',
    'user_agent',
])]
class ApiRequestLog extends Model
{
    /** Log entries are never updated. */
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'query' => 'array',
            'request_headers' => 'array',
            'response_headers' => 'array',
            'response_status' => 'integer',
            'duration_ms' => 'integer',
        ];
    }
}
