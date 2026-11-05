<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Data\ContractDeviceData;
use App\Exceptions\SerialNumberAlreadyAssignedException;
use App\Models\Contract;
use App\Models\ContractDevice;
use App\Models\Device;
use App\Services\ContractDeviceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

final class ContractDeviceApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function addsDeviceToContract(): void
    {
        $contract = Contract::factory()->create();
        $device = Device::factory()->create();

        $response = $this->attach($contract, ['device_id' => $device->id, 'serial_number' => '5CD4211XKQ']);

        $response->assertCreated()
            ->assertJsonPath('data.contract_id', $contract->id)
            ->assertJsonPath('data.serial_number', '5CD4211XKQ')
            ->assertJsonPath('data.device.id', $device->id)
            ->assertJsonStructure(['data' => ['id', 'contract_id', 'serial_number', 'device', 'assigned_at']]);

        $this->assertDatabaseHas('contract_devices', [
            'id' => $response->json('data.id'),
            'contract_id' => $contract->id,
            'device_id' => $device->id,
            'serial_number' => '5CD4211XKQ',
        ]);
    }

    #[Test]
    public function deviceTypeCanBeOnSeveralContractsWithDifferentSerialNumbers(): void
    {
        $first = Contract::factory()->create();
        $second = Contract::factory()->create();
        $device = Device::factory()->create();

        $this->attach($first, ['device_id' => $device->id, 'serial_number' => 'SN-1'])->assertCreated();
        $this->attach($second, ['device_id' => $device->id, 'serial_number' => 'SN-2'])->assertCreated();

        $this->assertEqualsCanonicalizing(
            [$first->id, $second->id],
            $device->contracts()->pluck('contracts.id')->all(),
        );
        $this->assertSame([$device->id], $first->devices()->pluck('devices.id')->all());
    }

    #[Test]
    public function deviceTypeCanBeOnOneContractSeveralTimes(): void
    {
        $contract = Contract::factory()->create();
        $device = Device::factory()->create();

        foreach (['SN-1', 'SN-2', 'SN-3'] as $serialNumber) {
            $this->attach($contract, ['device_id' => $device->id, 'serial_number' => $serialNumber])->assertCreated();
        }

        $this->getJson("/api/v1/contracts/{$contract->id}")->assertJsonCount(3, 'data.devices');
    }

    #[Test]
    public function rejectsSerialNumberThatIsAlreadyOnContract(): void
    {
        $device = Device::factory()->create();
        ContractDevice::factory()->for($device)->create(['serial_number' => 'SN-1']);
        $contract = Contract::factory()->create();

        $this->attach($contract, ['device_id' => $device->id, 'serial_number' => 'SN-1'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonValidationErrors('serial_number');

        $this->assertDatabaseCount('contract_devices', 1);
    }

    #[Test]
    public function allowsTheSameSerialNumberForDifferentDeviceTypes(): void
    {
        ContractDevice::factory()->create(['serial_number' => 'SN-1']);
        $contract = Contract::factory()->create();
        $otherDevice = Device::factory()->create();

        $this->attach($contract, ['device_id' => $otherDevice->id, 'serial_number' => 'SN-1'])->assertCreated();
    }

    #[Test]
    public function validatesDeviceAndSerialNumber(): void
    {
        $contract = Contract::factory()->create();

        $this->attach($contract, ['device_id' => 999, 'serial_number' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['device_id', 'serial_number']);
    }

    #[Test]
    public function returnsNotFoundWhenAddingToMissingContract(): void
    {
        $device = Device::factory()->create();

        $this->postJson('/api/v1/contracts/999/devices', ['device_id' => $device->id, 'serial_number' => 'SN-1'])
            ->assertNotFound()
            ->assertJsonPath('code', 'resource_not_found');
    }

    #[Test]
    public function removesDeviceFromContract(): void
    {
        $unit = ContractDevice::factory()->create();

        $this->deleteJson("/api/v1/contracts/{$unit->contract_id}/devices/{$unit->id}")->assertNoContent();

        $this->assertModelMissing($unit);
        $this->assertDatabaseHas('devices', ['id' => $unit->device_id]);
    }

    #[Test]
    public function doesNotRemoveDeviceUnitOfAnotherContract(): void
    {
        $unit = ContractDevice::factory()->create();
        $otherContract = Contract::factory()->create();

        $this->deleteJson("/api/v1/contracts/{$otherContract->id}/devices/{$unit->id}")
            ->assertNotFound()
            ->assertJsonPath('code', 'resource_not_found');

        $this->assertModelExists($unit);
    }

    #[Test]
    public function databaseConstraintGuardsAgainstConcurrentDuplicateAssignments(): void
    {
        // Simulates two concurrent requests that both passed validation: the service is called directly.
        $unit = ContractDevice::factory()->create(['serial_number' => 'SN-1']);
        $contract = Contract::factory()->create();

        $this->expectException(SerialNumberAlreadyAssignedException::class);

        $this->app->make(ContractDeviceService::class)
            ->attach($contract, new ContractDeviceData($unit->device_id, 'SN-1'));
    }

    /**
     * @param array<string, mixed> $payload
     * @return TestResponse<Response>
     */
    private function attach(Contract $contract, array $payload): TestResponse
    {
        return $this->postJson("/api/v1/contracts/{$contract->id}/devices", $payload);
    }
}
