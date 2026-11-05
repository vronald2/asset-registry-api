<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Contract;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Contract
 */
final class ContractResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'contract_number' => $this->contract_number,
            'partner_name' => $this->partner_name,
            /** @format date */
            'start_date' => $this->start_date->toDateString(),
            /** @format date */
            'end_date' => $this->end_date?->toDateString(),
            'description' => $this->description,
            // Present only in the detailed view: the list endpoint does not load the line items.
            'devices' => ContractDeviceResource::collection($this->whenLoaded('contractDevices')),
            /** @format date-time */
            'created_at' => $this->created_at?->toIso8601ZuluString(),
            /** @format date-time */
            'updated_at' => $this->updated_at?->toIso8601ZuluString(),
        ];
    }
}
