<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Device;
use Symfony\Component\HttpFoundation\Response;

/**
 * A device type cannot be deleted while units of it are assigned to contracts.
 */
final class DeviceInUseException extends ApiException
{
    public static function forDevice(Device $device): self
    {
        return new self(sprintf(
            'Device %d is assigned to at least one contract. Remove it from the contracts before deleting it.',
            $device->id,
        ));
    }

    public static function status(): int
    {
        return Response::HTTP_CONFLICT;
    }

    public static function errorCode(): ErrorCode
    {
        return ErrorCode::DeviceInUse;
    }
}
