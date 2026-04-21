<?php

namespace App\Src\Billing\UseCases;

use App\Models\BillingInvoice;
use App\Src\Billing\Services\BillingInvoiceService;

class DeleteBillingInvoiceUseCase
{
    public function __construct(
        private readonly BillingInvoiceService $billingInvoiceService,
    ) {}

    public function handle(BillingInvoice $billingInvoice): void
    {
        $this->billingInvoiceService->delete($billingInvoice);
    }
}
