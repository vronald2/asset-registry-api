<?php

declare(strict_types=1);

namespace App\Providers;

use App\Logging\ApiTraffic\ApiTrafficLogger;
use App\Logging\ApiTraffic\DatabaseApiTrafficLogger;
use Dedoc\Scramble\Scramble;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        ApiTrafficLogger::class => DatabaseApiTrafficLogger::class,
    ];

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Outside production, fail loudly on lazy loading, unfillable attributes and missing attributes.
        Model::shouldBeStrict(! $this->app->isProduction());

        // Document every method of a route, so that "PUT|PATCH" appears as two operations (HEAD mirrors GET).
        Scramble::configure()->resolveOperationMethodsUsing(
            static fn (Route $route): array => array_values(array_diff(
                array_map(strtolower(...), $route->methods()),
                ['head'],
            )),
        );
    }
}
