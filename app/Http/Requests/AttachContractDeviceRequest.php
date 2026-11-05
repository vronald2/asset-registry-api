<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Data\ContractDeviceData;
use App\Models\ContractDevice;
use App\Models\Device;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A physical device unit to be added to a contract.
 */
final class AttachContractDeviceRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'device_id' => ['required', 'integer', Rule::exists(Device::class, 'id')],
            'serial_number' => [
                'required',
                'string',
                'max:100',
                // A unit (device type + serial number) can be on one contract at a time.
                Rule::unique(ContractDevice::class, 'serial_number')
                    ->where('device_id', $this->integer('device_id')),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'serial_number.unique' => 'A unit of this device with the given serial number is already on a contract.',
        ];
    }

    public function toData(): ContractDeviceData
    {
        $input = $this->safe();

        return new ContractDeviceData(
            deviceId: $input->integer('device_id'),
            serialNumber: $input->string('serial_number')->value(),
        );
    }
}
