<?php

namespace App\Http\Requests\Das;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDasTaxBreakdownRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'adjusted_amount_brl' => ['required', 'numeric', 'gte:0'],
        ];
    }
}
