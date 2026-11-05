<?php

declare(strict_types=1);

namespace App\Exceptions;

use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * The unit (device type + serial number) is already on a contract.
 *
 * Normally request validation reports this as a field error; this exception covers the race
 * condition when two concurrent requests pass validation and the database unique index rejects one.
 */
final class SerialNumberAlreadyAssignedException extends ApiException
{
    public static function forUnit(int $deviceId, string $serialNumber, ?Throwable $previous = null): self
    {
        return new self(
            sprintf('A unit of device %d with serial number "%s" is already on a contract.', $deviceId, $serialNumber),
            previous: $previous,
        );
    }

    public static function status(): int
    {
        return Response::HTTP_CONFLICT;
    }

    public static function errorCode(): ErrorCode
    {
        return ErrorCode::SerialNumberAlreadyAssigned;
    }
}
