<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Http\RequestBody;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RequestBodyTest extends TestCase
{
    private const string BOM = "\xEF\xBB\xBF";

    /**
     * @return iterable<string, array{string}>
     */
    public static function bodiesLikeJson(): iterable
    {
        yield 'object' => ['{"name":"X"}'];
        yield 'list' => ['[1]'];
        yield 'broken JSON' => ['{"name":'];
        yield 'leading whitespace' => [" \t\r\n{}"];
        yield 'leading BOM' => [self::BOM . '{}'];
        yield 'BOM and whitespace' => [self::BOM . " \n[1]"];
    }

    #[DataProvider('bodiesLikeJson')]
    public function testRecognizesBodiesThatLookLikeJson(string $content): void
    {
        self::assertTrue(RequestBody::looksLikeJson($content));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function otherBodies(): iterable
    {
        yield 'empty' => [''];
        yield 'only whitespace' => [" \n"];
        yield 'only a BOM' => [self::BOM];
        yield 'JSON string' => ['"x"'];
        yield 'number' => ['123'];
        yield 'form' => ['a=b'];
        yield 'BOM after whitespace' => [' ' . self::BOM . '{}'];
    }

    #[DataProvider('otherBodies')]
    public function testDoesNotRecognizeOtherBodies(string $content): void
    {
        self::assertFalse(RequestBody::looksLikeJson($content));
    }
}
