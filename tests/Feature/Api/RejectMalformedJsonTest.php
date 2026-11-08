<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class RejectMalformedJsonTest extends TestCase
{
    use RefreshDatabase;

    private const string INVALID_JSON = 'The request body is not valid JSON.';

    private const string NOT_AN_OBJECT = 'The request body must be a JSON object.';

    private const array JSON = ['CONTENT_TYPE' => 'application/json'];

    public function testMalformedJsonPatchIsRejected(): void
    {
        $device = Device::factory()->create();

        // Laravel would read the body as empty input, and the PATCH would "succeed" without changing anything.
        $response = $this->call('PATCH', "/api/v1/devices/{$device->id}", server: self::JSON, content: '{"name": "X"');

        $this->assertProblemDetails($response, 400);
        $response->assertJsonPath('detail', self::INVALID_JSON);
        $this->assertDatabaseHas('devices', ['id' => $device->id, 'name' => $device->name]);
    }

    public function testMalformedJsonIsRejectedBeforeModelBinding(): void
    {
        // 400, not 404: the body is checked before the device is looked up.
        $response = $this->call('PATCH', '/api/v1/devices/999999', server: self::JSON, content: '{"name": "X"');

        $this->assertProblemDetails($response, 400);
        $response->assertJsonPath('detail', self::INVALID_JSON);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedJsonBodies(): iterable
    {
        yield 'syntax error' => ['{"name": "Laptop", "manufacturer": "Dell", "model": "X1"'];
        yield 'invalid UTF-8' => ["{\"name\": \"Laptop\xFF\", \"manufacturer\": \"Dell\", \"model\": \"X1\"}"];
        // Correct syntax, but PHP cannot turn a key starting with a NUL character into an object property.
        yield 'key starting with NUL' => ['{"\u0000a": 1}'];
    }

    #[DataProvider('malformedJsonBodies')]
    public function testMalformedJsonPostIsRejected(string $body): void
    {
        $response = $this->call('POST', '/api/v1/devices', server: self::JSON, content: $body);

        $this->assertProblemDetails($response, 400);
        $response->assertJsonPath('detail', self::INVALID_JSON);
        $this->assertDatabaseCount('devices', 0);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function nonObjectJsonBodies(): iterable
    {
        yield 'double-encoded JSON' => ['"{\"name\": \"X\"}"'];
        yield 'list' => ['[{"name": "X"}]'];
        yield 'number' => ['123'];
    }

    #[DataProvider('nonObjectJsonBodies')]
    public function testNonObjectJsonBodyIsRejected(string $body): void
    {
        $device = Device::factory()->create();

        // Valid JSON, but without the fields of an object the PATCH would "succeed" without changing anything.
        $response = $this->call('PATCH', "/api/v1/devices/{$device->id}", server: self::JSON, content: $body);

        $this->assertProblemDetails($response, 400);
        $response->assertJsonPath('detail', self::NOT_AN_OBJECT);
        $this->assertDatabaseHas('devices', ['id' => $device->id, 'name' => $device->name]);
    }

    public function testValidAndEmptyJsonBodiesAreAccepted(): void
    {
        $device = Device::factory()->create();
        $other = Device::factory()->create();

        $this->call('GET', '/api/v1/devices', server: self::JSON)->assertOk();
        $this->postJson('/api/v1/devices', ['name' => 'Laptop', 'manufacturer' => 'Dell', 'model' => 'X1'])
            ->assertCreated();
        $this->call('DELETE', "/api/v1/devices/{$device->id}", server: self::JSON)->assertNoContent();
        // Like in Laravel, a body of only whitespace counts as empty.
        $this->call('DELETE', "/api/v1/devices/{$other->id}", server: self::JSON, content: " \n")->assertNoContent();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function emptyJsonValues(): iterable
    {
        // PHP encodes an empty payload as [] (e.g. json_encode([]), Laravel's getJson()).
        yield 'empty list' => ['[]'];
        yield 'null' => ['null'];
    }

    #[DataProvider('emptyJsonValues')]
    public function testEmptyJsonValuesCountAsNoChanges(string $body): void
    {
        $device = Device::factory()->create();

        $this->call('PATCH', "/api/v1/devices/{$device->id}", server: self::JSON, content: $body)
            ->assertOk()
            ->assertJsonPath('data.name', $device->name);
    }
}
