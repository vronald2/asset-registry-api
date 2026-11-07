<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * The assignment of a device to a contract, carrying the serial number of the concrete unit.
 */
#[Table('contract_device', incrementing: true)]
class ContractDevice extends Pivot
{
}
