<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ContractDevice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A device unit on a contract. Its `id` identifies the unit when it is removed from the contract.
 *
 * @mixin ContractDevice
 */
final class ContractDeviceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'contract_id' => $this->contract_id,
            'serial_number' => $this->serial_number,
            'device' => DeviceResource::make($this->whenLoaded('device')),
            /** @format date-time */
            'assigned_at' => $this->created_at?->toIso8601ZuluString(),
        ];
    }
}
