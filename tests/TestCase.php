<?php

declare(strict_types=1);

namespace Tests;

use App\Http\ProblemDetails\ProblemDetails;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;

abstract class TestCase extends BaseTestCase
{
    /**
     * Asserts that the response is an RFC 9457 problem details document with the given HTTP status.
     *
     * @template TResponse of Response
     *
     * @param TestResponse<TResponse> $response
     */
    protected function assertProblemDetails(TestResponse $response, int $status): void
    {
        $response->assertStatus($status);
        $response->assertHeader('Content-Type', ProblemDetails::CONTENT_TYPE);
        $response->assertJsonStructure(['type', 'title', 'status', 'detail', 'instance']);
        $response->assertJsonPath('type', ProblemDetails::DEFAULT_TYPE);
        $response->assertJsonPath('title', Response::$statusTexts[$status]);
        $response->assertJsonPath('status', $status);
    }
}
