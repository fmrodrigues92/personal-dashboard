<?php

namespace App\Src\Das\UseCases;

use App\Models\DasCalculationTaxBreakdown;
use App\Src\Das\Domain\DasCalculation;
use App\Src\Das\Services\DasCalculationService;

class UpdateDasTaxBreakdownUseCase
{
    public function __construct(
        private readonly DasCalculationService $dasCalculationService,
    ) {}

    public function handle(DasCalculationTaxBreakdown $taxBreakdown, float $adjustedAmountBrl): DasCalculation
    {
        return $this->dasCalculationService->updateTaxBreakdown($taxBreakdown, $adjustedAmountBrl);
    }
}
