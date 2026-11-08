<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\ContractDevice;
use App\Models\Device;
use Illuminate\Http\Request;
use LogicException;

/**
 * A device as it appears on a contract: the device type and the serial number of the assigned unit.
 *
 * @mixin Device
 */
final class ContractDeviceResource extends DeviceResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $assignment = $this->assignment();

        return [
            ...parent::toArray($request),
            'serial_number' => $assignment->serial_number,
            'attached_at' => $assignment->created_at?->toIso8601String(),
        ];
    }

    private function assignment(): ContractDevice
    {
        $assignment = $this->resource instanceof Device ? $this->resource->getRelationValue('pivot') : null;

        if (! $assignment instanceof ContractDevice) {
            throw new LogicException('The device has to be loaded through the devices relation of a contract.');
        }

        return $assignment;
    }
}
