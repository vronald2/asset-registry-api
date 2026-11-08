<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Contract;
use App\Models\Device;
use Symfony\Component\HttpFoundation\Response;

/**
 * The device type is already on the contract; a device appears at most once on the same contract.
 */
final class DeviceAlreadyAttachedException extends ApiException
{
    public static function status(): int
    {
        return Response::HTTP_CONFLICT;
    }

    public static function forAssignment(Contract $contract, Device $device): self
    {
        return new self(sprintf('Device %d is already assigned to contract %d.', $device->id, $contract->id));
    }
}
