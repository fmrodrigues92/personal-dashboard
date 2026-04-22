<?php

namespace App\Src\Billing\UseCases;

use App\Src\Billing\Domain\BillingInvoice;
use App\Src\Billing\Services\BillingInvoiceService;

class CreateBillingInvoiceUseCase
{
    public function __construct(
        private readonly BillingInvoiceService $billingInvoiceService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, int $userId): BillingInvoice
    {
        return $this->billingInvoiceService->createRealInvoice($data, $userId);
    }
}
