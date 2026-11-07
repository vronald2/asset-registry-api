<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

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
            'start_date' => $this->start_date->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'description' => $this->description,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            /**
             * The devices on the contract; only present when a single contract is requested.
             *
             * @var list<ContractDeviceResource>
             */
            'devices' => ContractDeviceResource::collection($this->whenLoaded('devices')),
        ];
    }
}
