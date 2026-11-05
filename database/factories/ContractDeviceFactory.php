<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Contract;
use App\Models\ContractDevice;
use App\Models\Device;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContractDevice>
 */
final class ContractDeviceFactory extends Factory
{
    /** @var class-string<ContractDevice> */
    protected $model = ContractDevice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'contract_id' => Contract::factory(),
            'device_id' => Device::factory(),
            'serial_number' => $this->faker->unique()->bothify('SN-########'),
        ];
    }
}
