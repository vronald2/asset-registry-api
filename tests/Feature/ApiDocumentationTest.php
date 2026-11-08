<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\ProblemDetails\ProblemDetails;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ApiDocumentationTest extends TestCase
{
    use RefreshDatabase;

    private const string USERNAME = 'docs-user';

    private const string PASSWORD = 'docs-password';

    private const string CHALLENGE = 'Basic realm="Asset Registry API documentation", charset="UTF-8"';

    private const array DOCUMENTED_OPERATIONS = [
        '/api/v1/devices' => ['get', 'post'],
        '/api/v1/devices/{device}' => ['get', 'put', 'patch', 'delete'],
        '/api/v1/contracts' => ['get', 'post'],
        '/api/v1/contracts/{contract}' => ['get', 'put', 'patch', 'delete'],
        '/api/v1/contracts/{contract}/devices' => ['post'],
        '/api/v1/contracts/{contract}/devices/{device}' => ['delete'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        config(['api-docs.username' => self::USERNAME, 'api-docs.password' => self::PASSWORD]);
    }

    /**
     * @return iterable<string, array{string, array<string, string>}>
     */
    public static function unauthenticatedRequests(): iterable
    {
        foreach (['/docs/api', '/docs/api.json'] as $uri) {
            yield "{$uri}, no credentials" => [$uri, []];
            yield "{$uri}, wrong password" => [$uri, self::basicAuthHeader(self::USERNAME, 'wrong-password')];
            yield "{$uri}, wrong username" => [$uri, self::basicAuthHeader('someone-else', self::PASSWORD)];
        }
    }

    /**
     * @param array<string, string> $headers
     */
    #[DataProvider('unauthenticatedRequests')]
    public function testDocsRequireValidBasicAuth(string $uri, array $headers): void
    {
        $this->withHeaders($headers)
            ->get($uri)
            ->assertUnauthorized()
            ->assertHeader('WWW-Authenticate', self::CHALLENGE);
    }

    public function testDocsAreClosedWhenCredentialsAreNotConfigured(): void
    {
        config(['api-docs.username' => '', 'api-docs.password' => '']);

        $this->get('/docs/api.json')->assertUnauthorized();
        $this->withBasicAuth('', '')->get('/docs/api.json')->assertUnauthorized();
        $this->withBasicAuth(self::USERNAME, self::PASSWORD)->get('/docs/api')->assertUnauthorized();
    }

    public function testDocsUiIsServed(): void
    {
        $response = $this->withBasicAuth(self::USERNAME, self::PASSWORD)->get('/docs/api');

        $response->assertOk()->assertSee('Asset Registry API');
        self::assertStringStartsWith('text/html', (string) $response->headers->get('Content-Type'));
    }

    public function testOpenApiDocumentDescribesAllEndpoints(): void
    {
        $document = $this->openApiDocument();

        $version = $document['openapi'] ?? null;
        self::assertIsString($version);
        self::assertStringStartsWith('3.', $version);
        self::assertSame('Asset Registry API', data_get($document, 'info.title'));
        self::assertSame('1.0.0', data_get($document, 'info.version'));

        foreach (self::DOCUMENTED_OPERATIONS as $path => $methods) {
            foreach ($methods as $method) {
                self::assertIsArray(
                    data_get($document, ['paths', $path, $method]),
                    "{$method} {$path} is not documented.",
                );
            }
        }
    }

    public function testErrorResponsesAreDocumentedAsProblemDetails(): void
    {
        $document = $this->openApiDocument();

        $errorResponses = [
            ['/api/v1/devices', 'post', 422],
            ['/api/v1/devices/{device}', 'get', 404],
            ['/api/v1/devices/{device}', 'delete', 409],
            ['/api/v1/contracts/{contract}/devices', 'post', 409],
            ['/api/v1/contracts/{contract}/devices/{device}', 'delete', 404],
        ];

        foreach ($errorResponses as [$path, $method, $status]) {
            $content = data_get($document, ['paths', $path, $method, 'responses', (string) $status, 'content']);
            self::assertIsArray($content, "{$method} {$path} documents no {$status} response.");
            self::assertArrayHasKey(ProblemDetails::CONTENT_TYPE, $content, "{$method} {$path} {$status}");
        }

        self::assertIsArray(data_get($document, [
            'paths',
            '/api/v1/devices',
            'post',
            'responses',
            '422',
            'content',
            ProblemDetails::CONTENT_TYPE,
            'schema',
            'properties',
            'errors',
        ]));
    }

    public function testDocumentedExamplesAreFreeInTheDemoData(): void
    {
        $this->seed();

        $document = $this->openApiDocument();
        $example = 'components.schemas.%s.properties.%s.examples.0';
        $contractNumber = data_get($document, sprintf($example, 'ContractRequest', 'contract_number'));
        $serialNumber = data_get($document, sprintf($example, 'AttachDeviceRequest', 'serial_number'));

        // "Try it" in the documentation must work on a seeded database too.
        self::assertSame('SZ-2026-010', $contractNumber);
        self::assertSame('SN-EXAMPLE-0001', $serialNumber);
        $this->assertDatabaseMissing('contracts', ['contract_number' => $contractNumber]);
        $this->assertDatabaseMissing('contract_device', ['serial_number' => $serialNumber]);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function openApiDocument(): array
    {
        $document = $this->withBasicAuth(self::USERNAME, self::PASSWORD)->get('/docs/api.json')->assertOk()->json();
        self::assertIsArray($document);

        return $document;
    }

    /**
     * @return array<string, string>
     */
    private static function basicAuthHeader(string $username, string $password): array
    {
        return ['Authorization' => 'Basic ' . base64_encode("{$username}:{$password}")];
    }
}
