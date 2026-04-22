<?php

namespace App\Src\Das\Rules;

use App\Src\Das\Rules\Contracts\DasCalculationRuleInterface;
use InvalidArgumentException;

class DasCalculationRuleResolver
{
    public function __construct(
        private readonly SimplesNacionalService2018Rule $simplesNacionalService2018Rule,
    ) {}

    public function defaultVersion(): string
    {
        return 'simples_nacional_service_2018';
    }

    public function resolve(?string $version = null): DasCalculationRuleInterface
    {
        $resolvedVersion = $version ?? $this->defaultVersion();

        if ($this->simplesNacionalService2018Rule->version() === $resolvedVersion) {
            return $this->simplesNacionalService2018Rule;
        }

        throw new InvalidArgumentException("Unsupported DAS calculation rule version [{$resolvedVersion}].");
    }
}
