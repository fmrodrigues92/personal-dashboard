<?php

namespace App\Src\Billing\UseCases;

use App\Src\Billing\Services\BillingInvoiceService;
use Illuminate\Support\Collection;

class ListBillingInvoiceSimulationsUseCase
{
    public function __construct(
        private readonly BillingInvoiceService $billingInvoiceService,
    ) {}

    public function handle(int $userId): Collection
    {
        return $this->billingInvoiceService->listSimulations($userId);
    }
}
