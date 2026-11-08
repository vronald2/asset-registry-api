<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Recognizes JSON by the way a request body starts, whatever its Content-Type says.
 */
final class RequestBody
{
    private const string UTF8_BOM = "\xEF\xBB\xBF";

    private const string JSON_WHITESPACE = " \t\n\r";

    /**
     * Whether the body starts like a JSON object or list, after an optional UTF-8 BOM and whitespace. The rest of the
     * body is not looked at, so this also holds for broken JSON.
     */
    public static function looksLikeJson(string $content): bool
    {
        $start = str_starts_with($content, self::UTF8_BOM) ? strlen(self::UTF8_BOM) : 0;
        $start += strspn($content, self::JSON_WHITESPACE, $start);

        return in_array(substr($content, $start, 1), ['{', '['], true);
    }
}
