<?php

declare(strict_types=1);

namespace App\Data;

/**
 * A physical device unit to be put on a contract: the device type and the unit's serial number.
 */
final readonly class ContractDeviceData
{
    public function __construct(
        public int $deviceId,
        public string $serialNumber,
    ) {
    }
}
