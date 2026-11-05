<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Log\Context\Repository as Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gives every request an identifier. It is returned in the `X-Request-Id` response header, added to
 * the log context, included in error responses and stored with the API traffic log entry.
 *
 * A well-formed identifier sent by the client (e.g. by an API gateway) is reused, so a request can be
 * traced across systems; otherwise a time-ordered UUID (v7) is generated.
 */
final readonly class AssignRequestId
{
    public const string HEADER = 'X-Request-Id';

    public const string CONTEXT_KEY = 'request_id';

    private const string ACCEPTED_FORMAT = '/^[A-Za-z0-9._:-]{1,64}$/';

    public function __construct(
        private Context $context,
    ) {
    }

    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $this->resolveRequestId($request);

        $this->context->add(self::CONTEXT_KEY, $requestId);

        $response = $next($request);
        $response->headers->set(self::HEADER, $requestId);

        return $response;
    }

    private function resolveRequestId(Request $request): string
    {
        $incoming = $request->headers->get(self::HEADER);

        if ($incoming !== null && preg_match(self::ACCEPTED_FORMAT, $incoming) === 1) {
            return $incoming;
        }

        return Str::uuid7()->toString();
    }
}
