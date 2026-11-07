<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\ApiRequestLog;
use App\Models\Contract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

final class ProblemDetailsTest extends TestCase
{
    use RefreshDatabase;

    private const string FAILING_ENDPOINT = '/api/v1/test-only/failure';

    protected function setUp(): void
    {
        parent::setUp();

        Route::get(self::FAILING_ENDPOINT, static fn () => throw new RuntimeException('Sensitive internal detail'));
    }

    public function testUnknownApiRouteReturnsProblemDetails(): void
    {
        // No Accept header: the format must not depend on content negotiation.
        $response = $this->get('/api/v1/unknown-resource');

        $this->assertProblemDetails($response, 404);
        $response->assertJsonPath('instance', '/api/v1/unknown-resource');
    }

    public function testInvalidUtf8PathReturnsProblemDetailsAndIsLogged(): void
    {
        // Laravel's ValidatePathEncoding middleware rejects the URL before routing (400 "Malformed URL.").
        $response = $this->get('/api/v1/%FF');

        $this->assertProblemDetails($response, 400);
        $response->assertJsonPath('detail', 'Malformed URL.');
        $log = ApiRequestLog::query()->sole();
        self::assertSame('/api/v1/%FF', $log->path);
        self::assertSame(400, $log->response_status);
    }

    public function testRawInvalidByteInPathIsSubstitutedInTheResponse(): void
    {
        // A raw (not percent-encoded) byte reaches the instance member as it is: the encoding must not fail on it.
        $response = $this->get("/api/v1/\xFF");

        $this->assertProblemDetails($response, 400);
        $response->assertJsonPath('instance', "/api/v1/\u{FFFD}");
        self::assertSame('/api/v1/?', ApiRequestLog::query()->sole()->path);
    }

    public function testUnsupportedMethodReturnsProblemDetails(): void
    {
        $response = $this->call('PATCH', '/api/v1/devices');

        $this->assertProblemDetails($response, 405);
        $response->assertHeader('Allow', 'GET, HEAD, POST');
    }

    public function testUnhandledExceptionHidesInternalsInProduction(): void
    {
        config(['app.debug' => false]);

        $response = $this->get(self::FAILING_ENDPOINT);

        $this->assertProblemDetails($response, 500);
        $response->assertJsonPath('detail', 'An unexpected error occurred.');
        $response->assertJsonMissingPath('debug');
        $response->assertDontSee('Sensitive internal detail');
        $response->assertDontSee('.php');
        $response->assertDontSee('trace');
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function errorScenarios(): iterable
    {
        yield 'unknown route' => ['unknown route', 404];
        yield 'unsupported method' => ['unsupported method', 405];
        yield 'device in use' => ['device in use', 409];
        yield 'invalid payload' => ['invalid payload', 422];
        yield 'unexpected failure' => ['unexpected failure', 500];
    }

    #[DataProvider('errorScenarios')]
    public function testErrorResponsesShareProblemDetailsStructure(string $scenario, int $status): void
    {
        config(['app.debug' => false]);

        $response = $this->triggerError($scenario);

        $this->assertProblemDetails($response, $status);
        $body = $response->json();
        self::assertIsArray($body);
        self::assertSame(['type', 'title', 'status', 'detail', 'instance'], array_slice(array_keys($body), 0, 5));
        self::assertIsString($body['detail']);
        self::assertNotSame('', $body['detail']);
    }

    /**
     * @return TestResponse<Response>
     */
    private function triggerError(string $scenario): TestResponse
    {
        return match ($scenario) {
            'unknown route' => $this->get('/api/v1/unknown-resource'),
            'unsupported method' => $this->call('PATCH', '/api/v1/devices'),
            'device in use' => $this->deleteJson(sprintf(
                '/api/v1/devices/%d',
                Contract::factory()->withDevices()->create()->devices()->firstOrFail()->id,
            )),
            'invalid payload' => $this->postJson('/api/v1/devices', []),
            default => $this->get(self::FAILING_ENDPOINT),
        };
    }
}
