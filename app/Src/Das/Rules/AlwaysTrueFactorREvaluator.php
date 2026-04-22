<?php

namespace App\Src\Das\Rules;

use App\Src\Das\Rules\Contracts\FactorREvaluatorInterface;
use Carbon\CarbonImmutable;

class AlwaysTrueFactorREvaluator implements FactorREvaluatorInterface
{
    public function passes(CarbonImmutable $referenceMonth): bool
    {
        return true;
    }
}
