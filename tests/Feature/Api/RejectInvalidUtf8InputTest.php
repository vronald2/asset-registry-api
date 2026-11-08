<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\ApiRequestLog;
use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class RejectInvalidUtf8InputTest extends TestCase
{
    use RefreshDatabase;

    private const string DETAIL = 'The request contains invalid UTF-8 data.';

    public function testInvalidUtf8FormFieldIsRejected(): void
    {
        $response = $this->post('/api/v1/devices', ['name' => "Laptop\xFF", 'manufacturer' => 'Dell', 'model' => 'X1']);

        $this->assertProblemDetails($response, 400);
        $response->assertJsonPath('detail', self::DETAIL);
        $this->assertDatabaseCount('devices', 0);
    }

    public function testInvalidUtf8QueryParameterIsRejected(): void
    {
        $device = Device::factory()->create();

        $response = $this->patch("/api/v1/devices/{$device->id}?name=%FF");

        $this->assertProblemDetails($response, 400);
        $response->assertJsonPath('detail', self::DETAIL);
        $this->assertDatabaseHas('devices', ['id' => $device->id, 'name' => $device->name]);
    }

    public function testInvalidUtf8KeyIsRejected(): void
    {
        $response = $this->get('/api/v1/devices?%FF=1');

        $this->assertProblemDetails($response, 400);
        $response->assertJsonPath('detail', self::DETAIL);
    }

    /**
     * @return iterable<string, array{string, string, array<string, mixed>}>
     */
    public static function nestedInvalidInput(): iterable
    {
        yield 'nested query value' => ['GET', '/api/v1/devices?filter[name]=%FF', []];
        yield 'nested query key' => ['GET', '/api/v1/devices?filter[%FF]=1', []];
        yield 'nested form value' => [
            'POST',
            '/api/v1/devices',
            ['name' => 'Laptop', 'manufacturer' => 'Dell', 'model' => 'X1', 'tags' => ['ok', "\xFF"]],
        ];
    }

    /**
     * @param array<string, mixed> $form
     */
    #[DataProvider('nestedInvalidInput')]
    public function testInvalidUtf8InNestedParametersIsRejected(string $method, string $uri, array $form): void
    {
        $response = $this->call($method, $uri, $form);

        $this->assertProblemDetails($response, 400);
        $response->assertJsonPath('detail', self::DETAIL);
        $this->assertDatabaseCount('devices', 0);
    }

    public function testValidInputIsNotRejected(): void
    {
        // "árvíztűrő" in the query string and accented form values are valid UTF-8.
        $this->get('/api/v1/devices?page=1&note=%C3%A1rv%C3%ADzt%C5%B1r%C5%91')->assertOk();
        $this->post('/api/v1/devices', ['name' => 'Árvíztűrő tükörfúrógép', 'manufacturer' => 'Dell', 'model' => 'X1'])
            ->assertCreated();
    }

    public function testRejectedFormRequestIsLoggedWithScrubbedBody(): void
    {
        $this->post('/api/v1/devices', ['name' => "Laptop\xFF", 'manufacturer' => 'Dell', 'model' => 'X1'])
            ->assertStatus(400);

        $log = ApiRequestLog::query()->sole();
        self::assertSame(400, $log->response_status);
        $body = json_decode((string) $log->request_body, true);
        self::assertIsArray($body);
        self::assertSame('Laptop?', $body['name']);
        self::assertSame('Dell', $body['manufacturer']);
    }
}
