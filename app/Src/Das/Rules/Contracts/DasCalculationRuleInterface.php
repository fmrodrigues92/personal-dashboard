<?php

namespace App\Src\Das\Rules\Contracts;

use App\Models\BillingInvoice;
use App\Src\Das\Rules\Data\CalculatedDas;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

interface DasCalculationRuleInterface
{
    public function version(): string;

    /**
     * @param  Collection<int, BillingInvoice>  $monthlyInvoices
     */
    public function calculate(
        Collection $monthlyInvoices,
        CarbonImmutable $referenceMonth,
        float $rollingRevenueBrl,
        bool $factorRApplied,
    ): CalculatedDas;
}
