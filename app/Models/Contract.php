<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ContractFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property CarbonImmutable $start_date
 * @property CarbonImmutable|null $end_date
 */
#[Fillable(['contract_number', 'partner_name', 'start_date', 'end_date', 'description'])]
class Contract extends Model
{
    /** @use HasFactory<ContractFactory> */
    use HasFactory;

    /**
     * Defaults of the nullable columns, so a freshly created model exposes every attribute.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'end_date' => null,
        'description' => null,
    ];

    /**
     * The devices on the contract, in the order they were assigned.
     *
     * @return BelongsToMany<Device, $this, ContractDevice, 'pivot'>
     */
    public function devices(): BelongsToMany
    {
        return $this->belongsToMany(Device::class)
            ->using(ContractDevice::class)
            ->withPivot('serial_number')
            ->withTimestamps()
            ->orderByPivot('id');
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
