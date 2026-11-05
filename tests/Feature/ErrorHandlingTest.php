<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

final class ErrorHandlingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::get('api/v1/test/failure', static fn () => throw new RuntimeException('Sensitive internal detail'));
    }

    #[Test]
    public function rendersUnknownEndpointsAsJsonWhateverTheAcceptHeaderIs(): void
    {
        $this->get('/api/v1/unknown', ['Accept' => 'text/html'])
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/json')
            ->assertExactJsonStructure(['message', 'code', 'request_id'])
            ->assertJsonPath('code', 'route_not_found');
    }

    #[Test]
    public function rendersUnsupportedMethodsWithTheAllowedOnes(): void
    {
        $this->deleteJson('/api/v1/devices')
            ->assertMethodNotAllowed()
            ->assertHeader('Allow', 'GET, HEAD, POST')
            ->assertJsonPath('code', 'method_not_allowed');
    }

    #[Test]
    public function hidesTheDetailsOfUnexpectedErrors(): void
    {
        config(['app.debug' => false]);

        $response = $this->getJson('/api/v1/test/failure');

        $response->assertInternalServerError()
            ->assertExactJsonStructure(['message', 'code', 'request_id'])
            ->assertJsonPath('code', 'internal_server_error')
            ->assertJsonPath('message', 'An unexpected error occurred.');
        $this->assertStringNotContainsString('Sensitive internal detail', (string) $response->getContent());
    }

    #[Test]
    public function addsDebugDetailsToUnexpectedErrorsInDebugMode(): void
    {
        config(['app.debug' => true]);

        $this->getJson('/api/v1/test/failure')
            ->assertInternalServerError()
            ->assertJsonPath('debug.exception', RuntimeException::class)
            ->assertJsonPath('debug.message', 'Sensitive internal detail');
    }

    #[Test]
    public function returnsTheRequestIdInTheHeaderAndTheErrorBody(): void
    {
        $response = $this->getJson('/api/v1/devices/999')->assertNotFound();

        $requestId = (string) $response->headers->get('X-Request-Id');
        $this->assertTrue(Str::isUuid($requestId));
        $response->assertJsonPath('request_id', $requestId);
    }

    #[Test]
    public function reusesAWellFormedRequestIdOfTheClient(): void
    {
        $this->getJson('/api/v1/devices', ['X-Request-Id' => 'gateway-trace-123'])
            ->assertOk()
            ->assertHeader('X-Request-Id', 'gateway-trace-123');
    }

    #[Test]
    public function replacesAMalformedRequestIdOfTheClient(): void
    {
        $response = $this->getJson('/api/v1/devices', ['X-Request-Id' => 'not valid <script>']);

        $this->assertTrue(Str::isUuid((string) $response->headers->get('X-Request-Id')));
    }

    #[Test]
    public function limitsTheRequestRate(): void
    {
        config(['api.rate_limit.per_minute' => 2]);

        $this->getJson('/api/v1/devices')->assertOk();
        $this->getJson('/api/v1/devices')->assertOk();

        $this->getJson('/api/v1/devices')
            ->assertTooManyRequests()
            ->assertHeader('Retry-After')
            ->assertJsonPath('code', 'too_many_requests');
    }
}
