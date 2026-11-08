<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use stdClass;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Rejects an API request that declares a JSON body but sends neither a JSON object nor an empty body: invalid JSON
 * (invalid UTF-8 included), or another JSON value, such as double-encoded JSON or a list. Like in Laravel,
 * whitespace, null and [] (how PHP encodes an empty payload) count as empty.
 *
 * Laravel reads such a body as no usable input: a POST would fail validation with misleading "field is required"
 * errors, and a PATCH, whose rules all use "sometimes", would even "succeed" without changing anything. The error
 * handler renders the exception as a 400 problem details response.
 */
final readonly class RejectMalformedJson
{
    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isJson()) {
            return $next($request);
        }

        $content = $request->getContent();

        if (trim($content) === '') {
            return $next($request);
        }

        $data = json_decode($content);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new BadRequestHttpException('The request body is not valid JSON.');
        }

        if (! $data instanceof stdClass && $data !== null && $data !== []) {
            throw new BadRequestHttpException('The request body must be a JSON object.');
        }

        return $next($request);
    }
}
