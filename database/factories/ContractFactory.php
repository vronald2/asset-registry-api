<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Contract;
use App\Models\Device;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contract>
 */
class ContractFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = CarbonImmutable::instance(fake()->dateTimeBetween('-2 years'))->startOfDay();
        $endDate = fake()->boolean(70) ? $startDate->addMonths(fake()->numberBetween(6, 36)) : null;

        return [
            'contract_number' => fake()->unique()->bothify('CTR-####-####'),
            'partner_name' => fake()->company(),
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate?->toDateString(),
            'description' => fake()->optional()->sentence(),
        ];
    }

    /**
     * Assigns the given number of new devices to the contract, each with a unique serial number.
     */
    public function withDevices(int $count = 1): static
    {
        return $this->hasAttached(
            Device::factory()->count($count),
            static fn (): array => ['serial_number' => fake()->unique()->bothify('SN-????-########')],
            'devices',
        );
    }
}
