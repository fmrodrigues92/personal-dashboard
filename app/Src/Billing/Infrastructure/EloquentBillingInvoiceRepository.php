<?php

namespace App\Src\Billing\Infrastructure;

use App\Models\BillingInvoice as BillingInvoiceModel;
use App\Src\Billing\Contracts\BillingInvoiceRepositoryInterface;
use App\Src\Billing\Domain\BillingInvoice;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class EloquentBillingInvoiceRepository implements BillingInvoiceRepositoryInterface
{
    public function __construct(
        private readonly BillingInvoiceModel $model,
    ) {}

    public function create(array $attributes): BillingInvoice
    {
        $billingInvoice = $this->model->newQuery()->create($attributes);

        return BillingInvoice::fromModel($billingInvoice);
    }

    public function createMany(array $records): Collection
    {
        return collect($records)
            ->map(fn (array $attributes): BillingInvoice => $this->create($attributes));
    }

    public function getAll(): Collection
    {
        return $this->model
            ->newQuery()
            ->orderByDesc('billing_date')
            ->get()
            ->map(fn (BillingInvoiceModel $billingInvoice): BillingInvoice => BillingInvoice::fromModel($billingInvoice));
    }

    public function getSimulations(): Collection
    {
        return $this->model
            ->newQuery()
            ->where('is_simulation', true)
            ->orderByDesc('billing_date')
            ->get()
            ->map(fn (BillingInvoiceModel $billingInvoice): BillingInvoice => BillingInvoice::fromModel($billingInvoice));
    }

    public function getModelsForMonth(CarbonImmutable $referenceMonth): Collection
    {
        return $this->getModelsForPeriod(
            $referenceMonth->startOfMonth(),
            $referenceMonth->endOfMonth(),
        );
    }

    public function getModelsForPeriod(CarbonImmutable $startDate, CarbonImmutable $endDate): Collection
    {
        return $this->model
            ->newQuery()
            ->whereBetween('billing_date', [$startDate, $endDate])
            ->orderBy('billing_date')
            ->get();
    }

    public function updateCalculationAnnexes(array $calculationAnnexesById): void
    {
        foreach ($calculationAnnexesById as $billingInvoiceId => $calculationAnnex) {
            $this->model
                ->newQuery()
                ->whereKey($billingInvoiceId)
                ->update([
                    'cnae_calculation' => $calculationAnnex,
                ]);
        }
    }

    public function delete(BillingInvoiceModel $billingInvoice): ?bool
    {
        return $billingInvoice->delete();
    }

    public function forceDelete(BillingInvoiceModel $billingInvoice): ?bool
    {
        return $billingInvoice->forceDelete();
    }

    public function forceDeleteSimulations(): int
    {
        return $this->model
            ->newQuery()
            ->withTrashed()
            ->where('is_simulation', true)
            ->forceDelete();
    }
}
