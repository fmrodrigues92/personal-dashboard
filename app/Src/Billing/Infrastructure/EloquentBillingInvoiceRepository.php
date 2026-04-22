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

    public function getAll(int $userId): Collection
    {
        return $this->model
            ->newQuery()
            ->where('user_id', $userId)
            ->orderByDesc('billing_date')
            ->get()
            ->map(fn (BillingInvoiceModel $billingInvoice): BillingInvoice => BillingInvoice::fromModel($billingInvoice));
    }

    public function getSimulations(int $userId): Collection
    {
        return $this->model
            ->newQuery()
            ->where('user_id', $userId)
            ->where('is_simulation', true)
            ->orderByDesc('billing_date')
            ->get()
            ->map(fn (BillingInvoiceModel $billingInvoice): BillingInvoice => BillingInvoice::fromModel($billingInvoice));
    }

    public function getModelsForMonth(CarbonImmutable $referenceMonth, int $userId): Collection
    {
        return $this->getModelsForPeriod(
            $referenceMonth->startOfMonth(),
            $referenceMonth->endOfMonth(),
            $userId,
        );
    }

    public function getModelsForPeriod(CarbonImmutable $startDate, CarbonImmutable $endDate, int $userId): Collection
    {
        return $this->model
            ->newQuery()
            ->where('user_id', $userId)
            ->whereBetween('billing_date', [$startDate, $endDate])
            ->orderBy('billing_date')
            ->get();
    }

    public function updateCalculationAnnexes(array $calculationAnnexesById, int $userId): void
    {
        foreach ($calculationAnnexesById as $billingInvoiceId => $calculationAnnex) {
            $this->model
                ->newQuery()
                ->where('user_id', $userId)
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

    public function forceDeleteSimulations(int $userId): int
    {
        return $this->model
            ->newQuery()
            ->withTrashed()
            ->where('user_id', $userId)
            ->where('is_simulation', true)
            ->forceDelete();
    }
}
