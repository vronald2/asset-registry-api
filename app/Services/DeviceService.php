<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\DeviceData;
use App\Exceptions\DeviceInUseException;
use App\Models\Device;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Use cases of the device type catalogue.
 */
final readonly class DeviceService
{
    /**
     * @return LengthAwarePaginator<int, Device>
     */
    public function paginate(int $perPage): LengthAwarePaginator
    {
        return Device::query()
            ->orderBy('id')
            ->paginate($perPage)
            ->appends('per_page', (string) $perPage);
    }

    public function create(DeviceData $data): Device
    {
        return Device::query()->create($data->toArray());
    }

    public function update(Device $device, DeviceData $data): Device
    {
        $device->update($data->toArray());

        return $device;
    }

    /**
     * @throws DeviceInUseException when units of the device type are assigned to contracts
     */
    public function delete(Device $device): void
    {
        // Checked explicitly for a meaningful error; the RESTRICT foreign key is the last line of defence.
        if ($device->contracts()->exists()) {
            throw DeviceInUseException::forDevice($device);
        }

        $device->delete();
    }
}
