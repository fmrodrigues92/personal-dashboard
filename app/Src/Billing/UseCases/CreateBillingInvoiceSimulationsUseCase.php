<?php

namespace App\Src\Billing\UseCases;

use App\Src\Billing\Services\BillingInvoiceService;
use Illuminate\Support\Collection;

class CreateBillingInvoiceSimulationsUseCase
{
    public function __construct(
        private readonly BillingInvoiceService $billingInvoiceService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, int $userId): Collection
    {
        return $this->billingInvoiceService->createSimulations($data, $userId);
    }
}
