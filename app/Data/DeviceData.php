<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\DeviceCategory;

/**
 * Validated, complete representation of a device type (used for create and full update).
 */
final readonly class DeviceData
{
    public function __construct(
        public string $name,
        public string $manufacturer,
        public DeviceCategory $category,
        public ?string $description,
    ) {
    }

    /**
     * @return array{name: string, manufacturer: string, category: DeviceCategory, description: string|null}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'manufacturer' => $this->manufacturer,
            'category' => $this->category,
            'description' => $this->description,
        ];
    }
}
