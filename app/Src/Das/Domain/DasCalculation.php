<?php

namespace App\Src\Das\Domain;

use App\Models\DasCalculation as DasCalculationModel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

readonly class DasCalculation
{
    /**
     * @param  Collection<int, DasCalculationTaxBreakdown>  $taxBreakdowns
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public int $id,
        public CarbonImmutable $referenceMonth,
        public string $ruleVersion,
        public bool $factorRApplied,
        public float $monthlyRevenueBrl,
        public float $dasTotalBrl,
        public bool $isProjection,
        public ?array $metadata,
        public Collection $taxBreakdowns,
        public CarbonImmutable $createdAt,
        public CarbonImmutable $updatedAt,
    ) {}

    public static function fromModel(DasCalculationModel $dasCalculation): self
    {
        /** @var Collection<int, DasCalculationTaxBreakdown> $taxBreakdowns */
        $taxBreakdowns = $dasCalculation->taxBreakdowns
            ->map(fn (\App\Models\DasCalculationTaxBreakdown $taxBreakdown): DasCalculationTaxBreakdown => DasCalculationTaxBreakdown::fromModel($taxBreakdown));

        return new self(
            id: $dasCalculation->id,
            referenceMonth: CarbonImmutable::parse($dasCalculation->reference_month)->startOfDay(),
            ruleVersion: $dasCalculation->rule_version,
            factorRApplied: $dasCalculation->factor_r_applied,
            monthlyRevenueBrl: $dasCalculation->monthly_revenue_brl,
            dasTotalBrl: $dasCalculation->das_total_brl,
            isProjection: $dasCalculation->is_projection,
            metadata: $dasCalculation->metadata,
            taxBreakdowns: $taxBreakdowns,
            createdAt: $dasCalculation->created_at,
            updatedAt: $dasCalculation->updated_at,
        );
    }

    /**
     * @return array<string, array<int, array<string, float|int|string|null>>|array<string, mixed>|bool|float|int|string|null>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'reference_month' => $this->referenceMonth->toDateString(),
            'rule_version' => $this->ruleVersion,
            'factor_r_applied' => $this->factorRApplied,
            'monthly_revenue_brl' => $this->monthlyRevenueBrl,
            'das_total_brl' => $this->dasTotalBrl,
            'is_projection' => $this->isProjection,
            'metadata' => $this->metadata,
            'tax_breakdowns' => $this->taxBreakdowns
                ->map(fn (DasCalculationTaxBreakdown $taxBreakdown): array => $taxBreakdown->toArray())
                ->all(),
            'created_at' => $this->createdAt->toISOString(),
            'updated_at' => $this->updatedAt->toISOString(),
        ];
    }
}
