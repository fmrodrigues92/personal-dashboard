<?php

namespace App\Src\Billing\Contracts;

use App\Models\BillingInvoice as BillingInvoiceModel;
use App\Src\Billing\Domain\BillingInvoice;
use Carbon\CarbonImmutable;
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

    /**
     * @return Collection<int, BillingInvoiceModel>
     */
    public function getModelsForMonth(CarbonImmutable $referenceMonth): Collection;

    /**
     * @return Collection<int, BillingInvoiceModel>
     */
    public function getModelsForPeriod(CarbonImmutable $startDate, CarbonImmutable $endDate): Collection;

    /**
     * @param  array<int, int>  $calculationAnnexesById
     */
    public function updateCalculationAnnexes(array $calculationAnnexesById): void;

    public function delete(BillingInvoiceModel $billingInvoice): ?bool;

    public function forceDelete(BillingInvoiceModel $billingInvoice): ?bool;

    public function forceDeleteSimulations(): int;
}
