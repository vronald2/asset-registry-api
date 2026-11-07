<?php

declare(strict_types=1);

use App\Http\Middleware\LogApiTraffic;
use App\Http\Middleware\RejectInvalidUtf8Input;
use App\Http\Middleware\RejectMalformedJson;
use App\Http\ProblemDetails\ProblemDetailsRenderer;
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
        // First in the global stack: it sees every request, including the unknown routes and the error responses.
        $middleware->prepend(LogApiTraffic::class);
        // Malformed input is rejected before the route model binding and the validation of the API routes.
        $middleware->api(prepend: [RejectInvalidUtf8Input::class, RejectMalformedJson::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Every error of the /api/* endpoints is rendered as an RFC 9457 problem details document.
        $exceptions->render(
            static fn (Throwable $exception, Request $request): ?JsonResponse => app(ProblemDetailsRenderer::class)
                ->render($exception, $request),
        );
    })->create();
