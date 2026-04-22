<?php

namespace App\Src\Das\Services;

use App\Models\BillingInvoice;
use App\Models\DasCalculationTaxBreakdown as DasCalculationTaxBreakdownModel;
use App\Src\Billing\Contracts\BillingInvoiceRepositoryInterface;
use App\Src\Das\Contracts\DasCalculationRepositoryInterface;
use App\Src\Das\Domain\DasCalculation;
use App\Src\Das\Domain\DasCalculationTaxBreakdown;
use App\Src\Das\Rules\Contracts\FactorREvaluatorInterface;
use App\Src\Das\Rules\DasCalculationRuleResolver;
use App\Src\Das\Rules\Data\CalculatedDas;
use App\Src\Das\Rules\Data\CalculatedTaxBreakdown;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class DasCalculationService
{
    public function __construct(
        private readonly BillingInvoiceRepositoryInterface $billingInvoiceRepository,
        private readonly DasCalculationRepositoryInterface $dasCalculationRepository,
        private readonly DasCalculationRuleResolver $dasCalculationRuleResolver,
        private readonly FactorREvaluatorInterface $factorREvaluator,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function calculateAndPersist(array $data): DasCalculation
    {
        $referenceMonth = CarbonImmutable::parse($data['reference_month'])->startOfMonth();
        $currentMonth = CarbonImmutable::now()->startOfMonth();
        $rule = $this->dasCalculationRuleResolver->resolve($data['rule_version'] ?? null);
        $monthlyInvoices = $this->relevantInvoicesForMonth($referenceMonth, $currentMonth);

        if ($monthlyInvoices->isEmpty()) {
            throw ValidationException::withMessages([
                'reference_month' => 'No billing invoices found for the requested month.',
            ]);
        }

        $rollingRevenueBreakdown = $this->rollingRevenueBreakdownForMonth($referenceMonth, $currentMonth);

        $factorRApplied = $this->factorREvaluator->passes($referenceMonth);
        $calculatedDas = $rule->calculate(
            $monthlyInvoices,
            $referenceMonth,
            $this->baseForScenarioCalculation($rollingRevenueBreakdown['total_brl']),
            $factorRApplied,
        );
        $accountingDas = $rule->calculate(
            $monthlyInvoices,
            $referenceMonth,
            $this->baseForScenarioCalculation($rollingRevenueBreakdown['national_brl']),
            $factorRApplied,
        );

        $this->billingInvoiceRepository->updateCalculationAnnexes($calculatedDas->calculationAnnexesByInvoiceId);

        $metadata = array_merge(
            $calculatedDas->metadata,
            $this->comparisonMetadata($calculatedDas, $accountingDas, $rollingRevenueBreakdown),
        );

        return $this->dasCalculationRepository->save(
            attributes: [
                'reference_month' => $referenceMonth->toDateString(),
                'rule_version' => $calculatedDas->ruleVersion,
                'factor_r_applied' => $calculatedDas->factorRApplied,
                'monthly_revenue_brl' => $calculatedDas->monthlyRevenueBrl,
                'das_total_brl' => $calculatedDas->dasTotalBrl,
                'is_projection' => $calculatedDas->isProjection,
                'metadata' => $metadata,
            ],
            taxBreakdowns: array_map(
                fn (CalculatedTaxBreakdown $taxBreakdown): array => [
                    'tax_component_code' => $taxBreakdown->taxComponentCode,
                    'annex_used' => $taxBreakdown->annexUsed,
                    'invoice_type' => $taxBreakdown->invoiceType,
                    'calculated_amount_brl' => $taxBreakdown->calculatedAmountBrl,
                    'adjusted_amount_brl' => null,
                    'rate_percentage' => $taxBreakdown->ratePercentage,
                ],
                $calculatedDas->taxBreakdowns,
            ),
        );
    }

    /**
     * @return Collection<int, DasCalculation>
     */
    public function list(): Collection
    {
        return $this->dasCalculationRepository->getAll();
    }

    public function show(int $id): DasCalculation
    {
        $dasCalculation = $this->dasCalculationRepository->findById($id);

        if ($dasCalculation === null) {
            abort(404);
        }

        return $dasCalculation;
    }

    public function updateTaxBreakdown(
        DasCalculationTaxBreakdownModel $taxBreakdown,
        float $adjustedAmountBrl,
    ): DasCalculation {
        if ($taxBreakdown->dasCalculation->is_projection) {
            throw ValidationException::withMessages([
                'adjusted_amount_brl' => 'Projected DAS calculations can not be manually corrected.',
            ]);
        }

        return $this->dasCalculationRepository->updateTaxBreakdown($taxBreakdown, $adjustedAmountBrl);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, array<string, bool|float|int|string|null>>
     */
    public function timeline(array $data): array
    {
        $hasReferenceMonth = array_key_exists('reference_month', $data);
        $referenceMonth = isset($data['reference_month'])
            ? CarbonImmutable::parse($data['reference_month'])->startOfMonth()
            : CarbonImmutable::now()->startOfMonth();

        $monthsBefore = (int) ($data['months_before'] ?? ($hasReferenceMonth ? 0 : 12));
        $monthsAfter = (int) ($data['months_after'] ?? ($hasReferenceMonth ? 0 : 12));
        $rule = $this->dasCalculationRuleResolver->resolve($data['rule_version'] ?? null);
        $currentMonth = CarbonImmutable::now()->startOfMonth();
        $items = [];

        for ($offset = -$monthsBefore; $offset <= $monthsAfter; $offset++) {
            $month = $referenceMonth->addMonths($offset);
            $expectedProjection = $month->greaterThan($currentMonth);
            $storedCalculation = $this->dasCalculationRepository->findStoredForMonth(
                referenceMonth: $month,
                ruleVersion: $rule->version(),
                isProjection: $expectedProjection,
            );

            if ($storedCalculation === null) {
                $storedCalculation = $this->dasCalculationRepository->findStoredForMonth(
                    referenceMonth: $month,
                    ruleVersion: $rule->version(),
                    isProjection: ! $expectedProjection,
                );
            }

            if ($storedCalculation !== null) {
                $fallback = $this->previewMonth($month, $rule->version(), $currentMonth);

                $items[] = [
                    'reference_month' => $storedCalculation->referenceMonth->toDateString(),
                    'das_total_brl' => $storedCalculation->dasTotalBrl,
                    'monthly_revenue_brl' => $storedCalculation->monthlyRevenueBrl,
                    'is_projection' => $storedCalculation->isProjection,
                    'rule_version' => $storedCalculation->ruleVersion,
                    'das_calculation_id' => $storedCalculation->id,
                    'rbt12_national_brl' => $storedCalculation->metadata['rbt12']['national_brl'] ?? $fallback['rbt12_national_brl'],
                    'rbt12_international_brl' => $storedCalculation->metadata['rbt12']['international_brl'] ?? $fallback['rbt12_international_brl'],
                    'rbt12_total_brl' => $storedCalculation->metadata['rbt12']['total_brl'] ?? $fallback['rbt12_total_brl'],
                    'das_real' => $storedCalculation->metadata['das_real'] ?? $this->serializeStoredScenario($storedCalculation),
                    'das_contabilidade' => $storedCalculation->metadata['das_contabilidade'] ?? $fallback['das_contabilidade'],
                ];

                continue;
            }

            $preview = $this->previewMonth($month, $rule->version(), $currentMonth);
            $items[] = $preview;
        }

        return $items;
    }

    /**
     * @return array<string, bool|float|int|string|null>
     */
    private function previewMonth(
        CarbonImmutable $referenceMonth,
        string $ruleVersion,
        CarbonImmutable $currentMonth,
    ): array {
        $rule = $this->dasCalculationRuleResolver->resolve($ruleVersion);
        $monthlyInvoices = $this->relevantInvoicesForMonth($referenceMonth, $currentMonth);
        $rollingRevenueBreakdown = $this->rollingRevenueBreakdownForMonth($referenceMonth, $currentMonth);

        if ($monthlyInvoices->isEmpty()) {
            return [
                'reference_month' => $referenceMonth->toDateString(),
                'das_total_brl' => 0.0,
                'monthly_revenue_brl' => 0.0,
                'is_projection' => $referenceMonth->greaterThan($currentMonth),
                'rule_version' => $rule->version(),
                'das_calculation_id' => null,
                'rbt12_national_brl' => $rollingRevenueBreakdown['national_brl'],
                'rbt12_international_brl' => $rollingRevenueBreakdown['international_brl'],
                'rbt12_total_brl' => $rollingRevenueBreakdown['total_brl'],
                'das_real' => $this->emptyScenario($rollingRevenueBreakdown['total_brl']),
                'das_contabilidade' => $this->emptyScenario($rollingRevenueBreakdown['national_brl']),
            ];
        }

        $factorRApplied = $this->factorREvaluator->passes($referenceMonth);
        $calculatedDas = $rule->calculate(
            $monthlyInvoices,
            $referenceMonth,
            $this->baseForScenarioCalculation($rollingRevenueBreakdown['total_brl']),
            $factorRApplied,
        );
        $accountingDas = $rule->calculate(
            $monthlyInvoices,
            $referenceMonth,
            $this->baseForScenarioCalculation($rollingRevenueBreakdown['national_brl']),
            $factorRApplied,
        );

        return [
            'reference_month' => $referenceMonth->toDateString(),
            'das_total_brl' => $calculatedDas->dasTotalBrl,
            'monthly_revenue_brl' => $calculatedDas->monthlyRevenueBrl,
            'is_projection' => $calculatedDas->isProjection || $referenceMonth->greaterThan($currentMonth),
            'rule_version' => $calculatedDas->ruleVersion,
            'das_calculation_id' => null,
            'rbt12_national_brl' => $rollingRevenueBreakdown['national_brl'],
            'rbt12_international_brl' => $rollingRevenueBreakdown['international_brl'],
            'rbt12_total_brl' => $rollingRevenueBreakdown['total_brl'],
            'das_real' => $this->serializeCalculatedScenario($calculatedDas, $rollingRevenueBreakdown['total_brl']),
            'das_contabilidade' => $this->serializeCalculatedScenario($accountingDas, $rollingRevenueBreakdown['national_brl']),
        ];
    }

    /**
     * @return Collection<int, BillingInvoice>
     */
    private function relevantInvoicesForMonth(
        CarbonImmutable $referenceMonth,
        CarbonImmutable $currentMonth,
    ): Collection {
        $monthlyInvoices = $this->billingInvoiceRepository->getModelsForMonth($referenceMonth);

        return $this->filterInvoicesForMonthWindow(
            invoices: $monthlyInvoices,
            referenceMonth: $referenceMonth,
            currentMonth: $currentMonth,
        );
    }

    /**
     * @return array{national_brl: float, international_brl: float, total_brl: float}
     */
    private function rollingRevenueBreakdownForMonth(
        CarbonImmutable $referenceMonth,
        CarbonImmutable $currentMonth,
    ): array {
        $monthlyInvoices = $this->relevantInvoicesForMonth($referenceMonth, $currentMonth);
        $rollingInvoices = $this->billingInvoiceRepository->getModelsForPeriod(
            $referenceMonth->subMonths(12)->startOfMonth(),
            $referenceMonth->subMonths(1)->endOfMonth(),
        );
        $filteredInvoices = $this->filterInvoicesForRollingWindow($rollingInvoices, $currentMonth);
        $allKnownInvoices = $filteredInvoices
            ->concat($monthlyInvoices)
            ->sortBy('billing_date')
            ->values();

        if ($allKnownInvoices->isEmpty()) {
            return [
                'national_brl' => 0.0,
                'international_brl' => 0.0,
                'total_brl' => 0.0,
            ];
        }

        $firstKnownInvoiceMonth = CarbonImmutable::instance($allKnownInvoices->first()->billing_date)->startOfMonth();
        $monthsOfActivityIncludingReference = $firstKnownInvoiceMonth->diffInMonths($referenceMonth) + 1;

        if ($monthsOfActivityIncludingReference <= 12) {
            return $this->proportionalizedRevenueBreakdown(
                $referenceMonth,
                $filteredInvoices,
                $monthlyInvoices,
            );
        }

        $nationalRevenueBrl = $this->sumInvoicesByType($filteredInvoices, 'national');
        $internationalRevenueBrl = $this->sumInvoicesByType($filteredInvoices, 'international');

        return [
            'national_brl' => $nationalRevenueBrl,
            'international_brl' => $internationalRevenueBrl,
            'total_brl' => round($nationalRevenueBrl + $internationalRevenueBrl, 2),
        ];
    }

    /**
     * @param  Collection<int, BillingInvoice>  $invoices
     * @return Collection<int, BillingInvoice>
     */
    private function filterInvoicesForMonthWindow(
        Collection $invoices,
        CarbonImmutable $referenceMonth,
        CarbonImmutable $currentMonth,
    ): Collection {
        $shouldUseSimulations = $referenceMonth->greaterThan($currentMonth);

        return $invoices
            ->filter(fn ($billingInvoice): bool => $billingInvoice->is_simulation === $shouldUseSimulations)
            ->values();
    }

    /**
     * @param  Collection<int, BillingInvoice>  $invoices
     * @return Collection<int, BillingInvoice>
     */
    private function filterInvoicesForRollingWindow(
        Collection $invoices,
        CarbonImmutable $currentMonth,
    ): Collection {
        return $invoices
            ->filter(function ($billingInvoice) use ($currentMonth): bool {
                $invoiceMonth = CarbonImmutable::instance($billingInvoice->billing_date)->startOfMonth();
                $shouldUseSimulation = $invoiceMonth->greaterThan($currentMonth);

                return $billingInvoice->is_simulation === $shouldUseSimulation;
            })
            ->values();
    }

    private function normalizeInvoiceType(string $invoiceType): string
    {
        return strtolower(trim($invoiceType));
    }

    /**
     * @param  Collection<int, BillingInvoice>  $rollingInvoices
     * @param  Collection<int, BillingInvoice>  $monthlyInvoices
     * @return array{national_brl: float, international_brl: float, total_brl: float}
     */
    private function proportionalizedRevenueBreakdown(
        CarbonImmutable $referenceMonth,
        Collection $rollingInvoices,
        Collection $monthlyInvoices,
    ): array {
        if ($rollingInvoices->isEmpty()) {
            $nationalRevenueBrl = round($this->sumInvoicesByType($monthlyInvoices, 'national') * 12, 2);
            $internationalRevenueBrl = round($this->sumInvoicesByType($monthlyInvoices, 'international') * 12, 2);

            return [
                'national_brl' => $nationalRevenueBrl,
                'international_brl' => $internationalRevenueBrl,
                'total_brl' => round($nationalRevenueBrl + $internationalRevenueBrl, 2),
            ];
        }

        $firstKnownInvoiceMonth = CarbonImmutable::instance($rollingInvoices->first()->billing_date)->startOfMonth();
        $previousMonth = $referenceMonth->subMonth()->startOfMonth();
        $monthsOfActivityBeforeReference = $firstKnownInvoiceMonth->diffInMonths($previousMonth) + 1;

        $nationalRevenueBrl = round(
            ($this->sumInvoicesByType($rollingInvoices, 'national') / $monthsOfActivityBeforeReference) * 12,
            2,
        );
        $internationalRevenueBrl = round(
            ($this->sumInvoicesByType($rollingInvoices, 'international') / $monthsOfActivityBeforeReference) * 12,
            2,
        );

        return [
            'national_brl' => $nationalRevenueBrl,
            'international_brl' => $internationalRevenueBrl,
            'total_brl' => round($nationalRevenueBrl + $internationalRevenueBrl, 2),
        ];
    }

    /**
     * @param  Collection<int, BillingInvoice>  $invoices
     */
    private function sumInvoicesByType(Collection $invoices, string $invoiceType): float
    {
        return round(
            $invoices
                ->filter(fn ($billingInvoice): bool => $this->normalizeInvoiceType((string) $billingInvoice->type) === $invoiceType)
                ->sum('amount_brl'),
            2,
        );
    }

    private function baseForScenarioCalculation(float $rollingRevenueBrl): float
    {
        return $rollingRevenueBrl > 0 ? $rollingRevenueBrl : 0.01;
    }

    /**
     * @param  array{national_brl: float, international_brl: float, total_brl: float}  $rollingRevenueBreakdown
     * @return array<string, array<string, array<int, array<string, float|int|string|null>>|float>|array<string, float>>
     */
    private function comparisonMetadata(
        CalculatedDas $realDas,
        CalculatedDas $accountingDas,
        array $rollingRevenueBreakdown,
    ): array {
        return [
            'rbt12' => [
                'national_brl' => $rollingRevenueBreakdown['national_brl'],
                'international_brl' => $rollingRevenueBreakdown['international_brl'],
                'total_brl' => $rollingRevenueBreakdown['total_brl'],
            ],
            'das_real' => $this->serializeCalculatedScenario($realDas, $rollingRevenueBreakdown['total_brl']),
            'das_contabilidade' => $this->serializeCalculatedScenario($accountingDas, $rollingRevenueBreakdown['national_brl']),
        ];
    }

    /**
     * @return array<string, array<int, array<string, float|int|string|null>>|float>
     */
    private function serializeCalculatedScenario(
        CalculatedDas $calculatedDas,
        float $rbt12Brl,
    ): array {
        return [
            'rbt12_brl' => round($rbt12Brl, 2),
            'monthly_revenue_brl' => $calculatedDas->monthlyRevenueBrl,
            'das_total_brl' => $calculatedDas->dasTotalBrl,
            'tax_breakdowns' => array_map(
                fn (CalculatedTaxBreakdown $taxBreakdown): array => [
                    'tax_component_code' => $taxBreakdown->taxComponentCode,
                    'annex_used' => $taxBreakdown->annexUsed,
                    'invoice_type' => $taxBreakdown->invoiceType,
                    'calculated_amount_brl' => $taxBreakdown->calculatedAmountBrl,
                    'rate_percentage' => $taxBreakdown->ratePercentage,
                ],
                $calculatedDas->taxBreakdowns,
            ),
        ];
    }

    /**
     * @return array<string, array<int, array<string, float|int|string|null>>|float>
     */
    private function serializeStoredScenario(DasCalculation $storedCalculation): array
    {
        return [
            'rbt12_brl' => (float) ($storedCalculation->metadata['rolling_revenue_brl'] ?? 0.0),
            'monthly_revenue_brl' => $storedCalculation->monthlyRevenueBrl,
            'das_total_brl' => $storedCalculation->dasTotalBrl,
            'tax_breakdowns' => $storedCalculation->taxBreakdowns
                ->map(fn (DasCalculationTaxBreakdown $taxBreakdown): array => $taxBreakdown->toArray())
                ->all(),
        ];
    }

    /**
     * @return array<string, array<int, array<string, float|int|string|null>>|float>
     */
    private function emptyScenario(float $rbt12Brl): array
    {
        return [
            'rbt12_brl' => round($rbt12Brl, 2),
            'monthly_revenue_brl' => 0.0,
            'das_total_brl' => 0.0,
            'tax_breakdowns' => [],
        ];
    }
}
