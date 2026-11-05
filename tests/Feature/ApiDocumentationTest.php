<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ApiDocumentationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['api.docs.username' => 'docs', 'api.docs.password' => 'secret']);
    }

    #[Test]
    public function requiresCredentials(): void
    {
        $this->get('/docs/api')
            ->assertUnauthorized()
            ->assertHeader('WWW-Authenticate', 'Basic realm="API documentation", charset="UTF-8"');

        $this->get('/docs/api.json')->assertUnauthorized();
    }

    #[Test]
    public function rejectsInvalidCredentials(): void
    {
        $this->withBasicAuth('docs', 'wrong')->get('/docs/api')->assertUnauthorized();
        $this->withBasicAuth('someone', 'secret')->get('/docs/api')->assertUnauthorized();
    }

    #[Test]
    public function deniesAccessWhileNoCredentialsAreConfigured(): void
    {
        config(['api.docs.username' => '', 'api.docs.password' => '']);

        $this->withBasicAuth('', '')->get('/docs/api')->assertUnauthorized();
    }

    #[Test]
    public function servesTheDocumentationWithValidCredentials(): void
    {
        $this->withBasicAuth('docs', 'secret')->get('/docs/api')->assertOk();

        $this->withBasicAuth('docs', 'secret')->getJson('/docs/api.json')
            ->assertOk()
            ->assertJsonPath('info.title', config('app.name'))
            ->assertJsonStructure([
                'openapi',
                'paths' => [
                    '/v1/devices' => ['get', 'post'],
                    '/v1/devices/{device}' => ['get', 'put', 'delete'],
                    '/v1/contracts' => ['get', 'post'],
                    '/v1/contracts/{contract}' => ['get', 'put', 'delete'],
                    '/v1/contracts/{contract}/devices' => ['post'],
                    '/v1/contracts/{contract}/devices/{contractDevice}' => ['delete'],
                ],
            ]);
    }

    #[Test]
    public function documentsTheUniformErrorFormat(): void
    {
        $schemaOf = static fn (string $response): string => "components.responses.{$response}"
            . '.content.application/json.schema';

        $this->withBasicAuth('docs', 'secret')->getJson('/docs/api.json')
            ->assertOk()
            ->assertJsonPath($schemaOf('ValidationException') . '.required', [
                'message',
                'code',
                'errors',
                'request_id',
            ])
            ->assertJsonPath($schemaOf('ModelNotFoundException') . '.properties.code.enum', ['resource_not_found'])
            ->assertJsonPath($schemaOf('DeviceInUseException') . '.properties.code.enum', ['device_in_use']);
    }
}
