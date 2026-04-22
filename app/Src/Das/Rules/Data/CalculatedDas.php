<?php

namespace App\Src\Das\Rules\Data;

readonly class CalculatedDas
{
    /**
     * @param  array<int, CalculatedTaxBreakdown>  $taxBreakdowns
     * @param  array<int, int>  $calculationAnnexesByInvoiceId
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $ruleVersion,
        public bool $factorRApplied,
        public float $monthlyRevenueBrl,
        public float $dasTotalBrl,
        public bool $isProjection,
        public array $taxBreakdowns,
        public array $calculationAnnexesByInvoiceId,
        public array $metadata,
    ) {}
}
