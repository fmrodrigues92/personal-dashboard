<?php

namespace App\Src\ProLabore\Contracts;

use App\Models\ProLaboreReceipt as ProLaboreReceiptModel;
use App\Src\ProLabore\Domain\ProLaboreEntry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

interface ProLaboreRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createReceipt(array $attributes): ProLaboreEntry;

    /**
     * @return Collection<int, ProLaboreEntry>
     */
    public function getAll(int $userId): Collection;

    /**
     * @param  array<string, float>  $monthlyRevenueByMonth
     * @return Collection<int, ProLaboreEntry>
     */
    public function replaceSimulationsForPeriod(
        int $userId,
        CarbonImmutable $startMonth,
        CarbonImmutable $endMonth,
        array $monthlyRevenueByMonth,
    ): Collection;

    public function deleteReceipt(ProLaboreReceiptModel $receipt): ?bool;

    public function deleteSimulations(int $userId): int;
}
