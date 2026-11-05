<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DeviceCategory;
use Carbon\CarbonImmutable;
use Database\Factories\DeviceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A device type (catalogue item), e.g. "Latitude 5540" made by Dell.
 *
 * Physical units are tracked per contract by {@see ContractDevice}, each with its own serial number.
 *
 * @property int $id
 * @property string $name
 * @property string $manufacturer
 * @property DeviceCategory $category
 * @property string|null $description
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'manufacturer', 'category', 'description'])]
final class Device extends Model
{
    /** @use HasFactory<DeviceFactory> */
    use HasFactory;

    /**
     * The serial-numbered units of this device type, across all contracts.
     *
     * @return HasMany<ContractDevice, $this>
     */
    public function contractDevices(): HasMany
    {
        return $this->hasMany(ContractDevice::class);
    }

    /**
     * The contracts this device type is assigned to (many-to-many through contract_devices).
     *
     * @return BelongsToMany<Contract, $this>
     */
    public function contracts(): BelongsToMany
    {
        return $this->belongsToMany(Contract::class, 'contract_devices')
            ->withPivot(['id', 'serial_number'])
            ->withTimestamps();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => DeviceCategory::class,
        ];
    }
}
