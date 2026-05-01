<?php

namespace App\Src\ProLabore\Infrastructure;

use App\Models\ProLaboreReceipt as ProLaboreReceiptModel;
use App\Src\ProLabore\Contracts\ProLaboreRepositoryInterface;
use App\Src\ProLabore\Domain\ProLaboreEntry;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Collection;

class EloquentProLaboreRepository implements ProLaboreRepositoryInterface
{
    public function __construct(
        private readonly ProLaboreReceiptModel $model,
        private readonly CacheFactory $cache,
    ) {}

    public function createReceipt(array $attributes): ProLaboreEntry
    {
        $receipt = $this->model->newQuery()->create($attributes);

        return ProLaboreEntry::fromModel($receipt);
    }

    public function getAll(int $userId): Collection
    {
        $receipts = $this->model
            ->newQuery()
            ->where('user_id', $userId)
            ->orderByDesc('reference_month')
            ->get()
            ->map(fn (ProLaboreReceiptModel $receipt): ProLaboreEntry => ProLaboreEntry::fromModel($receipt));

        return $receipts
            ->concat($this->simulationEntries($userId))
            ->sortByDesc('referenceMonth')
            ->values();
    }

    public function replaceSimulationsForPeriod(
        int $userId,
        CarbonImmutable $startMonth,
        CarbonImmutable $endMonth,
        array $monthlyRevenueByMonth,
    ): Collection {
        $remainingRecords = collect($this->simulationRecords($userId))
            ->reject(function (array $record) use ($startMonth, $endMonth): bool {
                $referenceMonth = CarbonImmutable::parse($record['reference_month'])->startOfMonth();

                return $referenceMonth->betweenIncluded($startMonth, $endMonth);
            })
            ->values()
            ->all();

        $now = CarbonImmutable::now();
        $createdRecords = [];

        foreach ($monthlyRevenueByMonth as $referenceMonth => $sourceRevenueBrl) {
            $grossAmountBrl = $this->roundUpToNextTen($sourceRevenueBrl * 0.28);

            $createdRecords[] = [
                'id' => -random_int(1, PHP_INT_MAX),
                'user_id' => $userId,
                'reference_month' => CarbonImmutable::parse($referenceMonth)->startOfMonth()->toDateString(),
                'gross_amount_brl' => $grossAmountBrl,
                'source_revenue_brl' => round($sourceRevenueBrl, 2),
                'source' => 'billing_simulation',
                'is_simulation' => true,
                'created_at' => $now->toISOString(),
                'updated_at' => $now->toISOString(),
            ];
        }

        $this->simulationCache()->forever(
            $this->simulationCacheKey($userId),
            array_values(array_merge($remainingRecords, $createdRecords)),
        );

        return collect($createdRecords)
            ->map(fn (array $record): ProLaboreEntry => ProLaboreEntry::fromSimulationRecord($record))
            ->sortByDesc('referenceMonth')
            ->values();
    }

    public function deleteReceipt(ProLaboreReceiptModel $receipt): ?bool
    {
        return $receipt->delete();
    }

    public function deleteSimulations(int $userId): int
    {
        $deletedCount = $this->simulationEntries($userId)->count();

        $this->simulationCache()->forget($this->simulationCacheKey($userId));

        return $deletedCount;
    }

    /**
     * @return Collection<int, ProLaboreEntry>
     */
    private function simulationEntries(int $userId): Collection
    {
        return collect($this->simulationRecords($userId))
            ->map(fn (array $record): ProLaboreEntry => ProLaboreEntry::fromSimulationRecord($record));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function simulationRecords(int $userId): array
    {
        $records = $this->simulationCache()->get($this->simulationCacheKey($userId), []);

        return is_array($records) ? array_values($records) : [];
    }

    private function roundUpToNextTen(float $amount): float
    {
        return (floor($amount / 10) + 1) * 10;
    }

    private function simulationCacheKey(int $userId): string
    {
        return "pro_labore_simulations:{$userId}";
    }

    private function simulationCache(): CacheRepository
    {
        return $this->cache->store((string) config('pro_labore.simulations.cache_store', 'redis'));
    }
}
