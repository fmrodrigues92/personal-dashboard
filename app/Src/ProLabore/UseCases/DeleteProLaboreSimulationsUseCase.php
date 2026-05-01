<?php

namespace App\Src\ProLabore\UseCases;

use App\Src\ProLabore\Services\ProLaboreService;

class DeleteProLaboreSimulationsUseCase
{
    public function __construct(
        private readonly ProLaboreService $proLaboreService,
    ) {}

    public function handle(int $userId): int
    {
        return $this->proLaboreService->deleteSimulations($userId);
    }
}
