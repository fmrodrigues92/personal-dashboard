<?php

namespace App\Http\Requests\ProLabore;

use Illuminate\Foundation\Http\FormRequest;

class GenerateProLaboreSimulationsRequest extends FormRequest
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
            'start_month' => ['required', 'date'],
            'end_month' => ['required', 'date', 'after_or_equal:start_month'],
        ];
    }
}
