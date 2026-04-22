<?php

namespace App\Src\Das\UseCases;

use App\Src\Das\Services\DasCalculationService;
use Illuminate\Support\Collection;

class ListDasCalculationsUseCase
{
    public function __construct(
        private readonly DasCalculationService $dasCalculationService,
    ) {}

    public function handle(): Collection
    {
        return $this->dasCalculationService->list();
    }
}
