<?php

declare(strict_types=1);

namespace App\Http\ProblemDetails;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * An RFC 9457 problem details document.
 *
 * Every problem of this API uses the "about:blank" type, so, as the RFC requires for that type, the title is the
 * HTTP status phrase; the explanation of the specific occurrence goes into the "detail" member.
 */
final readonly class ProblemDetails
{
    public const string CONTENT_TYPE = 'application/problem+json';

    public const string DEFAULT_TYPE = 'about:blank';

    /**
     * @param array<string, mixed> $extensions Additional members, e.g. the "errors" of a validation problem.
     */
    public function __construct(
        public int $status,
        public string $detail,
        public ?string $instance = null,
        public array $extensions = [],
    ) {
    }

    public function title(): string
    {
        return Response::$statusTexts[$this->status] ?? 'Unknown Error';
    }

    public function withExtension(string $name, mixed $value): self
    {
        return new self($this->status, $this->detail, $this->instance, [...$this->extensions, $name => $value]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $members = [
            'type' => self::DEFAULT_TYPE,
            'title' => $this->title(),
            'status' => $this->status,
            'detail' => $this->detail,
        ];

        if ($this->instance !== null) {
            $members['instance'] = $this->instance;
        }

        return [...$members, ...$this->extensions];
    }

    /**
     * The detail and the instance may echo raw request bytes, so invalid UTF-8 is replaced (U+FFFD) instead of
     * making the encoding fail.
     *
     * @param array<string, string|list<string>> $headers Extra headers, e.g. "Allow" of a 405 response.
     */
    public function toResponse(array $headers = []): JsonResponse
    {
        return new JsonResponse(
            $this->toArray(),
            $this->status,
            [...$headers, 'Content-Type' => self::CONTENT_TYPE],
            JSON_INVALID_UTF8_SUBSTITUTE,
        );
    }
}
