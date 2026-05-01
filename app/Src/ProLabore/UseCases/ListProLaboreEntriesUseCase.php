<?php

namespace App\Src\ProLabore\UseCases;

use App\Src\ProLabore\Domain\ProLaboreEntry;
use App\Src\ProLabore\Services\ProLaboreService;
use Illuminate\Support\Collection;

class ListProLaboreEntriesUseCase
{
    public function __construct(
        private readonly ProLaboreService $proLaboreService,
    ) {}

    /**
     * @return Collection<int, ProLaboreEntry>
     */
    public function handle(int $userId): Collection
    {
        return $this->proLaboreService->listEntries($userId);
    }
}
