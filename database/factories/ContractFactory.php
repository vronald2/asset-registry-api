<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Contract;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contract>
 */
final class ContractFactory extends Factory
{
    /** @var class-string<Contract> */
    protected $model = Contract::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = CarbonImmutable::instance($this->faker->dateTimeBetween('-1 year'))->startOfDay();

        return [
            'contract_number' => $this->faker->unique()->numerify('SZ-####/####'),
            'partner_name' => $this->faker->company(),
            'start_date' => $startDate->toDateString(),
            'end_date' => $this->faker->boolean(80) ? $startDate->addYear()->toDateString() : null,
            'description' => $this->faker->optional()->sentence(),
        ];
    }
}
