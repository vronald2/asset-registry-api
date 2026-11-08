<?php

declare(strict_types=1);

namespace App\Logging\ApiTraffic;

use Illuminate\Container\Attributes\Config;

/**
 * Keeps secrets out of the traffic log, keeps the stored bodies within a size limit, and makes every stored value
 * valid UTF-8 (the log columns are utf8mb4, an invalid byte sequence would make the insert fail).
 */
final readonly class PayloadSanitizer
{
    private const int JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    /** @var list<string> */
    private array $maskedHeaders;

    /** @var list<string> */
    private array $maskedFields;

    /**
     * @param list<string> $maskedHeaders Header names (case-insensitive) whose values are masked.
     * @param list<string> $maskedFields Key fragments (case-insensitive): a key containing any of them is masked.
     */
    public function __construct(
        #[Config('api-logging.masked_headers')]
        array $maskedHeaders,
        #[Config('api-logging.masked_fields')]
        array $maskedFields,
        #[Config('api-logging.mask')]
        private string $mask,
        #[Config('api-logging.max_body_bytes')]
        private int $maxBodyBytes,
    ) {
        $this->maskedHeaders = array_map(strtolower(...), $maskedHeaders);
        $this->maskedFields = array_map(strtolower(...), $maskedFields);
    }

    /**
     * @param array<string, list<string|null>> $headers
     *
     * @return array<string, list<string|null>>
     */
    public function headers(array $headers): array
    {
        $sanitized = [];

        foreach ($headers as $name => $values) {
            $sanitized[$name] = in_array(strtolower($name), $this->maskedHeaders, true)
                ? [$this->mask]
                : array_map(fn (?string $value): ?string => $value === null ? null : $this->text($value), $values);
        }

        return $sanitized;
    }

    /**
     * Masks the values of the sensitive keys, at any depth.
     *
     * @param array<array-key, mixed> $data
     *
     * @return array<array-key, mixed>
     */
    public function data(array $data): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            $key = is_string($key) ? $this->text($key) : $key;

            $sanitized[$key] = match (true) {
                is_string($key) && $this->isSensitiveField($key) => $this->mask,
                is_array($value) => $this->data($value),
                is_string($value) => $this->text($value),
                default => $value,
            };
        }

        return $sanitized;
    }

    /**
     * Prepares a body for storage: a JSON body is masked (and re-encoded only if something was masked), every body
     * is truncated to the configured size, and a body of only whitespace counts as empty, like in Laravel.
     * A binary (non-UTF-8) body is replaced by a marker. So is a body that should be JSON but cannot be masked: one
     * that cannot be parsed, and a scalar (e.g. double-encoded JSON), which has no keys to recognize secrets by.
     */
    public function body(string $content, bool $expectJson = false): ?string
    {
        if (trim($content) === '') {
            return null;
        }

        if (! mb_check_encoding($content, 'UTF-8')) {
            return $this->marker('non-UTF-8', $content);
        }

        $decoded = json_decode($content, true);

        if ($expectJson && json_last_error() !== JSON_ERROR_NONE) {
            return $this->marker('unparsable JSON', $content);
        }

        if (is_array($decoded)) {
            $masked = $this->data($decoded);

            if ($masked !== $decoded) {
                $content = (string) json_encode($masked, self::JSON_FLAGS);
            }
        } elseif ($expectJson) {
            return $this->marker('JSON scalar', $content);
        }

        return $this->truncate($content);
    }

    /**
     * Encodes parsed form fields for storage in one pass: masked and made valid UTF-8 first, then truncated.
     *
     * @param array<array-key, mixed> $fields
     */
    public function formBody(array $fields): string
    {
        return $this->truncate((string) json_encode($this->data($fields), self::JSON_FLAGS));
    }

    /**
     * A body that is neither JSON nor form data (e.g. text/plain): its secrets cannot be recognized, so only its size
     * is stored.
     */
    public function opaqueBody(string $content): ?string
    {
        if ($content === '') {
            return null;
        }

        return $this->marker(mb_check_encoding($content, 'UTF-8') ? 'unparsed' : 'non-UTF-8', $content);
    }

    /**
     * Cuts the content to the configured number of bytes (marker included) without splitting a UTF-8 character.
     */
    public function truncate(string $content): string
    {
        $size = strlen($content);

        if ($size <= $this->maxBodyBytes) {
            return $content;
        }

        $marker = sprintf('…[truncated, %d bytes in total]', $size);

        return mb_strcut($content, 0, max(0, $this->maxBodyBytes - strlen($marker)), 'UTF-8') . $marker;
    }

    /**
     * Replaces the invalid UTF-8 byte sequences of a short value (header, query value, user agent) with "?".
     */
    public function text(string $value): string
    {
        return mb_check_encoding($value, 'UTF-8') ? $value : mb_scrub($value, 'UTF-8');
    }

    /**
     * The value stored instead of a body that cannot be stored safely, e.g. "[unparsed body, 16 bytes]".
     */
    private function marker(string $kind, string $content): string
    {
        return sprintf('[%s body, %d bytes]', $kind, strlen($content));
    }

    private function isSensitiveField(string $key): bool
    {
        $key = strtolower($key);

        foreach ($this->maskedFields as $fragment) {
            if (str_contains($key, $fragment)) {
                return true;
            }
        }

        return false;
    }
}
