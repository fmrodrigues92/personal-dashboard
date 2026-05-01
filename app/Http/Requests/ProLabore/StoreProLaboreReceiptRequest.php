<?php

namespace App\Http\Requests\ProLabore;

use Illuminate\Foundation\Http\FormRequest;

class StoreProLaboreReceiptRequest extends FormRequest
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
            'reference_month' => ['required', 'date'],
            'gross_amount_brl' => ['required', 'numeric', 'gt:0'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
