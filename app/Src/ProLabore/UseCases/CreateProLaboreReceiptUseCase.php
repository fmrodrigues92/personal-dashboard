<?php

namespace App\Src\ProLabore\UseCases;

use App\Src\ProLabore\Domain\ProLaboreEntry;
use App\Src\ProLabore\Services\ProLaboreService;

class CreateProLaboreReceiptUseCase
{
    public function __construct(
        private readonly ProLaboreService $proLaboreService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, int $userId): ProLaboreEntry
    {
        return $this->proLaboreService->createReceipt($data, $userId);
    }
}
