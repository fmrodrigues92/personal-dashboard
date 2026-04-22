<?php

namespace App\Src\Das\Domain;

use App\Models\DasCalculationTaxBreakdown as DasCalculationTaxBreakdownModel;
use Carbon\CarbonImmutable;

readonly class DasCalculationTaxBreakdown
{
    public function __construct(
        public int $id,
        public int $dasCalculationId,
        public string $taxComponentCode,
        public ?int $annexUsed,
        public ?string $invoiceType,
        public float $calculatedAmountBrl,
        public ?float $adjustedAmountBrl,
        public ?float $ratePercentage,
        public CarbonImmutable $createdAt,
        public CarbonImmutable $updatedAt,
    ) {}

    public static function fromModel(DasCalculationTaxBreakdownModel $taxBreakdown): self
    {
        return new self(
            id: $taxBreakdown->id,
            dasCalculationId: $taxBreakdown->das_calculation_id,
            taxComponentCode: $taxBreakdown->tax_component_code,
            annexUsed: $taxBreakdown->annex_used,
            invoiceType: $taxBreakdown->invoice_type,
            calculatedAmountBrl: $taxBreakdown->calculated_amount_brl,
            adjustedAmountBrl: $taxBreakdown->adjusted_amount_brl,
            ratePercentage: $taxBreakdown->rate_percentage,
            createdAt: $taxBreakdown->created_at,
            updatedAt: $taxBreakdown->updated_at,
        );
    }

    public function effectiveAmountBrl(): float
    {
        return $this->adjustedAmountBrl ?? $this->calculatedAmountBrl;
    }

    /**
     * @return array<string, float|int|string|null>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'das_calculation_id' => $this->dasCalculationId,
            'tax_component_code' => $this->taxComponentCode,
            'annex_used' => $this->annexUsed,
            'invoice_type' => $this->invoiceType,
            'calculated_amount_brl' => $this->calculatedAmountBrl,
            'adjusted_amount_brl' => $this->adjustedAmountBrl,
            'effective_amount_brl' => $this->effectiveAmountBrl(),
            'rate_percentage' => $this->ratePercentage,
            'created_at' => $this->createdAt->toISOString(),
            'updated_at' => $this->updatedAt->toISOString(),
        ];
    }
}
