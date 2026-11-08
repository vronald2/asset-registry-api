<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Models\Contract;
use App\Models\ContractDevice;
use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function testDemoSeederCreatesConsistentData(): void
    {
        $this->seed();
        // Running it again must not fail or duplicate anything.
        $this->seed();

        $this->assertDatabaseCount('devices', 6);
        $this->assertDatabaseCount('contracts', 3);
        $this->assertDatabaseCount('contract_device', 7);

        // Distinct under the case-insensitive collation: no two assignments share a serial number.
        self::assertSame(7, ContractDevice::query()->distinct()->count('serial_number'));

        // N:N: a device type is on several contracts, with a different serial number on each.
        $laptop = Device::query()->where('model', 'Latitude 5440')->sole();
        self::assertSame(2, $laptop->contracts()->count());

        $contract = Contract::query()->where('contract_number', 'SZ-2026-001')->sole();
        $this->getJson("/api/v1/contracts/{$contract->id}")
            ->assertOk()
            ->assertJsonCount(3, 'data.devices');
    }
}
