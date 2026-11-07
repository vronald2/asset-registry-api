<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\RequestPath;
use App\Logging\ApiTraffic\ApiTrafficEntryFactory;
use App\Logging\ApiTraffic\ApiTrafficLogger;
use Closure;
use Illuminate\Container\Attributes\Config;
use Illuminate\Http\Request;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Logs every /api/* request together with its response.
 *
 * It is the first global middleware, so it also sees the requests of unknown routes and the rendered error
 * responses. The entry is written in terminate(), after the response has been sent: logging neither slows down nor
 * breaks the API response, a failure only ends up in the application log.
 */
final readonly class LogApiTraffic
{
    private const string STARTED_AT = 'api_traffic_log.started_at';

    /**
     * @param list<string> $paths Request::is() patterns of the logged requests.
     */
    public function __construct(
        private ApiTrafficEntryFactory $entries,
        private ApiTrafficLogger $logger,
        private LoggerInterface $applicationLog,
        #[Config('api-logging.enabled')]
        private bool $enabled,
        #[Config('api-logging.paths')]
        private array $paths,
    ) {
    }

    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Laravel calls terminate() on a new instance, so the start time travels with the request.
        $request->attributes->set(self::STARTED_AT, hrtime(true));

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if (! $this->enabled || ! RequestPath::matches($request, ...$this->paths)) {
            return;
        }

        try {
            $this->logger->log($this->entries->make($request, $response, $this->durationMs($request)));
        } catch (Throwable $exception) {
            $this->applicationLog->error('Failed to log the API traffic.', ['exception' => $exception]);
        }
    }

    private function durationMs(Request $request): int
    {
        $startedAt = $request->attributes->get(self::STARTED_AT);

        if (! is_int($startedAt)) {
            return 0;
        }

        return (int) round((hrtime(true) - $startedAt) / 1_000_000);
    }
}
