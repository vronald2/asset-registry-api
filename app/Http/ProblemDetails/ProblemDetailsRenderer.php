<?php

declare(strict_types=1);

namespace App\Http\ProblemDetails;

use App\Http\RequestPath;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Renders the errors of the /api/* endpoints as problem details, regardless of the request's Accept header.
 * Other routes keep Laravel's default error rendering.
 */
final readonly class ProblemDetailsRenderer
{
    public function __construct(private ProblemDetailsFactory $factory)
    {
    }

    public function render(Throwable $exception, Request $request): ?JsonResponse
    {
        // An HttpResponseException already carries the response that has to be sent.
        if (! RequestPath::matches($request, 'api', 'api/*') || $exception instanceof HttpResponseException) {
            return null;
        }

        return $this->factory
            ->fromThrowable($exception, '/' . $request->path())
            ->toResponse($this->factory->headersFor($exception));
    }
}
