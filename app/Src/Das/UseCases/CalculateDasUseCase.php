<?php

namespace App\Src\Das\UseCases;

use App\Src\Das\Domain\DasCalculation;
use App\Src\Das\Services\DasCalculationService;

class CalculateDasUseCase
{
    public function __construct(
        private readonly DasCalculationService $dasCalculationService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): DasCalculation
    {
        return $this->dasCalculationService->calculateAndPersist($data);
    }
}
