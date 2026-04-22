<?php

namespace App\Http\Requests\Das;

use Illuminate\Foundation\Http\FormRequest;

class DasTimelineRequest extends FormRequest
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
            'reference_month' => ['nullable', 'date'],
            'months_before' => ['nullable', 'integer', 'min:0'],
            'months_after' => ['nullable', 'integer', 'min:0'],
            'rule_version' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $referenceMonth = $this->input('reference_month');

        if (is_string($referenceMonth) && preg_match('/^\d{4}-\d{2}$/', $referenceMonth) === 1) {
            $this->merge([
                'reference_month' => "{$referenceMonth}-01",
            ]);
        }

        if ($this->filled('reference_month') && ! $this->has('months_before') && ! $this->has('months_after')) {
            $this->merge([
                'months_before' => 0,
                'months_after' => 0,
            ]);
        }
    }
}
