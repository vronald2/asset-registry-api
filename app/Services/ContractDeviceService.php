<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\DeviceAlreadyAttachedException;
use App\Exceptions\DeviceNotAttachedException;
use App\Models\Contract;
use App\Models\Device;

/**
 * Business rules of the device assignments of contracts. The unique indexes of the contract_device table back the
 * same rules up against concurrent requests.
 */
final readonly class ContractDeviceService
{
    /**
     * Assigns the device to the contract with the serial number of the concrete unit.
     *
     * @return Device the device with its assignment (pivot) loaded
     *
     * @throws DeviceAlreadyAttachedException if the device is already on the contract
     */
    public function attach(Contract $contract, int $deviceId, string $serialNumber): Device
    {
        $device = Device::query()->findOrFail($deviceId);

        if ($contract->devices()->whereKey($device->getKey())->exists()) {
            throw DeviceAlreadyAttachedException::forAssignment($contract, $device);
        }

        $contract->devices()->attach($device, ['serial_number' => $serialNumber]);

        return $contract->devices()->whereKey($device->getKey())->firstOrFail();
    }

    /**
     * Removes the device from the contract; the device itself is kept.
     *
     * @throws DeviceNotAttachedException if the device is not on the contract
     */
    public function detach(Contract $contract, Device $device): void
    {
        if ($contract->devices()->detach($device) === 0) {
            throw DeviceNotAttachedException::forAssignment($contract, $device);
        }
    }
}
