<?php

namespace App\Src\Das\UseCases;

use App\Src\Das\Domain\DasCalculation;
use App\Src\Das\Services\DasCalculationService;

class ShowDasCalculationUseCase
{
    public function __construct(
        private readonly DasCalculationService $dasCalculationService,
    ) {}

    public function handle(int $id): DasCalculation
    {
        return $this->dasCalculationService->show($id);
    }
}
