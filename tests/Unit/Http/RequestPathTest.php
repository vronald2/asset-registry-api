<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Http\RequestPath;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

final class RequestPathTest extends TestCase
{
    public function testMatchesAValidPath(): void
    {
        self::assertTrue(RequestPath::matches(Request::create('/api/v1/devices'), 'api', 'api/*'));
    }

    public function testMatchesAPathWithInvalidUtf8(): void
    {
        $request = Request::create('/api/v1/%FF');

        // Request::is() fails here: Str::is() matches with a /u regex, which rejects invalid UTF-8.
        self::assertFalse($request->is('api/*'));
        self::assertTrue(RequestPath::matches($request, 'api/*'));
    }

    public function testAnyOfThePatternsIsEnough(): void
    {
        self::assertTrue(RequestPath::matches(Request::create('/api'), 'api', 'api/*'));
    }

    public function testDoesNotMatchOtherPaths(): void
    {
        self::assertFalse(RequestPath::matches(Request::create('/docs/api'), 'api', 'api/*'));
        self::assertFalse(RequestPath::matches(Request::create('/apiary'), 'api', 'api/*'));
    }
}
