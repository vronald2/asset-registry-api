<?php

declare(strict_types=1);

namespace App\Logging\ApiTraffic;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Symfony\Component\HttpFoundation\Response;

/**
 * Builds the sanitized log entry of a request and its response.
 */
final readonly class ApiTrafficEntryFactory
{
    private const int MAX_PATH_LENGTH = 2048;

    private const int MAX_ROUTE_LENGTH = 255;

    private const int MAX_USER_AGENT_LENGTH = 512;

    public function __construct(private PayloadSanitizer $sanitizer)
    {
    }

    public function make(Request $request, Response $response, int $durationMs): ApiTrafficEntry
    {
        $userAgent = $request->userAgent();

        return new ApiTrafficEntry(
            method: $request->getMethod(),
            path: mb_substr($this->sanitizer->text('/' . $request->path()), 0, self::MAX_PATH_LENGTH),
            route: $this->routeUri($request),
            query: $this->sanitizer->data($request->query->all()),
            requestHeaders: $this->sanitizer->headers($request->headers->all()),
            requestBody: $this->requestBody($request),
            responseStatus: $response->getStatusCode(),
            responseHeaders: $this->sanitizer->headers($response->headers->all()),
            responseBody: $this->responseBody($response),
            durationMs: $durationMs,
            ip: $request->ip(),
            userAgent: $userAgent === null
                ? null
                : mb_substr($this->sanitizer->text($userAgent), 0, self::MAX_USER_AGENT_LENGTH),
        );
    }

    private function routeUri(Request $request): ?string
    {
        $route = $request->route();

        return $route instanceof Route ? mb_substr('/' . $route->uri(), 0, self::MAX_ROUTE_LENGTH) : null;
    }

    private function requestBody(Request $request): ?string
    {
        // A form submission is logged by its parsed fields, so that the sensitive ones can be masked too.
        $formFields = $request->isJson() ? [] : $request->request->all();

        if ($formFields !== []) {
            return $this->sanitizer->body((string) json_encode($formFields, JSON_UNESCAPED_UNICODE));
        }

        return $this->sanitizer->body($request->getContent());
    }

    private function responseBody(Response $response): ?string
    {
        // Streamed and file responses have no content in memory.
        $content = $response->getContent();

        return $content === false ? null : $this->sanitizer->body($content);
    }
}
