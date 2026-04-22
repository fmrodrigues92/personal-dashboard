<?php

namespace App\Src\Das\Rules;

use App\Models\BillingInvoice;
use App\Src\Das\Rules\Contracts\DasCalculationRuleInterface;
use App\Src\Das\Rules\Data\CalculatedDas;
use App\Src\Das\Rules\Data\CalculatedTaxBreakdown;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Collection;

class SimplesNacionalService2018Rule implements DasCalculationRuleInterface
{
    private const EXEMPT_INTERNATIONAL_COMPONENTS = [
        'iss',
        'cofins',
        'pis_pasep',
    ];

    private const ANNEX_DISTRIBUTIONS = [
        3 => [
            1 => ['limit' => 180000.00, 'nominal_rate' => 0.06, 'deduction' => 0.00, 'components' => ['irpj' => 0.04, 'csll' => 0.035, 'cofins' => 0.1282, 'pis_pasep' => 0.0278, 'cpp' => 0.434, 'iss' => 0.335]],
            2 => ['limit' => 360000.00, 'nominal_rate' => 0.112, 'deduction' => 9360.00, 'components' => ['irpj' => 0.04, 'csll' => 0.035, 'cofins' => 0.1405, 'pis_pasep' => 0.0305, 'cpp' => 0.434, 'iss' => 0.32]],
            3 => ['limit' => 720000.00, 'nominal_rate' => 0.135, 'deduction' => 17640.00, 'components' => ['irpj' => 0.04, 'csll' => 0.035, 'cofins' => 0.1364, 'pis_pasep' => 0.0296, 'cpp' => 0.434, 'iss' => 0.325]],
            4 => ['limit' => 1800000.00, 'nominal_rate' => 0.16, 'deduction' => 35640.00, 'components' => ['irpj' => 0.04, 'csll' => 0.035, 'cofins' => 0.1364, 'pis_pasep' => 0.0296, 'cpp' => 0.434, 'iss' => 0.325]],
            5 => ['limit' => 3600000.00, 'nominal_rate' => 0.21, 'deduction' => 125640.00, 'components' => ['irpj' => 0.0602, 'csll' => 0.0526, 'cofins' => 0.1928, 'pis_pasep' => 0.0418, 'cpp' => 0.6526, 'iss' => null]],
            6 => ['limit' => 4800000.00, 'nominal_rate' => 0.33, 'deduction' => 648000.00, 'components' => ['irpj' => 0.35, 'csll' => 0.155, 'cofins' => 0.1644, 'pis_pasep' => 0.0356, 'cpp' => 0.295, 'iss' => 0.0]],
        ],
        5 => [
            1 => ['limit' => 180000.00, 'nominal_rate' => 0.155, 'deduction' => 0.00, 'components' => ['irpj' => 0.25, 'csll' => 0.15, 'cofins' => 0.141, 'pis_pasep' => 0.0305, 'cpp' => 0.2885, 'iss' => 0.14]],
            2 => ['limit' => 360000.00, 'nominal_rate' => 0.18, 'deduction' => 4500.00, 'components' => ['irpj' => 0.23, 'csll' => 0.15, 'cofins' => 0.141, 'pis_pasep' => 0.0305, 'cpp' => 0.2785, 'iss' => 0.17]],
            3 => ['limit' => 720000.00, 'nominal_rate' => 0.195, 'deduction' => 9900.00, 'components' => ['irpj' => 0.24, 'csll' => 0.15, 'cofins' => 0.1492, 'pis_pasep' => 0.0323, 'cpp' => 0.2385, 'iss' => 0.19]],
            4 => ['limit' => 1800000.00, 'nominal_rate' => 0.205, 'deduction' => 17100.00, 'components' => ['irpj' => 0.21, 'csll' => 0.15, 'cofins' => 0.1574, 'pis_pasep' => 0.0341, 'cpp' => 0.2385, 'iss' => 0.21]],
            5 => ['limit' => 3600000.00, 'nominal_rate' => 0.23, 'deduction' => 62100.00, 'components' => ['irpj' => 0.23, 'csll' => 0.125, 'cofins' => 0.141, 'pis_pasep' => 0.0305, 'cpp' => 0.2385, 'iss' => 0.235]],
            6 => ['limit' => 4800000.00, 'nominal_rate' => 0.305, 'deduction' => 540000.00, 'components' => ['irpj' => 0.35, 'csll' => 0.155, 'cofins' => 0.1644, 'pis_pasep' => 0.0356, 'cpp' => 0.295, 'iss' => 0.0]],
        ],
    ];

