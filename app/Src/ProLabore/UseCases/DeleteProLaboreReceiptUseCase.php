<?php

namespace App\Src\ProLabore\UseCases;

use App\Models\ProLaboreReceipt;
use App\Src\ProLabore\Services\ProLaboreService;

class DeleteProLaboreReceiptUseCase
{
    public function __construct(
        private readonly ProLaboreService $proLaboreService,
    ) {}

    public function handle(ProLaboreReceipt $receipt): void
    {
        $this->proLaboreService->deleteReceipt($receipt);
    }
}
