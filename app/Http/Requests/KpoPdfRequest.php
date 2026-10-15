<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class KpoPdfRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ];
    }
}
