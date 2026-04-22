<?php

namespace App\Src\Das\Infrastructure;

use App\Models\DasCalculation as DasCalculationModel;
use App\Models\DasCalculationTaxBreakdown as DasCalculationTaxBreakdownModel;
use App\Src\Das\Contracts\DasCalculationRepositoryInterface;
use App\Src\Das\Domain\DasCalculation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class EloquentDasCalculationRepository implements DasCalculationRepositoryInterface
{
    public function __construct(
        private readonly DasCalculationModel $model,
    ) {}

    public function save(array $attributes, array $taxBreakdowns): DasCalculation
    {
        $dasCalculation = $this->model->newQuery()->firstOrNew([
            'reference_month' => $attributes['reference_month'],
            'rule_version' => $attributes['rule_version'],
            'is_projection' => $attributes['is_projection'],
        ]);

        $dasCalculation->fill($attributes);
        $dasCalculation->save();

        $dasCalculation->taxBreakdowns()->delete();
        $dasCalculation->taxBreakdowns()->createMany($taxBreakdowns);
        $dasCalculation->load('taxBreakdowns');

        return DasCalculation::fromModel($dasCalculation);
    }

    public function getAll(): Collection
    {
        return $this->model
            ->newQuery()
            ->with('taxBreakdowns')
            ->orderByDesc('reference_month')
            ->get()
            ->map(fn (DasCalculationModel $dasCalculation): DasCalculation => DasCalculation::fromModel($dasCalculation));
    }

    public function findById(int $id): ?DasCalculation
    {
        $dasCalculation = $this->model
            ->newQuery()
            ->with('taxBreakdowns')
            ->find($id);

        return $dasCalculation === null ? null : DasCalculation::fromModel($dasCalculation);
    }

    public function findStoredForMonth(
        CarbonImmutable $referenceMonth,
        string $ruleVersion,
        bool $isProjection,
    ): ?DasCalculation {
        $dasCalculation = $this->model
            ->newQuery()
            ->with('taxBreakdowns')
            ->whereDate('reference_month', $referenceMonth->toDateString())
            ->where('rule_version', $ruleVersion)
            ->where('is_projection', $isProjection)
            ->first();

        return $dasCalculation === null ? null : DasCalculation::fromModel($dasCalculation);
    }

    public function updateTaxBreakdown(
        DasCalculationTaxBreakdownModel $taxBreakdown,
        float $adjustedAmountBrl,
    ): DasCalculation {
        $taxBreakdown->update([
            'adjusted_amount_brl' => $adjustedAmountBrl,
        ]);

        /** @var DasCalculationModel $dasCalculation */
        $dasCalculation = $taxBreakdown->dasCalculation()->with('taxBreakdowns')->firstOrFail();
        $dasTotalBrl = round(
            $dasCalculation->taxBreakdowns->sum(
                fn (DasCalculationTaxBreakdownModel $storedTaxBreakdown): float => $storedTaxBreakdown->adjusted_amount_brl
                    ?? $storedTaxBreakdown->calculated_amount_brl,
            ),
            2,
        );

        $dasCalculation->update([
            'das_total_brl' => $dasTotalBrl,
        ]);
        $dasCalculation->refresh()->load('taxBreakdowns');

        return DasCalculation::fromModel($dasCalculation);
    }
}
