<?php

namespace App\Src\Billing\UseCases;

use App\Src\Billing\Services\BillingInvoiceService;

class DeleteBillingInvoiceSimulationsUseCase
{
    public function __construct(
        private readonly BillingInvoiceService $billingInvoiceService,
    ) {}

    public function handle(int $userId): int
    {
        return $this->billingInvoiceService->deleteSimulations($userId);
    }
}
