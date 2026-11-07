<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Device;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * The device cannot be deleted, because it is assigned to at least one contract.
 */
final class DeviceInUseException extends ApiException
{
    public static function status(): int
    {
        return Response::HTTP_CONFLICT;
    }

    public static function forDevice(Device $device, ?Throwable $previous = null): self
    {
        return new self(
            sprintf(
                'Device %d is assigned to at least one contract, so it cannot be deleted. '
                . 'Remove it from the contracts first.',
                $device->id,
            ),
            previous: $previous,
        );
    }
}
