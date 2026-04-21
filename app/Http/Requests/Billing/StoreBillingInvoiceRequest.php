<?php

namespace App\Http\Requests\Billing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBillingInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'billing_date' => ['required', 'date'],
            'type' => ['required', 'string', Rule::in(['national', 'international'])],
            'cnae' => ['required', 'string', 'max:255'],
            'cnae_annex' => ['required', 'integer'],
            'cnae_calculation' => ['nullable', 'integer'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_external_id' => ['nullable', 'string', 'max:255'],
            'amount_brl' => ['required', 'numeric', 'gt:0'],
            'amount_usd' => ['nullable', 'required_if:type,international', 'numeric', 'gt:0'],
            'usd_brl_exchange_rate' => ['nullable', 'required_if:type,international', 'numeric', 'gt:0'],
            'is_simulation' => ['sometimes', 'boolean'],
        ];
    }
}
