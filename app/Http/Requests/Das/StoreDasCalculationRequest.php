<?php

namespace App\Http\Requests\Das;

use Illuminate\Foundation\Http\FormRequest;

class StoreDasCalculationRequest extends FormRequest
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
            'reference_month' => ['required', 'date'],
            'rule_version' => ['nullable', 'string', 'max:255'],
        ];
    }
}
