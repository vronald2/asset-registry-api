<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Http\ProblemDetails\ProblemDetails;
use App\Logging\ApiTraffic\ApiTrafficEntry;
use App\Logging\ApiTraffic\ApiTrafficLogger;
use App\Models\ApiRequestLog;
use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

final class ApiTrafficLoggingTest extends TestCase
{
    use RefreshDatabase;

    private const array DEVICE_PAYLOAD = [
        'name' => 'Dell Latitude 5440',
        'manufacturer' => 'Dell',
        'model' => 'Latitude 5440',
    ];

    public function testSuccessfulRequestIsLogged(): void
    {
        $response = $this->withHeaders(['X-Request-Id' => 'req-123', 'User-Agent' => 'AssetRegistryTest/1.0'])
            ->postJson('/api/v1/devices?source=test', self::DEVICE_PAYLOAD);

        $response->assertCreated();
        $log = ApiRequestLog::query()->sole();

        self::assertSame('POST', $log->method);
        self::assertSame('/api/v1/devices', $log->path);
        self::assertSame('/api/v1/devices', $log->route);
        self::assertSame(['source' => 'test'], $log->query);
        self::assertSame(['req-123'], $log->request_headers['x-request-id']);
        self::assertSame(self::DEVICE_PAYLOAD, json_decode((string) $log->request_body, true));
        self::assertSame(201, $log->response_status);
        self::assertSame(['application/json'], $log->response_headers['content-type']);
        self::assertSame($response->json(), json_decode((string) $log->response_body, true));
        self::assertGreaterThanOrEqual(0, $log->duration_ms);
        self::assertSame('127.0.0.1', $log->ip);
        self::assertSame('AssetRegistryTest/1.0', $log->user_agent);
        self::assertTrue($log->created_at->greaterThan(now()->subMinute()));
    }

    /**
     * @return iterable<string, array{string, string, int}>
     */
    public static function failingRequests(): iterable
    {
        yield 'validation error' => ['POST', '/api/v1/devices', 422];
        yield 'unknown route' => ['GET', '/api/v1/unknown-resource', 404];
        yield 'unexpected failure' => ['GET', '/api/v1/test-only/failure', 500];
    }

    #[DataProvider('failingRequests')]
    public function testErrorResponsesAreLogged(string $method, string $uri, int $status): void
    {
        config(['app.debug' => false]);
        Route::get('/api/v1/test-only/failure', static fn () => throw new RuntimeException('Boom'));

        $response = $this->json($method, $uri);

        $response->assertStatus($status);
        $log = ApiRequestLog::query()->sole();
        self::assertSame($uri, $log->path);
        self::assertSame($status, $log->response_status);
        self::assertSame([ProblemDetails::CONTENT_TYPE], $log->response_headers['content-type']);
        self::assertSame($response->json(), json_decode((string) $log->response_body, true));
    }

    public function testSensitiveDataIsMasked(): void
    {
        $this->withHeaders(['Authorization' => 'Bearer secret-token', 'Cookie' => 'session=secret-cookie'])
            ->postJson('/api/v1/devices?api_key=secret-query', [
                ...self::DEVICE_PAYLOAD,
                'password' => 'secret-password',
                'credentials' => ['api_token' => 'secret-nested', 'Secret' => 'secret-mixed-case'],
            ])
            ->assertCreated();

        $log = ApiRequestLog::query()->sole();
        $stored = (string) json_encode($log->getAttributes());
        $secrets = ['secret-token', 'secret-cookie', 'secret-query', 'secret-password', 'secret-nested'];
        foreach ([...$secrets, 'secret-mixed'] as $secret) {
            self::assertStringNotContainsString($secret, $stored);
        }

        self::assertSame(['***'], $log->request_headers['authorization']);
        self::assertSame(['***'], $log->request_headers['cookie']);
        self::assertSame(['api_key' => '***'], $log->query);
        $body = json_decode((string) $log->request_body, true);
        self::assertIsArray($body);
        self::assertSame('Dell Latitude 5440', $body['name']);
        self::assertSame('***', $body['password']);
        self::assertSame(['api_token' => '***', 'Secret' => '***'], $body['credentials']);
    }

    public function testBasicAuthCredentialsAreMasked(): void
    {
        // Symfony also exposes the decoded Basic Auth credentials as "php-auth-*" pseudo headers.
        $this->withBasicAuth('api-user', 'basic-secret')->getJson('/api/v1/devices')->assertOk();

        $log = ApiRequestLog::query()->sole();
        $stored = (string) json_encode($log->getAttributes());
        self::assertStringNotContainsString('basic-secret', $stored);
        self::assertStringNotContainsString(base64_encode('api-user:basic-secret'), $stored);
        self::assertSame(['***'], $log->request_headers['authorization']);
        self::assertSame(['***'], $log->request_headers['php-auth-user']);
        self::assertSame(['***'], $log->request_headers['php-auth-pw']);
    }

