<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DasCalculation extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'reference_month',
        'rule_version',
        'factor_r_applied',
        'monthly_revenue_brl',
        'das_total_brl',
        'is_projection',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reference_month' => 'immutable_date',
            'factor_r_applied' => 'boolean',
            'monthly_revenue_brl' => 'float',
            'das_total_brl' => 'float',
            'is_projection' => 'boolean',
            'metadata' => 'array',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return HasMany<DasCalculationTaxBreakdown, $this>
     */
    public function taxBreakdowns(): HasMany
    {
        return $this->hasMany(DasCalculationTaxBreakdown::class);
    }
}
