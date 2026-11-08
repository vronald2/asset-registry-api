<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

final class RejectMislabeledJsonTest extends TestCase
{
    use RefreshDatabase;

    private const string DETAIL =
        'The request body looks like JSON: send it with the Content-Type: application/json header.';

    private const string FORM_TYPE = 'application/x-www-form-urlencoded';

    private const array FORM = ['CONTENT_TYPE' => self::FORM_TYPE];

    private const string JSON_BODY = '{"name":"X"}';

    public function testJsonSentAsFormIsRejected(): void
    {
        $body = '{"name":"Laptop","manufacturer":"Dell","model":"X1"}';

        $response = $this->call('POST', '/api/v1/devices', server: self::FORM, content: $body);

        $this->assertProblemDetails($response, 415);
        $response->assertJsonPath('detail', self::DETAIL);
        $this->assertDatabaseCount('devices', 0);
    }

    #[TestWith(['PATCH'])]
    #[TestWith(['PUT'])]
    public function testJsonSentAsFormDoesNotChangeTheDevice(string $method): void
    {
        $device = Device::factory()->create();

        // PHP reads the body as one form field: the PATCH would "succeed" without changing anything.
        $response = $this->call($method, "/api/v1/devices/{$device->id}", server: self::FORM, content: self::JSON_BODY);

        $this->assertProblemDetails($response, 415);
        $this->assertDatabaseHas('devices', ['id' => $device->id, 'name' => $device->name]);
    }

    public function testJsonSentAsFormIsRejectedBeforeModelBinding(): void
    {
        // 415, not 404: the body is checked before the device is looked up.
        $response = $this->call('PATCH', '/api/v1/devices/999999', server: self::FORM, content: self::JSON_BODY);

        $this->assertProblemDetails($response, 415);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function mislabeledJsonRequests(): iterable
    {
        yield 'text/plain' => ['text/plain', self::JSON_BODY];
        yield 'no Content-Type' => ['', self::JSON_BODY];
        yield 'leading whitespace' => [self::FORM_TYPE, " \n" . self::JSON_BODY];
        yield 'leading BOM' => [self::FORM_TYPE, "\xEF\xBB\xBF" . self::JSON_BODY];
        yield 'list' => [self::FORM_TYPE, '[{"name":"X"}]'];
    }

    #[DataProvider('mislabeledJsonRequests')]
    public function testMislabeledJsonIsRejected(string $contentType, string $body): void
    {
        $response = $this->call('POST', '/api/v1/devices', server: ['CONTENT_TYPE' => $contentType], content: $body);

        $this->assertProblemDetails($response, 415);
        $response->assertJsonPath('detail', self::DETAIL);
    }

    public function testReadAndDeleteRequestsAreNotRejected(): void
    {
        $device = Device::factory()->create();

        // These endpoints do not read the body.
        $this->call('GET', '/api/v1/devices', server: self::FORM, content: self::JSON_BODY)->assertOk();
        $this->call('DELETE', "/api/v1/devices/{$device->id}", server: self::FORM, content: self::JSON_BODY)
            ->assertNoContent();
    }
}
