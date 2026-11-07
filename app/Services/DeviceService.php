<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\DeviceInUseException;
use App\Models\Device;
use Illuminate\Database\QueryException;

/**
 * @extends EloquentCrudService<Device>
 */
final readonly class DeviceService extends EloquentCrudService
{
    /** MySQL error code of a delete that a foreign key blocked (ER_ROW_IS_REFERENCED_2). */
    private const int ROW_IS_REFERENCED = 1451;

    /**
     * @throws DeviceInUseException if the device is assigned to at least one contract
     */
    public function delete(Device $device): void
    {
        if ($device->contracts()->exists()) {
            throw DeviceInUseException::forDevice($device);
        }

        try {
            $device->deleteOrFail();
        } catch (QueryException $exception) {
            // A concurrent assignment slipped in between the check and the delete: the foreign key blocked it.
            if (($exception->errorInfo[1] ?? null) !== self::ROW_IS_REFERENCED) {
                throw $exception;
            }

            throw DeviceInUseException::forDevice($device, $exception);
        }
    }

    protected function modelClass(): string
    {
        return Device::class;
    }
}
