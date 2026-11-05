<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Data\ContractData;
use App\Models\Contract;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Full representation of a contract, shared by create (POST) and full update (PUT).
 */
final class ContractRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'contract_number' => [
                'required',
                'string',
                'max:50',
                // On update the contract itself is ignored; on create there is no bound contract (no-op).
                Rule::unique(Contract::class, 'contract_number')->ignore($this->route('contract')),
            ],
            'partner_name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function toData(): ContractData
    {
        $input = $this->safe();

        return new ContractData(
            contractNumber: $input->string('contract_number')->value(),
            partnerName: $input->string('partner_name')->value(),
            startDate: CarbonImmutable::parse($input->string('start_date')->value()),
            endDate: $input->filled('end_date') ? CarbonImmutable::parse($input->string('end_date')->value()) : null,
            description: $input->filled('description') ? $input->string('description')->value() : null,
        );
    }
}
