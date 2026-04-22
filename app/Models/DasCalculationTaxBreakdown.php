<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DasCalculationTaxBreakdown extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'das_calculation_id',
        'tax_component_code',
        'annex_used',
        'invoice_type',
        'calculated_amount_brl',
        'adjusted_amount_brl',
        'rate_percentage',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'calculated_amount_brl' => 'float',
            'adjusted_amount_brl' => 'float',
            'rate_percentage' => 'float',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<DasCalculation, $this>
     */
    public function dasCalculation(): BelongsTo
    {
        return $this->belongsTo(DasCalculation::class);
    }
}