    public function version(): string
    {
        return 'simples_nacional_service_2018';
    }

    public function calculate(
        Collection $monthlyInvoices,
        CarbonImmutable $referenceMonth,
        float $rollingRevenueBrl,
        bool $factorRApplied,
    ): CalculatedDas {
        $aggregatedBreakdowns = [];
        $calculationAnnexesByInvoiceId = [];
        $rateSummaryByGroup = [];
        $monthlyRevenueBrl = round($monthlyInvoices->sum('amount_brl'), 2);
        $isProjection = $monthlyInvoices->isNotEmpty() && $monthlyInvoices->every(
            fn (BillingInvoice $billingInvoice): bool => $billingInvoice->is_simulation,
        );

        foreach ($monthlyInvoices as $billingInvoice) {
            $invoiceType = $this->normalizeInvoiceType($billingInvoice->type);
            $calculationAnnex = $this->resolveCalculationAnnex($billingInvoice, $factorRApplied);
            $calculationAnnexesByInvoiceId[$billingInvoice->id] = $calculationAnnex;
            $baseEffectiveRate = $this->baseEffectiveRate($calculationAnnex, $rollingRevenueBrl);
            $componentRates = $this->componentRates(
                annex: $calculationAnnex,
                effectiveRate: $baseEffectiveRate,
                invoiceType: $invoiceType,
                rollingRevenueBrl: $rollingRevenueBrl,
            );
            $appliedEffectiveRate = round(array_sum($componentRates), 10);
            $rateSummaryKey = implode(':', [$calculationAnnex, $invoiceType]);

            if (! array_key_exists($rateSummaryKey, $rateSummaryByGroup)) {
                $rateSummaryByGroup[$rateSummaryKey] = [
                    'annex_used' => $calculationAnnex,
                    'invoice_type' => $invoiceType,
                    'effective_rate_percentage' => round($appliedEffectiveRate * 100, 6),
                    'exempt_components' => $invoiceType === 'international'
                        ? self::EXEMPT_INTERNATIONAL_COMPONENTS
                        : [],
                ];
            }

            foreach ($componentRates as $taxComponentCode => $componentRate) {
                $key = implode(':', [$taxComponentCode, $calculationAnnex, $invoiceType]);

                if (! array_key_exists($key, $aggregatedBreakdowns)) {
                    $aggregatedBreakdowns[$key] = [
                        'tax_component_code' => $taxComponentCode,
                        'annex_used' => $calculationAnnex,
                        'invoice_type' => $invoiceType,
                        'calculated_amount_brl' => 0.0,
                        'rate_percentage' => round($componentRate * 100, 6),
                    ];
                }

                $aggregatedBreakdowns[$key]['calculated_amount_brl'] += $billingInvoice->amount_brl * $componentRate;
            }
        }

        $calculatedTaxBreakdowns = $this->finalizeBreakdowns($aggregatedBreakdowns);

        $dasTotalBrl = round(
            array_sum(
                array_map(
                    fn (CalculatedTaxBreakdown $taxBreakdown): float => $taxBreakdown->calculatedAmountBrl,
                    $calculatedTaxBreakdowns,
                ),
            ),
            2,
        );

        return new CalculatedDas(
            ruleVersion: $this->version(),
            factorRApplied: $factorRApplied,
            monthlyRevenueBrl: $monthlyRevenueBrl,
            dasTotalBrl: $dasTotalBrl,
            isProjection: $isProjection,
            taxBreakdowns: $calculatedTaxBreakdowns,
            calculationAnnexesByInvoiceId: $calculationAnnexesByInvoiceId,
            metadata: [
                'reference_month' => $referenceMonth->toDateString(),
                'rolling_revenue_brl' => round($rollingRevenueBrl, 2),
                'invoice_count' => $monthlyInvoices->count(),
                'factor_r_threshold_percentage' => 28.0,
                'rate_summary' => array_values($rateSummaryByGroup),
                'rate_source' => [
                    'annex_3' => 'https://www.contabilizei.com.br/contabilidade-online/anexo-3-simples-nacional/',
                    'annex_5' => 'https://www.contabilizei.com.br/contabilidade-online/anexo-5-simples-nacional/',
                ],
            ],
        );
    }

