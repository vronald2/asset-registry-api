<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Contract;

/**
 * @extends EloquentCrudService<Contract>
 */
final readonly class ContractService extends EloquentCrudService
{
    /**
     * Deletes the contract; the cascading foreign key removes its device assignments, the devices are kept.
     */
    public function delete(Contract $contract): void
    {
        $contract->delete();
    }

    /**
     * Loads the devices of the contract, with their serial numbers, for the detailed representation.
     */
    public function loadDevices(Contract $contract): Contract
    {
        return $contract->load('devices');
    }

    protected function modelClass(): string
    {
        return Contract::class;
    }
}
