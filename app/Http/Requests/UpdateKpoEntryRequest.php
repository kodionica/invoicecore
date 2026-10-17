<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateKpoEntryRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'entry_date' => ['sometimes', 'date'],
            'income_type' => ['sometimes', 'nullable', 'string', 'max:100'],
            'income_category' => ['sometimes', 'nullable', Rule::in(['product', 'service', 'unclassified'])],
            'adjustment_rsd' => ['sometimes', 'numeric'],
            'description' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
