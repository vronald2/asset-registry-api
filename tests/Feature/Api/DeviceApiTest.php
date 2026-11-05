<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\DeviceCategory;
use App\Models\ContractDevice;
use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class DeviceApiTest extends TestCase
{
    use RefreshDatabase;

    private const array DEVICE_STRUCTURE = [
        'id',
        'name',
        'manufacturer',
        'category',
        'description',
        'created_at',
        'updated_at',
    ];

    #[Test]
    public function listsDevicesWithPagination(): void
    {
        Device::factory()->count(3)->create();

        $this->getJson('/api/v1/devices?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['data' => ['*' => self::DEVICE_STRUCTURE], 'links', 'meta'])
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('links.next', 'http://localhost/api/v1/devices?per_page=2&page=2');
    }

    #[Test]
    public function rejectsInvalidPageSize(): void
    {
        $this->getJson('/api/v1/devices?per_page=101')
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonValidationErrors('per_page');
    }

    #[Test]
    public function showsDevice(): void
    {
        $device = Device::factory()->create(['category' => DeviceCategory::Printer]);

        $this->getJson("/api/v1/devices/{$device->id}")
            ->assertOk()
            ->assertJsonStructure(['data' => self::DEVICE_STRUCTURE])
            ->assertJsonPath('data.id', $device->id)
            ->assertJsonPath('data.name', $device->name)
            ->assertJsonPath('data.category', 'printer');
    }

    #[Test]
    public function returnsNotFoundForMissingDevice(): void
    {
        $this->getJson('/api/v1/devices/999')
            ->assertNotFound()
            ->assertJsonPath('code', 'resource_not_found')
            ->assertJsonPath('message', 'Device not found.');
    }

    #[Test]
    public function createsDevice(): void
    {
        $response = $this->postJson('/api/v1/devices', [
            'name' => 'Latitude 5540',
            'manufacturer' => 'Dell',
            'category' => 'laptop',
            'description' => '15.6" business laptop',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Latitude 5540')
            ->assertJsonPath('data.manufacturer', 'Dell')
            ->assertJsonPath('data.category', 'laptop')
            ->assertJsonPath('data.description', '15.6" business laptop');

        $device = Device::query()->sole();
        $this->assertSame('Latitude 5540', $device->name);
        $this->assertSame(DeviceCategory::Laptop, $device->category);
        $response->assertJsonPath('data.id', $device->id)
            ->assertHeader('Location', "http://localhost/api/v1/devices/{$device->id}");
    }

    #[Test]
    public function validatesDevice(): void
    {
        $this->postJson('/api/v1/devices', ['category' => 'spaceship', 'description' => str_repeat('x', 2001)])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonValidationErrors(['name', 'manufacturer', 'category', 'description']);

        $this->assertDatabaseCount('devices', 0);
    }

    #[Test]
    public function updatesDeviceByReplacingItsRepresentation(): void
    {
        $device = Device::factory()->create(['description' => 'Old description']);

        $this->putJson("/api/v1/devices/{$device->id}", [
            'name' => 'ThinkPad T14 Gen 5',
            'manufacturer' => 'Lenovo',
            'category' => 'laptop',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'ThinkPad T14 Gen 5')
            ->assertJsonPath('data.description', null);

        $this->assertDatabaseHas('devices', [
            'id' => $device->id,
            'name' => 'ThinkPad T14 Gen 5',
            'manufacturer' => 'Lenovo',
            'description' => null,
        ]);
    }

    #[Test]
    public function doesNotSupportPartialUpdates(): void
    {
        $device = Device::factory()->create();

        $this->patchJson("/api/v1/devices/{$device->id}", ['name' => 'New name'])
            ->assertMethodNotAllowed()
            ->assertJsonPath('code', 'method_not_allowed');
    }

    #[Test]
    public function deletesDeviceThatIsNotOnAnyContract(): void
    {
        $device = Device::factory()->create();

        $this->deleteJson("/api/v1/devices/{$device->id}")->assertNoContent();

        $this->assertModelMissing($device);
    }

    #[Test]
    public function refusesToDeleteDeviceThatIsOnContract(): void
    {
        $device = Device::factory()->create();
        ContractDevice::factory()->for($device)->create();

        $this->deleteJson("/api/v1/devices/{$device->id}")
            ->assertConflict()
            ->assertJsonPath('code', 'device_in_use');

        $this->assertModelExists($device);
    }
}
