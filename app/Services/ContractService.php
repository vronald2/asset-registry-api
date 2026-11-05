<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\ContractData;
use App\Models\Contract;
use App\Models\ContractDevice;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Use cases of the contracts.
 */
final readonly class ContractService
{
    /**
     * Contracts without their devices: the list view does not load the line items.
     *
     * @return LengthAwarePaginator<int, Contract>
     */
    public function paginate(int $perPage): LengthAwarePaginator
    {
        return Contract::query()
            ->orderBy('id')
            ->paginate($perPage)
            ->appends('per_page', (string) $perPage);
    }

    /**
     * Eager loads the line items of the contract (with their device types) for the detailed view.
     */
    public function loadDevices(Contract $contract): Contract
    {
        return $contract->load([
            'contractDevices' => static fn (HasMany $query): HasMany => $query->orderBy('id'),
            'contractDevices.device',
        ]);
    }

    public function create(ContractData $data): Contract
    {
        return Contract::query()->create($data->toArray());
    }

    public function update(Contract $contract, ContractData $data): Contract
    {
        $contract->update($data->toArray());

        return $contract;
    }

    /**
     * Deletes the contract; its line items ({@see ContractDevice}) are removed by the ON DELETE CASCADE
     * foreign key, while the device types themselves are kept.
     */
    public function delete(Contract $contract): void
    {
        $contract->delete();
    }
}
