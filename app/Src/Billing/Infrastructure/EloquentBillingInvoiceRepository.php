<?php

namespace App\Src\Billing\Infrastructure;

use App\Models\BillingInvoice as BillingInvoiceModel;
use App\Src\Billing\Contracts\BillingInvoiceRepositoryInterface;
use App\Src\Billing\Domain\BillingInvoice;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Collection;

class EloquentBillingInvoiceRepository implements BillingInvoiceRepositoryInterface
{
    public function __construct(
        private readonly BillingInvoiceModel $model,
        private readonly CacheFactory $cache,
    ) {}

    public function create(array $attributes): BillingInvoice
    {
        if (($attributes['is_simulation'] ?? false) === true) {
            return BillingInvoice::fromModel($this->storeSimulation($attributes));
        }

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
        $realInvoices = $this->model
            ->newQuery()
            ->where('user_id', $userId)
            ->where('is_simulation', false)
            ->orderByDesc('billing_date')
            ->get()
            ->map(fn (BillingInvoiceModel $billingInvoice): BillingInvoice => BillingInvoice::fromModel($billingInvoice));

        return $realInvoices
            ->concat($this->getSimulations($userId))
            ->sortByDesc('billingDate')
            ->values();
    }

    public function getSimulations(int $userId): Collection
    {
        return $this->simulationModels($userId)
            ->sortByDesc('billing_date')
            ->values()
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
        $realInvoices = $this->model
            ->newQuery()
            ->where('user_id', $userId)
            ->where('is_simulation', false)
            ->whereBetween('billing_date', [$startDate, $endDate])
            ->orderBy('billing_date')
            ->get();

        $simulations = $this->simulationModels($userId)
            ->filter(function (BillingInvoiceModel $billingInvoice) use ($startDate, $endDate): bool {
                $billingDate = CarbonImmutable::instance($billingInvoice->billing_date);

                return $billingDate->betweenIncluded($startDate, $endDate);
            });

        return $realInvoices
            ->concat($simulations)
            ->sortBy('billing_date')
            ->values();
    }

    public function updateCalculationAnnexes(array $calculationAnnexesById, int $userId): void
    {
        foreach ($calculationAnnexesById as $billingInvoiceId => $calculationAnnex) {
            if ($billingInvoiceId < 1) {
                continue;
            }

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
        $deletedCount = $this->simulationModels($userId)->count();

        $this->simulationCache()->forget($this->simulationCacheKey($userId));

        $legacyDeletedCount = $this->model
            ->newQuery()
            ->withTrashed()
            ->where('user_id', $userId)
            ->where('is_simulation', true)
            ->forceDelete();

        return $deletedCount + $legacyDeletedCount;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function storeSimulation(array $attributes): BillingInvoiceModel
    {
        $userId = (int) $attributes['user_id'];
        $now = CarbonImmutable::now();
        $record = array_merge($attributes, [
            'id' => -random_int(1, PHP_INT_MAX),
            'billing_date' => CarbonImmutable::parse($attributes['billing_date'])->toISOString(),
            'cnae' => null,
            'cnae_annex' => null,
            'cnae_calculation' => null,
            'customer_name' => null,
            'customer_external_id' => null,
            'amount_usd' => null,
            'usd_brl_exchange_rate' => null,
            'is_simulation' => true,
            'created_at' => $now->toISOString(),
            'updated_at' => $now->toISOString(),
            'deleted_at' => null,
        ]);

        $records = $this->simulationRecords($userId);
        $records[] = $record;

        $this->simulationCache()->forever($this->simulationCacheKey($userId), $records);

        return $this->simulationModelFromRecord($record);
    }

    /**
     * @return Collection<int, BillingInvoiceModel>
     */
    private function simulationModels(int $userId): Collection
    {
        return collect($this->simulationRecords($userId))
            ->map(fn (array $record): BillingInvoiceModel => $this->simulationModelFromRecord($record));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function simulationRecords(int $userId): array
    {
        $records = $this->simulationCache()->get($this->simulationCacheKey($userId), []);

        return is_array($records) ? array_values($records) : [];
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function simulationModelFromRecord(array $record): BillingInvoiceModel
    {
        $billingInvoice = $this->model->newInstance();
        $billingInvoice->forceFill([
            'id' => (int) $record['id'],
            'user_id' => (int) $record['user_id'],
            'billing_date' => $record['billing_date'],
            'type' => (string) $record['type'],
            'cnae' => $record['cnae'] ?? null,
            'cnae_annex' => $record['cnae_annex'] ?? null,
            'cnae_calculation' => $record['cnae_calculation'] ?? null,
            'customer_name' => $record['customer_name'] ?? null,
            'customer_external_id' => $record['customer_external_id'] ?? null,
            'amount_brl' => (float) $record['amount_brl'],
            'amount_usd' => $record['amount_usd'] ?? null,
            'usd_brl_exchange_rate' => $record['usd_brl_exchange_rate'] ?? null,
            'is_simulation' => true,
            'created_at' => $record['created_at'],
            'updated_at' => $record['updated_at'],
            'deleted_at' => $record['deleted_at'] ?? null,
        ]);
        $billingInvoice->syncOriginal();

        return $billingInvoice;
    }

    private function simulationCacheKey(int $userId): string
    {
        return "billing_invoice_simulations:{$userId}";
    }

    private function simulationCache(): CacheRepository
    {
        return $this->cache->store((string) config('billing.future_invoices.cache_store', 'redis'));
    }
}
