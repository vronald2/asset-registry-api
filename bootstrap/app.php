<?php

declare(strict_types=1);

use App\Exceptions\ApiException;
use App\Exceptions\ApiExceptionRenderer;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\LogApiTraffic;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Global and outermost, so even early failures (404, 405, 413, maintenance) get a request id
        // and are logged. The traffic logger only stores the paths configured in config/api.php.
        $middleware->prepend([
            AssignRequestId::class,
            LogApiTraffic::class,
        ]);

        $middleware->throttleApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Expected business errors are part of the API contract, not incidents.
        $exceptions->dontReport(ApiException::class);

        // Every error of the API is rendered in the same JSON format, whatever the Accept header is.
        $exceptions->render(
            static fn (Throwable $exception, Request $request): ?JsonResponse => $request->is('api/*')
                ? app(ApiExceptionRenderer::class)->render($exception)
                : null,
        );
    })->create();
