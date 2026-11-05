<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Logging\ApiTraffic\ApiTrafficLogger;
use App\Logging\ApiTraffic\ApiTrafficRecordFactory;
use Closure;
use Illuminate\Container\Attributes\Config;
use Illuminate\Http\Request;
use Illuminate\Log\Context\Repository as Context;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Logs the API traffic: every request matching the configured paths is stored with its response.
 *
 * Registered as global middleware so that requests to unknown endpoints (404) and with unsupported
 * methods (405) are logged as well. Storing happens in terminate(), after the response has been sent
 * to the client, and a logging failure never breaks the request.
 */
final readonly class LogApiTraffic
{
    private const string DURATION_ATTRIBUTE = 'api_traffic.duration_ms';

    /**
     * @param list<string> $paths
     */
    public function __construct(
        private ApiTrafficLogger $logger,
        private ApiTrafficRecordFactory $records,
        private Context $context,
        #[Config('api.logging.enabled')] private bool $enabled,
        #[Config('api.logging.paths')] private array $paths,
    ) {
    }

    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = hrtime(true);

        $response = $next($request);

        $request->attributes->set(self::DURATION_ATTRIBUTE, (hrtime(true) - $startedAt) / 1_000_000);

        return $response;
    }

    public function terminate(Request $request, Response $response): void
    {
        if (! $this->enabled || ! $request->is(...$this->paths)) {
            return;
        }

        $requestId = $this->context->get(AssignRequestId::CONTEXT_KEY);
        $durationMs = $request->attributes->get(self::DURATION_ATTRIBUTE);

        try {
            $this->logger->log($this->records->make(
                request: $request,
                response: $response,
                requestId: is_string($requestId) ? $requestId : '',
                durationMs: is_float($durationMs) ? $durationMs : 0.0,
            ));
        } catch (Throwable $exception) {
            // Logging must never break the API: the failure is reported and the request carries on.
            report($exception);
        }
    }
}
