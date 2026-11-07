<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\DeviceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A device type (e.g. "Dell Latitude 5440"). It can be assigned to any number of contracts; every assignment carries
 * the serial number of the concrete unit.
 */
#[Fillable(['name', 'manufacturer', 'model', 'description'])]
class Device extends Model
{
    /** @use HasFactory<DeviceFactory> */
    use HasFactory;

    /**
     * Defaults of the nullable columns, so a freshly created model exposes every attribute.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'description' => null,
    ];

    /**
     * @return BelongsToMany<Contract, $this, ContractDevice, 'pivot'>
     */
    public function contracts(): BelongsToMany
    {
        return $this->belongsToMany(Contract::class)
            ->using(ContractDevice::class)
            ->withPivot('serial_number')
            ->withTimestamps();
    }
}
