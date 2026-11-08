<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Contract;
use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class DeviceApiTest extends TestCase
{
    use RefreshDatabase;

    private const string ENDPOINT = '/api/v1/devices';

    private const array VALID_PAYLOAD = [
        'name' => 'Dell Latitude 5440',
        'manufacturer' => 'Dell',
        'model' => 'Latitude 5440',
    ];

    public function testStoreCreatesDevice(): void
    {
        $payload = [...self::VALID_PAYLOAD, 'description' => '14" business laptop'];

        $response = $this->postJson(self::ENDPOINT, $payload);

        $response->assertCreated()
            ->assertJsonStructure([
                'data' => ['id', 'name', 'manufacturer', 'model', 'description', 'created_at', 'updated_at'],
            ])
            ->assertJson(['data' => $payload]);
        $this->assertDatabaseHas('devices', ['id' => $response->json('data.id'), ...$payload]);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidPayloads(): iterable
    {
        yield 'missing name' => [array_diff_key(self::VALID_PAYLOAD, ['name' => true]), 'name'];
        yield 'missing manufacturer' => [array_diff_key(self::VALID_PAYLOAD, ['manufacturer' => true]), 'manufacturer'];
        yield 'missing model' => [array_diff_key(self::VALID_PAYLOAD, ['model' => true]), 'model'];
        yield 'too long name' => [[...self::VALID_PAYLOAD, 'name' => str_repeat('a', 256)], 'name'];
        yield 'non-string manufacturer' => [[...self::VALID_PAYLOAD, 'manufacturer' => ['Dell']], 'manufacturer'];
        yield 'too long description' => [
            [...self::VALID_PAYLOAD, 'description' => str_repeat('a', 5001)],
            'description',
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('invalidPayloads')]
    public function testStoreRejectsInvalidPayload(array $payload, string $invalidField): void
    {
        $response = $this->postJson(self::ENDPOINT, $payload);

        $this->assertProblemDetails($response, 422);
        $response->assertJsonValidationErrors([$invalidField]);
        $message = $response->json("errors.{$invalidField}.0");
        self::assertIsString($message);
        self::assertStringStartsWith("The {$invalidField} field", $message);
        $this->assertDatabaseCount('devices', 0);
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('invalidPayloads')]
    public function testUpdateRejectsInvalidPayload(array $payload, string $invalidField): void
    {
        $device = Device::factory()->create();

        $response = $this->putJson(self::ENDPOINT . "/{$device->id}", $payload);

        $this->assertProblemDetails($response, 422);
        $response->assertJsonValidationErrors([$invalidField]);
        $this->assertDatabaseHas('devices', $device->only(['id', 'name', 'manufacturer', 'model', 'description']));
    }

    public function testIndexReturnsPaginatedDevicesOrderedById(): void
    {
        $devices = Device::factory()->count(20)->create();

        $firstPage = $this->getJson(self::ENDPOINT);

        $firstPage->assertOk()
            ->assertJsonStructure(['data', 'links' => ['first', 'last', 'prev', 'next'], 'meta'])
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath('meta.total', 20);
        self::assertSame($devices->take(15)->pluck('id')->all(), $firstPage->json('data.*.id'));

        $secondPage = $this->getJson(self::ENDPOINT . '?page=2&per_page=5');

        $secondPage->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.per_page', 5);
        self::assertSame($devices->slice(5, 5)->pluck('id')->values()->all(), $secondPage->json('data.*.id'));
        $nextPageLink = $secondPage->json('links.next');
        self::assertIsString($nextPageLink);
        self::assertStringContainsString('per_page=5', $nextPageLink);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidPaginationParameters(): iterable
    {
        yield 'page zero' => ['page=0'];
        yield 'negative page' => ['page=-1'];
        yield 'non-numeric page' => ['page=first'];
        yield 'per_page zero' => ['per_page=0'];
        yield 'per_page above the maximum' => ['per_page=101'];
        yield 'non-numeric per_page' => ['per_page=all'];
    }

    #[DataProvider('invalidPaginationParameters')]
    public function testIndexRejectsInvalidPaginationParameters(string $query): void
    {
        $this->assertProblemDetails($this->getJson(self::ENDPOINT . '?' . $query), 422);
    }

    public function testShowReturnsDevice(): void
    {
        $device = Device::factory()->create();

        $this->getJson(self::ENDPOINT . "/{$device->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $device->id)
            ->assertJsonPath('data.name', $device->name)
            ->assertJsonPath('data.manufacturer', $device->manufacturer)
            ->assertJsonPath('data.model', $device->model)
            ->assertJsonPath('data.description', $device->description)
            ->assertJsonPath('data.created_at', $device->created_at?->toIso8601String());
    }

    public function testShowReturnsNotFoundForUnknownDevice(): void
    {
        $response = $this->getJson(self::ENDPOINT . '/999999');

        $this->assertProblemDetails($response, 404);
        $response->assertJsonPath('detail', 'Device not found.');
    }

    public function testPutReplacesDevice(): void
    {
        $device = Device::factory()->create(['description' => 'Old description']);
        $payload = ['name' => 'HP LaserJet Pro', 'manufacturer' => 'HP', 'model' => 'M404dn'];

        $this->putJson(self::ENDPOINT . "/{$device->id}", $payload)
            ->assertOk()
            ->assertJson(['data' => ['id' => $device->id, ...$payload, 'description' => null]]);

        // The optional field left out of the PUT is cleared.
        $this->assertDatabaseHas('devices', ['id' => $device->id, ...$payload, 'description' => null]);
    }

    public function testPutRequiresAllMandatoryFields(): void
    {
        $device = Device::factory()->create();

        $response = $this->putJson(self::ENDPOINT . "/{$device->id}", ['name' => 'Only the name']);

        $this->assertProblemDetails($response, 422);
        $response->assertJsonValidationErrors(['manufacturer', 'model']);
        $this->assertDatabaseHas('devices', ['id' => $device->id, 'name' => $device->name]);
    }

    public function testPatchUpdatesOnlyGivenField(): void
    {
        $device = Device::factory()->create(['description' => 'Kept description']);

        $this->patchJson(self::ENDPOINT . "/{$device->id}", ['name' => 'Renamed device'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed device');

        $this->assertDatabaseHas('devices', [
            'id' => $device->id,
            'name' => 'Renamed device',
            'manufacturer' => $device->manufacturer,
            'model' => $device->model,
            'description' => 'Kept description',
        ]);
    }

    public function testDestroyDeletesUnassignedDevice(): void
    {
        $device = Device::factory()->create();

        $this->deleteJson(self::ENDPOINT . "/{$device->id}")->assertNoContent();

        $this->assertModelMissing($device);
    }

    public function testDestroyReturnsNotFoundForUnknownDevice(): void
    {
        $this->assertProblemDetails($this->deleteJson(self::ENDPOINT . '/999999'), 404);
    }

    public function testNonNumericIdentifierIsNotFound(): void
    {
        $device = Device::factory()->create();

        // MySQL would cast "1abc" to 1: the route only accepts numeric identifiers.
        $this->assertProblemDetails($this->getJson(self::ENDPOINT . "/{$device->id}abc"), 404);
        $this->assertProblemDetails($this->deleteJson(self::ENDPOINT . "/{$device->id}abc"), 404);
        $this->assertModelExists($device);
    }

    public function testDestroyRejectsDeviceAssignedToContract(): void
    {
        $contract = Contract::factory()->withDevices()->create();
        $device = $contract->devices()->firstOrFail();

        $response = $this->deleteJson(self::ENDPOINT . "/{$device->id}");

        $this->assertProblemDetails($response, 409);
        $this->assertModelExists($device);
        $this->assertDatabaseHas('contract_device', ['contract_id' => $contract->id, 'device_id' => $device->id]);
    }
}
