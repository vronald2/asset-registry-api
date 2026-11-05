<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Logging\ApiTraffic\ApiTrafficLogger;
use App\Logging\ApiTraffic\ApiTrafficRecord;
use App\Models\ApiRequestLog;
use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

final class ApiTrafficLoggingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function logsTheRequestAndTheResponse(): void
    {
        $response = $this->postJson(
            '/api/v1/devices',
            ['name' => 'Latitude 5540', 'manufacturer' => 'Dell', 'category' => 'laptop'],
            ['User-Agent' => 'TestClient/1.0'],
        );

        $log = ApiRequestLog::query()->sole();
        $this->assertSame($response->headers->get('X-Request-Id'), $log->request_id);
        $this->assertSame('POST', $log->method);
        $this->assertSame('http://localhost/api/v1/devices', $log->url);
        $this->assertSame('api/v1/devices', $log->route);
        $this->assertSame('127.0.0.1', $log->ip_address);
        $this->assertSame('TestClient/1.0', $log->user_agent);
        $this->assertSame(201, $log->response_status);
        $this->assertGreaterThan(0, $log->duration_ms);
        $this->assertSame(['application/json'], $log->request_headers['content-type']);
        $this->assertSame(['application/json'], $log->response_headers['content-type']);
        $this->assertSame('Latitude 5540', $this->decode($log->request_body)['name']);
        $this->assertSame($response->json('data'), $this->decode($log->response_body)['data']);
    }

    #[Test]
    public function masksSensitiveValues(): void
    {
        $this->postJson(
            '/api/v1/devices?token=query-secret',
            [
                'name' => 'Latitude 5540',
                'manufacturer' => 'Dell',
                'category' => 'laptop',
                'password' => 'body-secret',
                'meta' => ['Access_Token' => 'nested-secret'],
            ],
            ['Authorization' => 'Bearer header-secret'],
        )->assertCreated();

        $log = ApiRequestLog::query()->sole();
        $stored = (string) json_encode($log->getAttributes());

        foreach (['query-secret', 'body-secret', 'nested-secret', 'header-secret'] as $secret) {
            $this->assertStringNotContainsString($secret, $stored);
        }

        $this->assertSame('http://localhost/api/v1/devices?token=********', $log->url);
        $this->assertSame('********', $log->request_headers['authorization']);
        $this->assertSame('********', $this->decode($log->request_body)['password']);
        $this->assertSame('Dell', $this->decode($log->request_body)['manufacturer']);
    }

    #[Test]
    public function logsFailedRequestsToUnknownEndpointsToo(): void
    {
        $this->getJson('/api/v1/unknown')->assertNotFound();

        $log = ApiRequestLog::query()->sole();
        $this->assertSame(404, $log->response_status);
        $this->assertNull($log->route);
        $this->assertSame('route_not_found', $this->decode($log->response_body)['code']);
    }

    #[Test]
    public function doesNotLogRequestsOutsideTheApi(): void
    {
        $this->get('/docs/api')->assertUnauthorized();
        $this->get('/up')->assertOk();

        $this->assertDatabaseCount('api_request_logs', 0);
    }

    #[Test]
    public function canBeDisabled(): void
    {
        config(['api.logging.enabled' => false]);

        $this->getJson('/api/v1/devices')->assertOk();

        $this->assertDatabaseCount('api_request_logs', 0);
    }

    #[Test]
    public function truncatesLongBodies(): void
    {
        config(['api.logging.max_body_length' => 40]);
        Device::factory()->count(5)->create();

        $this->getJson('/api/v1/devices')->assertOk();

        $body = (string) ApiRequestLog::query()->sole()->response_body;
        $this->assertStringEndsWith('...[truncated]', $body);
        $this->assertLessThanOrEqual(40 + mb_strlen('...[truncated]'), mb_strlen($body));
    }

    #[Test]
    public function aLoggingFailureDoesNotBreakTheRequest(): void
    {
        Exceptions::fake();
        $failingLogger = new class () implements ApiTrafficLogger {
            public function log(ApiTrafficRecord $record): void
            {
                throw new RuntimeException('Log storage is unavailable.');
            }
        };
        $this->app->instance(ApiTrafficLogger::class, $failingLogger);

        $this->getJson('/api/v1/devices')->assertOk();

        Exceptions::assertReported(
            static fn (RuntimeException $exception): bool => $exception->getMessage() === 'Log storage is unavailable.',
        );
    }

    /**
     * @return array<mixed>
     */
    private function decode(?string $json): array
    {
        $this->assertNotNull($json);

        return (array) json_decode($json, true, flags: JSON_THROW_ON_ERROR);
    }
}
