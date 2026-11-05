<?php

declare(strict_types=1);

namespace App\Logging\ApiTraffic;

use Illuminate\Container\Attributes\Config;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Extracts what is worth logging from a request/response pair: sensitive values are masked,
 * bodies are truncated.
 */
final readonly class ApiTrafficRecordFactory
{
    private const string TRUNCATION_MARKER = '...[truncated]';

    private const int JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;

    public function __construct(
        private SensitiveDataMasker $masker,
        #[Config('api.logging.max_body_length')] private int $maxBodyLength,
    ) {
    }

    public function make(Request $request, Response $response, string $requestId, float $durationMs): ApiTrafficRecord
    {
        $route = $request->route();

        return new ApiTrafficRecord(
            requestId: $requestId,
            method: $request->getMethod(),
            url: $this->maskedUrl($request),
            route: $route instanceof Route ? $route->uri() : null,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            requestHeaders: $this->masker->mask($request->headers->all()),
            // The raw body, as sent by the client (before input normalisation such as trimming).
            requestBody: $this->body($request->getContent(), $request->headers->get('Content-Type')),
            responseStatus: $response->getStatusCode(),
            responseHeaders: $this->masker->mask($response->headers->all()),
            responseBody: $this->body($response->getContent(), $response->headers->get('Content-Type')),
            durationMs: round($durationMs, 2),
        );
    }

    /**
     * The full URL with masked query parameters; the query string is decoded for readability.
     */
    private function maskedUrl(Request $request): string
    {
        $query = $this->masker->mask($request->query->all());

        return $query === [] ? $request->url() : $request->url() . '?' . urldecode(http_build_query($query));
    }

    /**
     * JSON and form bodies are masked; any other body is stored as it is. Long bodies are truncated.
     */
    private function body(string|false $content, ?string $contentType): ?string
    {
        if ($content === false || $content === '') {
            return null;
        }

        return Str::limit($this->maskBody($content, $contentType), $this->maxBodyLength, self::TRUNCATION_MARKER);
    }

    private function maskBody(string $content, ?string $contentType): string
    {
        if (json_validate($content)) {
            $decoded = json_decode($content, true, flags: JSON_THROW_ON_ERROR);

            return is_array($decoded) ? json_encode($this->masker->mask($decoded), self::JSON_FLAGS) : $content;
        }

        if ($contentType !== null && str_starts_with($contentType, 'application/x-www-form-urlencoded')) {
            parse_str($content, $fields);

            return http_build_query($this->masker->mask($fields));
        }

        return $content;
    }
}
