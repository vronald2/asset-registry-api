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
        self::assertNull($this->sanitizer()->body(''));
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
