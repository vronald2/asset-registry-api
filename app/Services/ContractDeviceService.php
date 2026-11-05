<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\ContractDeviceData;
use App\Exceptions\SerialNumberAlreadyAssignedException;
use App\Models\Contract;
use App\Models\ContractDevice;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Adding device units to and removing them from contracts.
 */
final readonly class ContractDeviceService
{
    /**
     * @throws SerialNumberAlreadyAssignedException when the unit is already on a contract
     */
    public function attach(Contract $contract, ContractDeviceData $data): ContractDevice
    {
        try {
            $contractDevice = $contract->contractDevices()->create([
                'device_id' => $data->deviceId,
                'serial_number' => $data->serialNumber,
            ]);
        } catch (UniqueConstraintViolationException $exception) {
            throw SerialNumberAlreadyAssignedException::forUnit($data->deviceId, $data->serialNumber, $exception);
        }

        return $contractDevice->load('device');
    }

    public function detach(ContractDevice $contractDevice): void
    {
        $contractDevice->delete();
    }
}
