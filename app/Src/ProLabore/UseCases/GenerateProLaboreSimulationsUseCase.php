<?php

namespace App\Src\ProLabore\UseCases;

use App\Src\ProLabore\Domain\ProLaboreEntry;
use App\Src\ProLabore\Services\ProLaboreService;
use Illuminate\Support\Collection;

class GenerateProLaboreSimulationsUseCase
{
    public function __construct(
        private readonly ProLaboreService $proLaboreService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return Collection<int, ProLaboreEntry>
     */
    public function handle(array $data, int $userId): Collection
    {
        return $this->proLaboreService->generateSimulations($data, $userId);
    }
}
