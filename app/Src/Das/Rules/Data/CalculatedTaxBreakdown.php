<?php

namespace App\Src\Das\Rules\Data;

readonly class CalculatedTaxBreakdown
{
    public function __construct(
        public string $taxComponentCode,
        public ?int $annexUsed,
        public ?string $invoiceType,
        public float $calculatedAmountBrl,
        public ?float $ratePercentage,
    ) {}
}
