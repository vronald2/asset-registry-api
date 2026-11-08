<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Contract;
use App\Models\Device;
use Symfony\Component\HttpFoundation\Response;

/**
 * The device is not on the contract, so there is no assignment to remove.
 */
final class DeviceNotAttachedException extends ApiException
{
    public static function status(): int
    {
        return Response::HTTP_NOT_FOUND;
    }

    public static function forAssignment(Contract $contract, Device $device): self
    {
        return new self(sprintf('Device %d is not assigned to contract %d.', $device->id, $contract->id));
    }
}
