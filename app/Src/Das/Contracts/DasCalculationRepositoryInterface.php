<?php

namespace App\Src\Das\Contracts;

use App\Models\DasCalculationTaxBreakdown as DasCalculationTaxBreakdownModel;
use App\Src\Das\Domain\DasCalculation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

interface DasCalculationRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array<string, float|int|string|null>>  $taxBreakdowns
     */
    public function save(array $attributes, array $taxBreakdowns): DasCalculation;

    /**
     * @return Collection<int, DasCalculation>
     */
    public function getAll(): Collection;

    public function findById(int $id): ?DasCalculation;

    public function findStoredForMonth(
        CarbonImmutable $referenceMonth,
        string $ruleVersion,
        bool $isProjection,
    ): ?DasCalculation;

    public function updateTaxBreakdown(
        DasCalculationTaxBreakdownModel $taxBreakdown,
        float $adjustedAmountBrl,
    ): DasCalculation;
}
