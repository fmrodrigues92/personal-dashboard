<?php

namespace App\Src\Das\Rules\Contracts;

use Carbon\CarbonImmutable;

interface FactorREvaluatorInterface
{
    public function passes(CarbonImmutable $referenceMonth): bool;
}
