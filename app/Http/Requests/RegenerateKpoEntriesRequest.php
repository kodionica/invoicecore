<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegenerateKpoEntriesRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['confirm_regeneration', 'confirm_manual_overwrite', 'include_historical'] as $field) {
            if ($this->has($field)) {
                $this->merge([$field => filter_var($this->input($field), FILTER_VALIDATE_BOOLEAN)]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'invoice_ids' => ['required', 'array', 'min:1'],
            'invoice_ids.*' => ['required', 'integer', 'distinct', 'exists:invoices,id'],
            'preview_fingerprint' => ['required', 'string', 'size:64'],
            'confirm_regeneration' => ['required', 'accepted'],
            'confirm_manual_overwrite' => ['sometimes', 'boolean'],
            'include_historical' => ['sometimes', 'boolean'],
        ];
    }
}
