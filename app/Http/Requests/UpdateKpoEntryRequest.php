<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateKpoEntryRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'entry_date' => ['sometimes', 'date'],
            'income_type' => ['sometimes', 'nullable', 'string', 'max:100'],
            'adjustment_rsd' => ['sometimes', 'numeric'],
            'description' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
