<?php

namespace App\Src\Das\UseCases;

use App\Src\Das\Services\DasCalculationService;

class GetDasTimelineUseCase
{
    public function __construct(
        private readonly DasCalculationService $dasCalculationService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, array<string, bool|float|int|string|null>>
     */
    public function handle(array $data): array
    {
        return $this->dasCalculationService->timeline($data);
    }
}
