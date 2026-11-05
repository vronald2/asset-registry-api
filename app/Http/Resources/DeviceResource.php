<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Device
 */
final class DeviceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'manufacturer' => $this->manufacturer,
            'category' => $this->category,
            'description' => $this->description,
            /** @format date-time */
            'created_at' => $this->created_at?->toIso8601ZuluString(),
            /** @format date-time */
            'updated_at' => $this->updated_at?->toIso8601ZuluString(),
        ];
    }
}
