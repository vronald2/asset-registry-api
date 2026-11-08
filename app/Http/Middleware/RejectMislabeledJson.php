<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\RequestBody;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;

/**
 * Rejects an API write request whose body looks like JSON but is not declared as JSON, typically because of a missing
 * Content-Type header ("curl -d" sends a form by default).
 *
 * PHP reads such a body as one form field that no validation rule looks at: a POST would fail with misleading "field
 * is required" errors, and a PATCH, whose rules all use "sometimes", would even "succeed" without changing anything.
 * The error handler renders the exception as a 415 problem details response.
 */
final readonly class RejectMislabeledJson
{
    private const array WRITE_METHODS = ['POST', 'PUT', 'PATCH'];

    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (
            ! $request->isJson()
            && in_array($request->getMethod(), self::WRITE_METHODS, true)
            && RequestBody::looksLikeJson($request->getContent())
        ) {
            throw new UnsupportedMediaTypeHttpException(
                'The request body looks like JSON: send it with the Content-Type: application/json header.',
            );
        }

        return $next($request);
    }
}
