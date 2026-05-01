<?php

namespace App\Src\ProLabore\Services;

use App\Models\ProLaboreReceipt as ProLaboreReceiptModel;
use App\Src\Billing\Contracts\BillingInvoiceRepositoryInterface;
use App\Src\ProLabore\Contracts\ProLaboreRepositoryInterface;
use App\Src\ProLabore\Domain\ProLaboreEntry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class ProLaboreService
{
    public function __construct(
        private readonly ProLaboreRepositoryInterface $proLaboreRepository,
        private readonly BillingInvoiceRepositoryInterface $billingInvoiceRepository,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function createReceipt(array $data, int $userId): ProLaboreEntry
    {
        return $this->proLaboreRepository->createReceipt([
            'user_id' => $userId,
            'reference_month' => CarbonImmutable::parse($data['reference_month'])->startOfMonth()->toDateString(),
            'gross_amount_brl' => $data['gross_amount_brl'],
            'notes' => $data['notes'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return Collection<int, ProLaboreEntry>
     */
    public function generateSimulations(array $data, int $userId): Collection
    {
        $startMonth = CarbonImmutable::parse($data['start_month'])->startOfMonth();
        $endMonth = CarbonImmutable::parse($data['end_month'])->startOfMonth();
        $simulatedBillingInvoices = $this->billingInvoiceRepository
            ->getModelsForPeriod($startMonth, $endMonth->endOfMonth(), $userId)
            ->filter(fn ($billingInvoice): bool => $billingInvoice->is_simulation);

        $monthlyRevenueByMonth = $simulatedBillingInvoices
            ->groupBy(fn ($billingInvoice): string => CarbonImmutable::instance($billingInvoice->billing_date)->startOfMonth()->toDateString())
            ->map(fn (Collection $monthInvoices): float => round((float) $monthInvoices->sum('amount_brl'), 2))
            ->filter(fn (float $amountBrl): bool => $amountBrl > 0)
            ->all();

        return $this->proLaboreRepository->replaceSimulationsForPeriod(
            userId: $userId,
            startMonth: $startMonth,
            endMonth: $endMonth,
            monthlyRevenueByMonth: $monthlyRevenueByMonth,
        );
    }

    /**
     * @return Collection<int, ProLaboreEntry>
     */
    public function listEntries(int $userId): Collection
    {
        return $this->proLaboreRepository->getAll($userId);
    }

    public function deleteReceipt(ProLaboreReceiptModel $receipt): void
    {
        $this->proLaboreRepository->deleteReceipt($receipt);
    }

    public function deleteSimulations(int $userId): int
    {
        return $this->proLaboreRepository->deleteSimulations($userId);
    }
}
