<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Rejects an API request whose query or form parameters (keys or values, at any depth) are not valid UTF-8.
 *
 * Such input can neither be validated meaningfully nor stored in the utf8mb4 columns. Without this check it reaches
 * the database and fails there with a 500. The error handler renders the exception as a 400 problem details
 * response. For a JSON request the request bag holds the decoded JSON, which is always valid UTF-8 (json_decode()
 * fails on anything else), so checking it is harmless.
 */
final readonly class RejectInvalidUtf8Input
{
    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->isValidUtf8($request->query->all()) || ! $this->isValidUtf8($request->request->all())) {
            throw new BadRequestHttpException('The request contains invalid UTF-8 data.');
        }

        return $next($request);
    }

    /**
     * @param array<array-key, mixed> $parameters
     */
    private function isValidUtf8(array $parameters): bool
    {
        foreach ($parameters as $key => $value) {
            $isValid = match (true) {
                is_string($key) && ! mb_check_encoding($key, 'UTF-8') => false,
                is_string($value) => mb_check_encoding($value, 'UTF-8'),
                is_array($value) => $this->isValidUtf8($value),
                default => true,
            };

            if (! $isValid) {
                return false;
            }
        }

        return true;
    }
}
