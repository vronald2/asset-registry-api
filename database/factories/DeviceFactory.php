<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Device;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->word()) . ' ' . fake()->numerify('####'),
            'manufacturer' => fake()->company(),
            'model' => strtoupper(fake()->bothify('??-####')),
            'description' => fake()->optional()->sentence(),
        ];
    }
}
