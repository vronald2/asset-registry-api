<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ContractDeviceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Association entity of the contract <-> device many-to-many relationship:
 * one physical device unit on a contract, identified by its serial number.
 *
 * It is a first-class model (with its own id) because the relationship has its own attribute
 * (the serial number) and its own lifecycle (a unit is added to / removed from a contract).
 *
 * @property int $id
 * @property int $contract_id
 * @property int $device_id
 * @property string $serial_number
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Contract $contract
 * @property-read Device $device
 */
#[Fillable(['contract_id', 'device_id', 'serial_number'])]
final class ContractDevice extends Model
{
    /** @use HasFactory<ContractDeviceFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Contract, $this>
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    /**
     * @return BelongsTo<Device, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
