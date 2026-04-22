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
    public function getAll(int $userId): Collection;

    /**
     * @return Collection<int, BillingInvoice>
     */
    public function getSimulations(int $userId): Collection;

    /**
     * @return Collection<int, BillingInvoiceModel>
     */
    public function getModelsForMonth(CarbonImmutable $referenceMonth, int $userId): Collection;

    /**
     * @return Collection<int, BillingInvoiceModel>
     */
    public function getModelsForPeriod(CarbonImmutable $startDate, CarbonImmutable $endDate, int $userId): Collection;

    /**
     * @param  array<int, int>  $calculationAnnexesById
     */
    public function updateCalculationAnnexes(array $calculationAnnexesById, int $userId): void;

    public function delete(BillingInvoiceModel $billingInvoice): ?bool;

    public function forceDelete(BillingInvoiceModel $billingInvoice): ?bool;

    public function forceDeleteSimulations(int $userId): int;
}
