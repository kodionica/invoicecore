<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RemoveGeneratedKpoEntryRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
            'confirm' => ['required', 'accepted'],
        ];
    }
}
