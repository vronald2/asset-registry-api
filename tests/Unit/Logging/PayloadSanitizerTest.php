<?php

declare(strict_types=1);

namespace Tests\Unit\Logging;

use App\Logging\ApiTraffic\PayloadSanitizer;
use PHPUnit\Framework\TestCase;

final class PayloadSanitizerTest extends TestCase
{
    public function testMasksSensitiveHeadersCaseInsensitively(): void
    {
        $headers = $this->sanitizer()->headers([
            'authorization' => ['Bearer secret'],
            'cookie' => ['a=1', 'b=2'],
            'accept' => ['application/json'],
        ]);

        self::assertSame(
            ['authorization' => ['***'], 'cookie' => ['***'], 'accept' => ['application/json']],
            $headers,
        );
    }

    public function testMasksSensitiveFieldsAtAnyDepth(): void
    {
        $data = $this->sanitizer()->data([
            'name' => 'Laptop',
            'Password' => 'p',
            'nested' => [
                'api_token' => 't',
                'items' => [['client_secret' => 's', 'serial_number' => 'SN-1']],
            ],
        ]);

        self::assertSame(
            [
                'name' => 'Laptop',
                'Password' => '***',
                'nested' => [
                    'api_token' => '***',
                    'items' => [['client_secret' => '***', 'serial_number' => 'SN-1']],
                ],
            ],
            $data,
        );
    }

    public function testMasksJsonBodies(): void
    {
        self::assertSame(
            '{"name":"Laptop","password":"***"}',
            $this->sanitizer()->body('{"name":"Laptop","password":"secret"}'),
        );
    }

    public function testKeepsBodiesWithoutSensitiveDataUnchanged(): void
    {
        self::assertSame('{ "name": "Laptop" }', $this->sanitizer()->body('{ "name": "Laptop" }'));
        self::assertSame('plain text body', $this->sanitizer()->body('plain text body'));
    }

    public function testStoresEmptyBodyAsNull(): void
    {
        $sanitizer = $this->sanitizer();

        self::assertNull($sanitizer->body(''));
        // Like in Laravel, a body of only whitespace counts as empty.
        self::assertNull($sanitizer->body(" \n", expectJson: true));
    }

    public function testTruncatesOversizedBodiesWithMarker(): void
    {
        $body = $this->sanitizer(maxBodyBytes: 100)->body(str_repeat('a', 500));

        self::assertNotNull($body);
        self::assertLessThanOrEqual(100, strlen($body));
        self::assertStringStartsWith('aaaa', $body);
        self::assertStringEndsWith('…[truncated, 500 bytes in total]', $body);
    }

    public function testKeepsBodyAtTheExactLimit(): void
    {
        $content = str_repeat('a', 100);

        self::assertSame($content, $this->sanitizer(maxBodyBytes: 100)->body($content));
    }

    public function testDoesNotSplitMultibyteCharacters(): void
    {
        // "ő" takes two bytes: an odd cut position must not leave half a character behind.
        $body = $this->sanitizer(maxBodyBytes: 61)->body(str_repeat('ő', 100));

        self::assertNotNull($body);
        self::assertTrue(mb_check_encoding($body, 'UTF-8'));
    }

    public function testReplacesUnparsableJsonWithMarker(): void
    {
        $sanitizer = $this->sanitizer();

        self::assertSame(
            '[unparsable JSON body, 32 bytes]',
            $sanitizer->body('{"name":"x","password":"hunter2"', expectJson: true),
        );
        self::assertSame(
            '{"name":"x","password":"***"}',
            $sanitizer->body('{"name":"x","password":"hunter2"}', expectJson: true),
        );
        // Without the JSON expectation a plain text body is kept as it is.
        self::assertSame('plain text body', $sanitizer->body('plain text body'));
    }

    public function testReplacesJsonScalarWithMarker(): void
    {
        $sanitizer = $this->sanitizer();

        // A scalar has no keys to recognize a secret by, e.g. in double-encoded JSON.
        self::assertSame(
            '[JSON scalar body, 28 bytes]',
            $sanitizer->body('"{\"password\":\"hunter2\"}"', expectJson: true),
        );
        self::assertSame('[JSON scalar body, 3 bytes]', $sanitizer->body('123', expectJson: true));
        // Without the JSON expectation (a response body) the content is kept.
        self::assertSame('"text"', $sanitizer->body('"text"'));
    }

    public function testFormBodyIsMaskedScrubbedAndTruncated(): void
    {
        self::assertSame(
            '{"name":"Laptop?","password":"***"}',
            $this->sanitizer()->formBody(['name' => "Laptop\xFF", 'password' => 'secret']),
        );

        $truncated = $this->sanitizer(maxBodyBytes: 60)->formBody(['description' => str_repeat('a', 200)]);

        self::assertLessThanOrEqual(60, strlen($truncated));
        self::assertStringContainsString('[truncated,', $truncated);
    }

    public function testOpaqueBodyKeepsOnlyTheSize(): void
    {
        $sanitizer = $this->sanitizer();

        self::assertNull($sanitizer->opaqueBody(''));
        // Like in body(), a body of only whitespace counts as empty.
        self::assertNull($sanitizer->opaqueBody("\n"));
        self::assertNull($sanitizer->opaqueBody(" \t"));
        self::assertSame('[unparsed body, 16 bytes]', $sanitizer->opaqueBody('password=hunter2'));
        self::assertSame('[non-UTF-8 body, 2 bytes]', $sanitizer->opaqueBody("\xFF\xFE"));
    }

    public function testReplacesNonUtf8BodyWithMarker(): void
    {
        self::assertSame('[non-UTF-8 body, 4 bytes]', $this->sanitizer()->body("\xFF\xFE\x00\x01"));
    }

    public function testScrubsInvalidUtf8FromHeadersAndData(): void
    {
        $sanitizer = $this->sanitizer();

        self::assertSame(['user-agent' => ['Agent?']], $sanitizer->headers(['user-agent' => ["Agent\xFF"]]));
        self::assertSame(['q' => 'a?b', 'list' => ['?']], $sanitizer->data(['q' => "a\xFFb", 'list' => ["\xFE"]]));
        self::assertSame('Agent?', $sanitizer->text("Agent\xFF"));
    }

    private function sanitizer(int $maxBodyBytes = 1024): PayloadSanitizer
    {
        return new PayloadSanitizer(
            maskedHeaders: ['Authorization', 'cookie'],
            maskedFields: ['password', 'TOKEN', 'secret'],
            mask: '***',
            maxBodyBytes: $maxBodyBytes,
        );
    }
}
