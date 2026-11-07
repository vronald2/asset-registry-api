<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\ContractDevice;
use App\Models\Device;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The payload of adding a device to a contract.
 */
final class AttachDeviceRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            /**
             * Identifier of the device (type) to add.
             * @example 1
             */
            'device_id' => ['required', 'integer:strict', Rule::exists(Device::class, 'id')],
            /**
             * Serial number of the concrete unit; unique across all contracts (case-insensitive).
             * @example SN-EXAMPLE-0001
             */
            'serial_number' => ['required', 'string', 'max:100', Rule::unique(ContractDevice::class, 'serial_number')],
        ];
    }

    public function deviceId(): int
    {
        return $this->integer('device_id');
    }

    public function serialNumber(): string
    {
        return $this->string('serial_number')->toString();
    }
}
