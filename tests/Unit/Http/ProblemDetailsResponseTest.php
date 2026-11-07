<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Http\ProblemDetails\ProblemDetails;
use PHPUnit\Framework\TestCase;

final class ProblemDetailsResponseTest extends TestCase
{
    public function testInvalidUtf8IsSubstitutedInTheResponse(): void
    {
        $problem = new ProblemDetails(404, "The route api/v1/\xFF could not be found.", "/api/v1/\xFF");

        $body = json_decode((string) $problem->toResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);

        self::assertIsArray($body);
        self::assertSame("The route api/v1/\u{FFFD} could not be found.", $body['detail']);
        self::assertSame("/api/v1/\u{FFFD}", $body['instance']);
    }
}
