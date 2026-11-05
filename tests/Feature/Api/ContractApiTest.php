<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Contract;
use App\Models\ContractDevice;
use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ContractApiTest extends TestCase
{
    use RefreshDatabase;

    private const array VALID_PAYLOAD = [
        'contract_number' => 'SZ-2026/001',
        'partner_name' => 'Példa Kft.',
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'description' => 'Office equipment rental',
    ];

    #[Test]
    public function listsContractsWithoutTheirDevices(): void
    {
        ContractDevice::factory()->count(2)->for(Contract::factory())->create();
        Contract::factory()->create();

        $this->getJson('/api/v1/contracts')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['data' => ['*' => ['id', 'contract_number', 'partner_name', 'start_date']]])
            ->assertJsonMissingPath('data.0.devices')
            ->assertJsonMissingPath('data.1.devices');
    }

    #[Test]
    public function showsContractWithItsDevices(): void
    {
        $contract = Contract::factory()->create(['start_date' => '2026-01-01', 'end_date' => null]);
        $laptop = Device::factory()->create(['name' => 'Latitude 5540']);
        $first = ContractDevice::factory()->for($contract)->for($laptop)->create(['serial_number' => 'SN-1']);
        $second = ContractDevice::factory()->for($contract)->for($laptop)->create(['serial_number' => 'SN-2']);
        ContractDevice::factory()->create(); // a unit on another contract

        $this->getJson("/api/v1/contracts/{$contract->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $contract->id)
            ->assertJsonPath('data.start_date', '2026-01-01')
            ->assertJsonPath('data.end_date', null)
            ->assertJsonCount(2, 'data.devices')
            ->assertJsonPath('data.devices.0.id', $first->id)
            ->assertJsonPath('data.devices.0.serial_number', 'SN-1')
            ->assertJsonPath('data.devices.0.device.id', $laptop->id)
            ->assertJsonPath('data.devices.0.device.name', 'Latitude 5540')
            ->assertJsonPath('data.devices.1.id', $second->id)
            ->assertJsonPath('data.devices.1.serial_number', 'SN-2')
            ->assertJsonStructure([
                'data' => ['devices' => ['*' => ['id', 'contract_id', 'serial_number', 'device', 'assigned_at']]],
            ]);
    }

    #[Test]
    public function returnsNotFoundForMissingContract(): void
    {
        $this->getJson('/api/v1/contracts/999')
            ->assertNotFound()
            ->assertJsonPath('code', 'resource_not_found')
            ->assertJsonPath('message', 'Contract not found.');
    }

    #[Test]
    public function createsContract(): void
    {
        $response = $this->postJson('/api/v1/contracts', self::VALID_PAYLOAD);

        $response->assertCreated()
            ->assertJsonPath('data.contract_number', 'SZ-2026/001')
            ->assertJsonPath('data.partner_name', 'Példa Kft.')
            ->assertJsonPath('data.start_date', '2026-01-01')
            ->assertJsonPath('data.end_date', '2026-12-31')
            ->assertJsonMissingPath('data.devices');

        $contract = Contract::query()->sole();
        $this->assertSame('SZ-2026/001', $contract->contract_number);
        $response->assertJsonPath('data.id', $contract->id)
            ->assertHeader('Location', "http://localhost/api/v1/contracts/{$contract->id}");
    }

    #[Test]
    public function validatesContract(): void
    {
        $this->postJson('/api/v1/contracts', [
            'start_date' => '2026-06-01',
            'end_date' => '2026-05-31',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonValidationErrors(['contract_number', 'partner_name', 'end_date']);

        $this->postJson('/api/v1/contracts', [...self::VALID_PAYLOAD, 'start_date' => '01/01/2026'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('start_date');
    }

    #[Test]
    public function rejectsDuplicateContractNumber(): void
    {
        Contract::factory()->create(['contract_number' => 'SZ-2026/001']);

        $this->postJson('/api/v1/contracts', self::VALID_PAYLOAD)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('contract_number');
    }

    #[Test]
    public function updatesContractKeepingItsOwnContractNumber(): void
    {
        $contract = Contract::factory()->create(['contract_number' => 'SZ-2026/001']);

        $this->putJson("/api/v1/contracts/{$contract->id}", [...self::VALID_PAYLOAD, 'partner_name' => 'Minta Zrt.'])
            ->assertOk()
            ->assertJsonPath('data.partner_name', 'Minta Zrt.')
            ->assertJsonPath('data.contract_number', 'SZ-2026/001');

        $this->assertDatabaseHas('contracts', ['id' => $contract->id, 'partner_name' => 'Minta Zrt.']);
    }

    #[Test]
    public function refusesToTakeOverTheNumberOfAnotherContract(): void
    {
        Contract::factory()->create(['contract_number' => 'SZ-2026/001']);
        $contract = Contract::factory()->create(['contract_number' => 'SZ-2026/002']);

        $this->putJson("/api/v1/contracts/{$contract->id}", self::VALID_PAYLOAD)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('contract_number');
    }

    #[Test]
    public function deletesContractTogetherWithItsLineItems(): void
    {
        $contract = Contract::factory()->create();
        $unit = ContractDevice::factory()->for($contract)->create();

        $this->deleteJson("/api/v1/contracts/{$contract->id}")->assertNoContent();

        $this->assertModelMissing($contract);
        $this->assertModelMissing($unit);
        $this->assertDatabaseHas('devices', ['id' => $unit->device_id]);
    }
}
