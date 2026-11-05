<?php

declare(strict_types=1);

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Date::use(CarbonImmutable::class);

        // Outside production: fail fast on lazy loading (N+1), unknown and missing attributes.
        Model::shouldBeStrict(! $this->app->isProduction());

        DB::prohibitDestructiveCommands($this->app->isProduction());

        RateLimiter::for(
            'api',
            static fn (Request $request): Limit => Limit::perMinute(Config::integer('api.rate_limit.per_minute'))
                ->by((string) $request->ip()),
        );
    }
}
