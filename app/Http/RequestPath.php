<?php

declare(strict_types=1);

namespace App\Http;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Request::is() that also works for paths with invalid UTF-8 (e.g. /api/v1/%FF).
 *
 * Str::is() matches with a /u regex, which fails on invalid byte sequences, so such a path would match no pattern.
 * The decoded path is turned into valid UTF-8 first; the invalid bytes become "?".
 */
final class RequestPath
{
    public static function matches(Request $request, string ...$patterns): bool
    {
        return Str::is($patterns, mb_scrub($request->decodedPath(), 'UTF-8'));
    }
}
