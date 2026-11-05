<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;

/**
 * One API request/response pair. Entries are append-only and pruned after the retention period
 * (`php artisan model:prune`, scheduled daily).
 *
 * @property int $id
 * @property string $request_id
 * @property string $method
 * @property string $url
 * @property string|null $route
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property array<string, mixed> $request_headers
 * @property string|null $request_body
 * @property int $response_status
 * @property array<string, mixed> $response_headers
 * @property string|null $response_body
 * @property float $duration_ms
 * @property CarbonImmutable|null $created_at
 */
#[Fillable([
    'request_id',
    'method',
    'url',
    'route',
    'ip_address',
    'user_agent',
    'request_headers',
    'request_body',
    'response_status',
    'response_headers',
    'response_body',
    'duration_ms',
])]
final class ApiRequestLog extends Model
{
    use MassPrunable;

    public const UPDATED_AT = null;

    /**
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        $retentionDays = Config::integer('api.logging.retention_days');

        return self::query()->where('created_at', '<', now()->subDays($retentionDays));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'request_headers' => 'array',
            'response_headers' => 'array',
            'response_status' => 'integer',
            'duration_ms' => 'float',
        ];
    }
}