    private function resolveCalculationAnnex(BillingInvoice $billingInvoice, bool $factorRApplied): int
    {
        if ($billingInvoice->cnae_annex === null) {
            if ($billingInvoice->is_simulation) {
                return 3;
            }

            throw new DomainException("Billing invoice [{$billingInvoice->id}] is missing cnae_annex.");
        }

        if ($billingInvoice->cnae_annex === 5 && $factorRApplied) {
            return 3;
        }

        if (! array_key_exists($billingInvoice->cnae_annex, self::ANNEX_DISTRIBUTIONS)) {
            throw new DomainException("Unsupported annex [{$billingInvoice->cnae_annex}] for billing invoice [{$billingInvoice->id}].");
        }

        return $billingInvoice->cnae_annex;
    }

    private function normalizeInvoiceType(string $invoiceType): string
    {
        return strtolower(trim($invoiceType));
    }

    private function baseEffectiveRate(int $annex, float $rollingRevenueBrl): float
    {
        $bracket = $this->findBracket($annex, $rollingRevenueBrl);

        return round(
            (($rollingRevenueBrl * $bracket['nominal_rate']) - $bracket['deduction']) / $rollingRevenueBrl,
            10,
        );
    }

    /**
     * @return array<string, float>
     */
    private function componentRates(
        int $annex,
        float $effectiveRate,
        string $invoiceType,
        float $rollingRevenueBrl,
    ): array {
        $bracketIndex = $this->findBracketIndex($annex, $rollingRevenueBrl);
        $distribution = self::ANNEX_DISTRIBUTIONS[$annex][$bracketIndex]['components'];
        $componentRates = [];

        foreach ($distribution as $taxComponentCode => $share) {
            if ($annex === 3 && $bracketIndex === 5 && $taxComponentCode !== 'iss') {
                $componentRates[$taxComponentCode] = max($effectiveRate - 0.05, 0) * $share;

                continue;
            }

            $componentRates[$taxComponentCode] = $share === null ? 0.0 : $effectiveRate * $share;
        }

        if ($annex === 3 && $bracketIndex === 5) {
            $componentRates['iss'] = min($effectiveRate, 0.05);
        }

        if ($invoiceType === 'international') {
            foreach (self::EXEMPT_INTERNATIONAL_COMPONENTS as $taxComponentCode) {
                $componentRates[$taxComponentCode] = 0.0;
            }
        }

        return $componentRates;
    }

    /**
     * @param  array<string, array<string, float|int|string|null>>  $aggregatedBreakdowns
     * @return array<int, CalculatedTaxBreakdown>
     */
    private function finalizeBreakdowns(array $aggregatedBreakdowns): array
    {
        $calculatedTaxBreakdowns = [];
        $roundedTotal = 0.0;

        foreach ($aggregatedBreakdowns as $aggregatedBreakdown) {
            $roundedAmount = round((float) $aggregatedBreakdown['calculated_amount_brl'], 2);
            $roundedTotal += $roundedAmount;

            $calculatedTaxBreakdowns[] = new CalculatedTaxBreakdown(
                taxComponentCode: (string) $aggregatedBreakdown['tax_component_code'],
                annexUsed: (int) $aggregatedBreakdown['annex_used'],
                invoiceType: (string) $aggregatedBreakdown['invoice_type'],
                calculatedAmountBrl: $roundedAmount,
                ratePercentage: (float) $aggregatedBreakdown['rate_percentage'],
            );
        }

        return $calculatedTaxBreakdowns;
    }

    /**
     * @return array{limit: float, nominal_rate: float, deduction: float}
     */
    private function findBracket(int $annex, float $rollingRevenueBrl): array
    {
        foreach (self::ANNEX_DISTRIBUTIONS[$annex] as $bracket) {
            if ($rollingRevenueBrl <= $bracket['limit']) {
                return [
                    'limit' => $bracket['limit'],
                    'nominal_rate' => $bracket['nominal_rate'],
                    'deduction' => $bracket['deduction'],
                ];
            }
        }

        throw new DomainException("Rolling revenue [{$rollingRevenueBrl}] exceeds Simples Nacional limit for annex [{$annex}].");
    }

    private function findBracketIndex(int $annex, float $rollingRevenueBrl): int
    {
        foreach (array_values(self::ANNEX_DISTRIBUTIONS[$annex]) as $index => $bracket) {
            if ($rollingRevenueBrl <= $bracket['limit']) {
                return $index + 1;
            }
        }

        throw new DomainException("Rolling revenue [{$rollingRevenueBrl}] exceeds Simples Nacional limit for annex [{$annex}].");
    }
}
