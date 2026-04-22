<?php

namespace App\Src\Billing\Services;

use App\Models\BillingInvoice as BillingInvoiceModel;
use App\Src\Billing\Contracts\BillingInvoiceRepositoryInterface;
use App\Src\Billing\Domain\BillingInvoice;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class BillingInvoiceService
{
    public function __construct(
        private readonly BillingInvoiceRepositoryInterface $billingInvoiceRepository,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function createRealInvoice(array $data, int $userId): BillingInvoice
    {
        $isInternational = $data['type'] === 'international';

        return $this->billingInvoiceRepository->create([
            'user_id' => $userId,
            'billing_date' => $data['billing_date'],
            'type' => $data['type'],
            'cnae' => $data['cnae'],
            'cnae_annex' => $data['cnae_annex'],
            'cnae_calculation' => null,
            'customer_name' => $data['customer_name'],
            'customer_external_id' => $data['customer_external_id'],
            'amount_brl' => $data['amount_brl'],
            'amount_usd' => $isInternational ? $data['amount_usd'] : null,
            'usd_brl_exchange_rate' => $isInternational ? $data['usd_brl_exchange_rate'] : null,
            'is_simulation' => false,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return Collection<int, BillingInvoice>
     */
    public function createSimulations(array $data, int $userId): Collection
    {
        $startDate = CarbonImmutable::parse($data['start_date'])->startOfMonth()->startOfDay();
        $endDate = CarbonImmutable::parse($data['end_date'])->startOfMonth()->startOfDay();
        $records = [];

        for ($billingDate = $startDate; $billingDate->lessThanOrEqualTo($endDate); $billingDate = $billingDate->addMonth()) {
            $records[] = [
                'user_id' => $userId,
                'billing_date' => $billingDate,
                'type' => $data['type'],
                'cnae' => null,
                'cnae_annex' => null,
                'cnae_calculation' => null,
                'customer_name' => null,
                'customer_external_id' => null,
                'amount_brl' => $data['amount_brl'],
                'amount_usd' => null,
                'usd_brl_exchange_rate' => null,
                'is_simulation' => true,
            ];
        }

        return $this->billingInvoiceRepository->createMany($records);
    }

    /**
     * @return Collection<int, BillingInvoice>
     */
    public function listInvoices(int $userId): Collection
    {
        return $this->billingInvoiceRepository->getAll($userId);
    }

    /**
     * @return Collection<int, BillingInvoice>
     */
    public function listSimulations(int $userId): Collection
    {
        return $this->billingInvoiceRepository->getSimulations($userId);
    }

    public function delete(BillingInvoiceModel $billingInvoice): void
    {
        if ($billingInvoice->is_simulation) {
            $this->billingInvoiceRepository->forceDelete($billingInvoice);

            return;
        }

        $this->billingInvoiceRepository->delete($billingInvoice);
    }

    public function deleteSimulations(int $userId): int
    {
        return $this->billingInvoiceRepository->forceDeleteSimulations($userId);
    }
}
