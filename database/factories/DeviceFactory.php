<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DeviceCategory;
use App\Models\Device;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Device>
 */
final class DeviceFactory extends Factory
{
    /** @var class-string<Device> */
    protected $model = Device::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->bothify('Model ??-###'),
            'manufacturer' => $this->faker->randomElement(['Apple', 'Cisco', 'Dell', 'HP', 'Lenovo', 'Samsung']),
            'category' => $this->faker->randomElement(DeviceCategory::cases()),
            'description' => $this->faker->optional()->sentence(),
        ];
    }
}
