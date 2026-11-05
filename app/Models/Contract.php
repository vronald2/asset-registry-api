<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ContractFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $contract_number
 * @property string $partner_name
 * @property CarbonImmutable $start_date
 * @property CarbonImmutable|null $end_date
 * @property string|null $description
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['contract_number', 'partner_name', 'start_date', 'end_date', 'description'])]
final class Contract extends Model
{
    /** @use HasFactory<ContractFactory> */
    use HasFactory;

    /**
     * The line items of the contract: device units identified by their serial numbers.
     *
     * @return HasMany<ContractDevice, $this>
     */
    public function contractDevices(): HasMany
    {
        return $this->hasMany(ContractDevice::class);
    }

    /**
     * The device types on this contract (many-to-many through contract_devices).
     *
     * @return BelongsToMany<Device, $this>
     */
    public function devices(): BelongsToMany
    {
        return $this->belongsToMany(Device::class, 'contract_devices')
            ->withPivot(['id', 'serial_number'])
            ->withTimestamps();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'immutable_date',
            'end_date' => 'immutable_date',
        ];
    }
}