    public function testNonUtf8BodyIsLoggedWithMarker(): void
    {
        $this->call(
            'POST',
            '/api/v1/devices',
            server: ['CONTENT_TYPE' => 'application/octet-stream', 'HTTP_USER_AGENT' => "Agent\xFF"],
            content: "\xFF\xFE binary payload",
        )->assertUnprocessable();

        $log = ApiRequestLog::query()->sole();
        self::assertSame('[non-UTF-8 body, 17 bytes]', $log->request_body);
        self::assertSame('Agent?', $log->user_agent);
    }

    public function testLongOverriddenMethodIsLogged(): void
    {
        $response = $this->call('POST', '/api/v1/devices', server: ['HTTP_X_HTTP_METHOD_OVERRIDE' => 'VERSIONCONTROL']);

        $this->assertProblemDetails($response, 405);
        $log = ApiRequestLog::query()->sole();
        // Cut to the width of the method column; the override header itself is kept among the request headers.
        self::assertSame('VERSIONCON', $log->method);
        self::assertSame(['VERSIONCONTROL'], $log->request_headers['x-http-method-override']);
    }

    public function testUnparsableJsonBodyIsReplacedWithMarker(): void
    {
        $body = '{"name":"x","password":"hunter2"';

        // Rejected as malformed JSON (400); the log must not keep the unmasked body either.
        $this->call('POST', '/api/v1/devices', server: ['CONTENT_TYPE' => 'application/json'], content: $body)
            ->assertBadRequest();

        $log = ApiRequestLog::query()->sole();
        self::assertSame(sprintf('[unparsable JSON body, %d bytes]', strlen($body)), $log->request_body);
        self::assertStringNotContainsString('hunter2', (string) json_encode($log->getAttributes()));
    }

    public function testJsonSentAsFormIsLoggedMasked(): void
    {
        // "curl -d" sends JSON as application/x-www-form-urlencoded: PHP reads the whole body as one form key.
        $body = '{"name":"Laptop","password":"hunter2"}';

        $this->call(
            'POST',
            '/api/v1/devices',
            [$body => ''],
            server: ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'],
            content: $body,
        )->assertUnprocessable();

        $log = ApiRequestLog::query()->sole();
        self::assertSame('{"name":"Laptop","password":"***"}', $log->request_body);
        self::assertStringNotContainsString('hunter2', (string) json_encode($log->getAttributes()));
    }

    /**
     * The form fields are given the way PHP parses such a body: the whole text up to the first "=" becomes one field
     * name, with its spaces turned into underscores.
     *
     * @return iterable<string, array{string, array<string, string>, string, string}>
     */
    public static function bodiesSentAsForm(): iterable
    {
        $invalidJson = '{"name":"Laptop","password":"hunter2",}';

        yield 'invalid JSON' => [$invalidJson, [$invalidJson => ''], '[unparsable JSON body, 39 bytes]', 'hunter2'];
        yield 'text without name=value pairs' => [
            'password: hunter2',
            ['password:_hunter2' => ''],
            '[unparsed body, 17 bytes]',
            'hunter2',
        ];
        yield 'header line with a Base64 value' => [
            'Authorization: Basic dXNlcjpwYXNzd29yZA==',
            ['Authorization:_Basic_dXNlcjpwYXNzd29yZA' => '='],
            '[unparsed body, 41 bytes]',
            'dXNlcjpwYXNzd29yZA',
        ];
        yield 'field name with a space' => [
            'password hunter2=x',
            ['password_hunter2' => 'x'],
            '[unparsed body, 18 bytes]',
            'hunter2',
        ];
        yield 'field name ending with a line break' => [
            'hunter2%0A=x',
            ["hunter2\n" => 'x'],
            '[unparsed body, 12 bytes]',
            'hunter2',
        ];
        yield 'field name with an unclosed bracket' => [
            'a[hunter2=x',
            ['a_hunter2' => 'x'],
            '[unparsed body, 11 bytes]',
            'hunter2',
        ];
    }

    /**
     * @param array<string, string> $parsedFields
     */
    #[DataProvider('bodiesSentAsForm')]
    public function testBodySentAsFormDoesNotLeakSecrets(
        string $body,
        array $parsedFields,
        string $expected,
        string $secret,
    ): void {
        $this->call(
            'POST',
            '/api/v1/devices',
            $parsedFields,
            server: ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'],
            content: $body,
        )->assertUnprocessable();

        $log = ApiRequestLog::query()->sole();
        self::assertSame($expected, $log->request_body);
        self::assertStringNotContainsString($secret, (string) json_encode($log->getAttributes()));
    }

