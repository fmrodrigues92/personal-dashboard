<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BillingInvoice extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'billing_date',
        'type',
        'cnae',
        'cnae_annex',
        'cnae_calculation',
        'customer_name',
        'customer_external_id',
        'amount_brl',
        'amount_usd',
        'usd_brl_exchange_rate',
        'is_simulation',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'billing_date' => 'immutable_datetime',
            'amount_brl' => 'float',
            'amount_usd' => 'float',
            'usd_brl_exchange_rate' => 'float',
            'is_simulation' => 'boolean',
            'deleted_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
