<?php

namespace App\Src\Billing\Contracts;

use App\Models\BillingInvoice as BillingInvoiceModel;
use App\Src\Billing\Domain\BillingInvoice;
use Illuminate\Support\Collection;

interface BillingInvoiceRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): BillingInvoice;

    /**
     * @param  array<int, array<string, mixed>>  $records
     * @return Collection<int, BillingInvoice>
     */
    public function createMany(array $records): Collection;

    /**
     * @return Collection<int, BillingInvoice>
     */
    public function getAll(): Collection;

    /**
     * @return Collection<int, BillingInvoice>
     */
    public function getSimulations(): Collection;

    public function delete(BillingInvoiceModel $billingInvoice): ?bool;

    public function forceDelete(BillingInvoiceModel $billingInvoice): ?bool;

    public function forceDeleteSimulations(): int;
}