    public function testRealFormRequestIsLoggedMasked(): void
    {
        $fields = [...self::DEVICE_PAYLOAD, 'password' => 'hunter2', 'credentials' => ['password' => 'hunter2']];

        // http_build_query() URL-encodes the brackets of the nested field, like a browser.
        $this->call(
            'POST',
            '/api/v1/devices',
            $fields,
            server: ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'],
            content: http_build_query($fields),
        )->assertCreated();

        $log = ApiRequestLog::query()->sole();
        self::assertSame(
            [...self::DEVICE_PAYLOAD, 'password' => '***', 'credentials' => ['password' => '***']],
            json_decode((string) $log->request_body, true),
        );
        self::assertStringNotContainsString('hunter2', (string) json_encode($log->getAttributes()));
    }

    public function testFormFieldNamesWithBracketsDotsAndDashesAreLogged(): void
    {
        $fields = ['tags' => ['a'], 'meta' => ['a' => ['b' => 'c']], 'x_y-z' => '1'];

        // Written by hand: http_build_query() would number the items of "tags[]".
        $this->call(
            'POST',
            '/api/v1/devices',
            $fields,
            server: ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'],
            content: 'tags%5B%5D=a&meta%5Ba%5D%5Bb%5D=c&x.y-z=1',
        )->assertUnprocessable();

        self::assertSame($fields, json_decode((string) ApiRequestLog::query()->sole()->request_body, true));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function unparsedTextBodies(): iterable
    {
        yield 'name=value text' => ['password=hunter2', '[unparsed body, 16 bytes]'];
        // Valid JSON, but a string has no keys to recognize a secret by.
        yield 'JSON string' => ['"password=hunter2"', '[unparsed body, 18 bytes]'];
    }

    #[DataProvider('unparsedTextBodies')]
    public function testUnparsedTextBodyIsLoggedBySizeOnly(string $body, string $expected): void
    {
        $this->call('POST', '/api/v1/devices', server: ['CONTENT_TYPE' => 'text/plain'], content: $body)
            ->assertUnprocessable();

        $log = ApiRequestLog::query()->sole();
        self::assertSame($expected, $log->request_body);
        self::assertStringNotContainsString('hunter2', (string) json_encode($log->getAttributes()));
    }

    public function testJsonScalarBodyIsReplacedWithMarker(): void
    {
        // Double-encoded JSON is a valid JSON string: rejected as not an object (400), and not logged as it is either.
        $body = '"{\"name\":\"Laptop\",\"password\":\"hunter2\"}"';

        $this->call('POST', '/api/v1/devices', server: ['CONTENT_TYPE' => 'application/json'], content: $body)
            ->assertBadRequest();

        $log = ApiRequestLog::query()->sole();
        self::assertSame(sprintf('[JSON scalar body, %d bytes]', strlen($body)), $log->request_body);
        self::assertStringNotContainsString('hunter2', (string) json_encode($log->getAttributes()));
    }

    public function testOversizedBodyIsTruncated(): void
    {
        config(['api-logging.max_body_bytes' => 200]);

        $this->postJson('/api/v1/devices', [...self::DEVICE_PAYLOAD, 'description' => str_repeat('x', 1000)])
            ->assertCreated();

        $log = ApiRequestLog::query()->sole();
        foreach ([$log->request_body, $log->response_body] as $body) {
            self::assertIsString($body);
            self::assertLessThanOrEqual(200, strlen($body));
            self::assertStringContainsString('[truncated,', $body);
        }
    }

    public function testLoggingFailureDoesNotAffectResponse(): void
    {
        Device::factory()->count(2)->create();
        $expected = $this->getJson('/api/v1/devices')->assertOk()->json();

        $this->app->instance(ApiTrafficLogger::class, new class () implements ApiTrafficLogger {
            public function log(ApiTrafficEntry $entry): void
            {
                throw new RuntimeException('The log storage is unavailable.');
            }
        });
        $applicationLog = Log::spy();

        $response = $this->getJson('/api/v1/devices');

        $response->assertOk();
        self::assertSame($expected, $response->json());
        $applicationLog->shouldHaveReceived('error')->once()->withArgs(
            static fn (string $message, array $context): bool => $message === 'Failed to log the API traffic.'
                && ($context['exception'] ?? null) instanceof RuntimeException,
        );
    }

    public function testNonApiRoutesAreNotLogged(): void
    {
        $this->get('/up')->assertOk();
        $this->get('/')->assertRedirect('/docs/api');
        $this->get('/docs/api');

        $this->assertDatabaseCount('api_request_logs', 0);
    }
}
