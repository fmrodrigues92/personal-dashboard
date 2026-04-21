<?php

namespace App\Src\Billing\Domain;

use App\Models\BillingInvoice as BillingInvoiceModel;
use Carbon\CarbonImmutable;

readonly class BillingInvoice
{
    public function __construct(
        public int $id,
        public CarbonImmutable $billingDate,
        public string $type,
        public ?string $cnae,
        public ?int $cnaeAnnex,
        public ?int $cnaeCalculation,
        public ?string $customerName,
        public ?string $customerExternalId,
        public float $amountBrl,
        public ?float $amountUsd,
        public ?float $usdBrlExchangeRate,
        public bool $isSimulation,
        public CarbonImmutable $createdAt,
        public CarbonImmutable $updatedAt,
        public ?CarbonImmutable $deletedAt,
    ) {}

    public static function fromModel(BillingInvoiceModel $billingInvoice): self
    {
        return new self(
            id: $billingInvoice->id,
            billingDate: $billingInvoice->billing_date,
            type: $billingInvoice->type,
            cnae: $billingInvoice->cnae,
            cnaeAnnex: $billingInvoice->cnae_annex,
            cnaeCalculation: $billingInvoice->cnae_calculation,
            customerName: $billingInvoice->customer_name,
            customerExternalId: $billingInvoice->customer_external_id,
            amountBrl: $billingInvoice->amount_brl,
            amountUsd: $billingInvoice->amount_usd,
            usdBrlExchangeRate: $billingInvoice->usd_brl_exchange_rate,
            isSimulation: $billingInvoice->is_simulation,
            createdAt: $billingInvoice->created_at,
            updatedAt: $billingInvoice->updated_at,
            deletedAt: $billingInvoice->deleted_at,
        );
    }

    /**
     * @return array<string, bool|float|int|string|null>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'billing_date' => $this->billingDate->toISOString(),
            'type' => $this->type,
            'cnae' => $this->cnae,
            'cnae_annex' => $this->cnaeAnnex,
            'cnae_calculation' => $this->cnaeCalculation,
            'customer_name' => $this->customerName,
            'customer_external_id' => $this->customerExternalId,
            'amount_brl' => $this->amountBrl,
            'amount_usd' => $this->amountUsd,
            'usd_brl_exchange_rate' => $this->usdBrlExchangeRate,
            'is_simulation' => $this->isSimulation,
            'created_at' => $this->createdAt->toISOString(),
            'updated_at' => $this->updatedAt->toISOString(),
            'deleted_at' => $this->deletedAt?->toISOString(),
        ];
    }
}
