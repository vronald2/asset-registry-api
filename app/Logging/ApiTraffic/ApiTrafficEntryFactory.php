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
    /** Width of the method column; an overridden method (X-HTTP-Method-Override) can be longer. */
    private const int MAX_METHOD_LENGTH = 10;

    private const int MAX_PATH_LENGTH = 2048;

    private const int MAX_ROUTE_LENGTH = 255;

    private const int MAX_USER_AGENT_LENGTH = 512;

    /** A plain field name, optionally followed by the [key] or [] parts of a nested field. */
    private const string FORM_FIELD_NAME = '/^[A-Za-z0-9_.-]+(?:\[[A-Za-z0-9_.-]*\])*$/D';

    public function __construct(private PayloadSanitizer $sanitizer)
    {
    }

    public function make(Request $request, Response $response, int $durationMs): ApiTrafficEntry
    {
        $userAgent = $request->userAgent();

        return new ApiTrafficEntry(
            method: mb_substr($request->getMethod(), 0, self::MAX_METHOD_LENGTH),
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

    /**
     * Only content whose secrets can be recognized is stored: JSON and form fields, both masked. Any other body is
     * reduced to its size.
     */
    private function requestBody(Request $request): ?string
    {
        $content = $request->getContent();

        // JSON is also recognized by its first character, whatever the Content-Type: "curl -d" sends it as a form,
        // and PHP reads the whole body, secrets included, as one form key. Invalid JSON is replaced by a marker.
        if ($request->isJson() || $this->startsLikeJson($content)) {
            return $this->sanitizer->body($content, expectJson: true);
        }

        $formFields = $request->request->all();

        if ($formFields !== [] && $this->isFormEncoded($content)) {
            return $this->sanitizer->formBody($formFields);
        }

        return $this->sanitizer->opaqueBody($content);
    }

    private function startsLikeJson(string $content): bool
    {
        $content = ltrim($content);

        return str_starts_with($content, '{') || str_starts_with($content, '[');
    }

    /**
     * Whether every pair of the raw body is a name=value pair with a regular field name. PHP also reads other text
     * sent as a form (e.g. "password: secret" or "Authorization: Basic abc=") as a field name, which would reach the
     * log unmasked. The raw name is checked because PHP turns the spaces of a parsed name into underscores. A
     * multipart body has no raw content here: PHP has already parsed it.
     */
    private function isFormEncoded(string $content): bool
    {
        foreach (explode('&', $content) as $pair) {
            if ($pair !== '' && ! $this->isFormField($pair)) {
                return false;
            }
        }

        return true;
    }

    private function isFormField(string $pair): bool
    {
        $nameLength = strpos($pair, '=');

        return $nameLength !== false
            && preg_match(self::FORM_FIELD_NAME, urldecode(substr($pair, 0, $nameLength))) === 1;
    }

    private function responseBody(Response $response): ?string
    {
        // Streamed and file responses have no content in memory.
        $content = $response->getContent();

        return $content === false ? null : $this->sanitizer->body($content);
    }
}
