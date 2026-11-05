<?php

declare(strict_types=1);

namespace App\Data;

use Carbon\CarbonImmutable;

/**
 * Validated, complete representation of a contract (used for create and full update).
 */
final readonly class ContractData
{
    public function __construct(
        public string $contractNumber,
        public string $partnerName,
        public CarbonImmutable $startDate,
        public ?CarbonImmutable $endDate,
        public ?string $description,
    ) {
    }

    /**
     * @return array{
     *     contract_number: string,
     *     partner_name: string,
     *     start_date: string,
     *     end_date: string|null,
     *     description: string|null,
     * }
     */
    public function toArray(): array
    {
        return [
            'contract_number' => $this->contractNumber,
            'partner_name' => $this->partnerName,
            'start_date' => $this->startDate->toDateString(),
            'end_date' => $this->endDate?->toDateString(),
            'description' => $this->description,
        ];
    }
}
