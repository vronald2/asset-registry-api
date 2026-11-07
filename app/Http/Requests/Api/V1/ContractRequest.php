<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\Contract;
use Illuminate\Validation\Rule;

/**
 * The payload of creating and updating a contract.
 */
final class ContractRequest extends ResourceWriteRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    protected function fieldRules(): array
    {
        return [
            /**
             * Unique contract number.
             * @example SZ-2026-001
             */
            'contract_number' => [
                'required',
                'string',
                'max:50',
                Rule::unique(Contract::class, 'contract_number')->ignore($this->route('contract')),
            ],
            /**
             * The contracting partner.
             * @example Acme Kft.
             */
            'partner_name' => ['required', 'string', 'max:255'],
            /**
             * First day of the contract (YYYY-MM-DD).
             * @example 2026-01-01
             */
            'start_date' => ['required', 'date_format:Y-m-d', ...$this->storedEndDateRule()],
            /**
             * Last day of the contract (YYYY-MM-DD); not earlier than the start date.
             * @example 2026-12-31
             */
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:' . $this->startDateReference()],
            /** @example Lease of the office laptops. */
            'description' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * What end_date is compared to: the submitted start_date or, when a PATCH leaves it out, the stored one.
     */
    private function startDateReference(): string
    {
        $contract = $this->route('contract');

        if ($this->has('start_date') || ! $contract instanceof Contract) {
            return 'start_date';
        }

        return $contract->start_date->toDateString();
    }

    /**
     * The other direction: a PATCH that keeps the stored end_date must not move start_date past it.
     *
     * @return list<string>
     */
    private function storedEndDateRule(): array
    {
        $contract = $this->route('contract');

        if (! $this->isMethod('PATCH') || $this->has('end_date') || ! $contract instanceof Contract) {
            return [];
        }

        return $contract->end_date === null ? [] : ['before_or_equal:' . $contract->end_date->toDateString()];
    }
}
