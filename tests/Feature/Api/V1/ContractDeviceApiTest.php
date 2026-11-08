<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Contract;
use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ContractDeviceApiTest extends TestCase
{
    use RefreshDatabase;

    public function testAttachAddsDeviceToContract(): void
    {
        $contract = Contract::factory()->create();
        $device = Device::factory()->create();

        $response = $this->postJson(
            $this->devicesOf($contract),
            ['device_id' => $device->id, 'serial_number' => 'SN-0001'],
        );

        $response->assertCreated()
            ->assertJsonStructure([
                'data' => ['id', 'name', 'manufacturer', 'model', 'description', 'serial_number', 'attached_at'],
            ])
            ->assertJsonPath('data.id', $device->id)
            ->assertJsonPath('data.name', $device->name)
            ->assertJsonPath('data.serial_number', 'SN-0001');
        $this->assertDatabaseHas(
            'contract_device',
            ['contract_id' => $contract->id, 'device_id' => $device->id, 'serial_number' => 'SN-0001'],
        );

        $this->getJson("/api/v1/contracts/{$contract->id}")
            ->assertJsonCount(1, 'data.devices')
            ->assertJsonPath('data.devices.0.id', $device->id)
            ->assertJsonPath('data.devices.0.serial_number', 'SN-0001');
    }

    public function testSameDeviceCanBeAttachedToAnotherContract(): void
    {
        $device = Device::factory()->create();
        $first = Contract::factory()->create();
        $second = Contract::factory()->create();

        $this->postJson($this->devicesOf($first), ['device_id' => $device->id, 'serial_number' => 'SN-A'])
            ->assertCreated();
        $this->postJson($this->devicesOf($second), ['device_id' => $device->id, 'serial_number' => 'SN-B'])
            ->assertCreated();

        $this->assertDatabaseHas('contract_device', ['contract_id' => $first->id, 'device_id' => $device->id]);
        $this->assertDatabaseHas('contract_device', ['contract_id' => $second->id, 'device_id' => $device->id]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function serialNumbersInUse(): iterable
    {
        yield 'same serial number' => ['SN-ABC-123'];
        yield 'different case' => ['sn-abc-123'];
        yield 'surrounding whitespace' => ['  SN-ABC-123  '];
    }

    #[DataProvider('serialNumbersInUse')]
    public function testAttachRejectsSerialNumberAlreadyInUse(string $serialNumber): void
    {
        Contract::factory()->create()->devices()->attach(
            Device::factory()->create(),
            ['serial_number' => 'SN-ABC-123'],
        );
        $contract = Contract::factory()->create();
        $device = Device::factory()->create();

        $response = $this->postJson(
            $this->devicesOf($contract),
            ['device_id' => $device->id, 'serial_number' => $serialNumber],
        );

        $this->assertProblemDetails($response, 422);
        $response->assertJsonValidationErrors(['serial_number']);
        $this->assertDatabaseMissing('contract_device', ['contract_id' => $contract->id]);
    }

    public function testAttachRejectsDeviceAlreadyOnContract(): void
    {
        $contract = Contract::factory()->create();
        $device = Device::factory()->create();
        $contract->devices()->attach($device, ['serial_number' => 'SN-FIRST']);

        $response = $this->postJson(
            $this->devicesOf($contract),
            ['device_id' => $device->id, 'serial_number' => 'SN-SECOND'],
        );

        $this->assertProblemDetails($response, 409);
        $response->assertJsonPath('detail', "Device {$device->id} is already assigned to contract {$contract->id}.");
        $this->assertDatabaseCount('contract_device', 1);
        $this->assertDatabaseMissing('contract_device', ['serial_number' => 'SN-SECOND']);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidAttachPayloads(): iterable
    {
        yield 'missing device id' => [['device_id' => null], 'device_id'];
        yield 'unknown device id' => [['device_id' => 999999], 'device_id'];
        yield 'non-integer device id' => [['device_id' => 'laptop'], 'device_id'];
        yield 'missing serial number' => [['serial_number' => null], 'serial_number'];
        yield 'empty serial number' => [['serial_number' => ''], 'serial_number'];
        yield 'too long serial number' => [['serial_number' => str_repeat('S', 101)], 'serial_number'];
        yield 'non-string serial number' => [['serial_number' => ['SN-0001']], 'serial_number'];
    }

    /**
     * @param array<string, mixed> $overrides Fields replacing the ones of a valid payload.
     */
    #[DataProvider('invalidAttachPayloads')]
    public function testAttachRejectsInvalidPayload(array $overrides, string $invalidField): void
    {
        $contract = Contract::factory()->create();
        $device = Device::factory()->create();

        $response = $this->postJson(
            $this->devicesOf($contract),
            [...['device_id' => $device->id, 'serial_number' => 'SN-0001'], ...$overrides],
        );

        $this->assertProblemDetails($response, 422);
        $response->assertJsonValidationErrors([$invalidField]);
        $this->assertDatabaseCount('contract_device', 0);
    }

    public function testAttachRequiresAJsonIntegerDeviceId(): void
    {
        $contract = Contract::factory()->create();
        // Both values would be read as 1 by a lenient integer check.
        Device::factory()->create(['id' => 1]);

        foreach ([true, '1'] as $deviceId) {
            $response = $this->postJson(
                $this->devicesOf($contract),
                ['device_id' => $deviceId, 'serial_number' => 'SN-0001'],
            );

            $this->assertProblemDetails($response, 422);
            $response->assertJsonValidationErrors(['device_id']);
        }

        $this->assertDatabaseCount('contract_device', 0);
    }

    public function testAttachReturnsNotFoundForUnknownContract(): void
    {
        $device = Device::factory()->create();

        $response = $this->postJson(
            '/api/v1/contracts/999999/devices',
            ['device_id' => $device->id, 'serial_number' => 'SN-0001'],
        );

        $this->assertProblemDetails($response, 404);
        $response->assertJsonPath('detail', 'Contract not found.');
    }

    public function testDetachRemovesDeviceFromContract(): void
    {
        $contract = Contract::factory()->create();
        $device = Device::factory()->create();
        $contract->devices()->attach($device, ['serial_number' => 'SN-0001']);

        $this->deleteJson($this->devicesOf($contract) . "/{$device->id}")->assertNoContent();

        $this->assertDatabaseMissing('contract_device', ['contract_id' => $contract->id, 'device_id' => $device->id]);
        $this->assertModelExists($contract);
        $this->assertModelExists($device);
    }

    public function testDetachReturnsNotFoundWhenDeviceIsNotOnContract(): void
    {
        $contract = Contract::factory()->create();
        $device = Device::factory()->create();
        Contract::factory()->create()->devices()->attach($device, ['serial_number' => 'SN-ELSEWHERE']);

        $response = $this->deleteJson($this->devicesOf($contract) . "/{$device->id}");

        $this->assertProblemDetails($response, 404);
        $response->assertJsonPath('detail', "Device {$device->id} is not assigned to contract {$contract->id}.");
        $this->assertDatabaseHas('contract_device', ['device_id' => $device->id, 'serial_number' => 'SN-ELSEWHERE']);
    }

    public function testDetachReturnsNotFoundForUnknownDevice(): void
    {
        $contract = Contract::factory()->create();

        $response = $this->deleteJson($this->devicesOf($contract) . '/999999');

        $this->assertProblemDetails($response, 404);
        $response->assertJsonPath('detail', 'Device not found.');
    }

    private function devicesOf(Contract $contract): string
    {
        return "/api/v1/contracts/{$contract->id}/devices";
    }
}
