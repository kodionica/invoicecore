<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompanyBankAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'currency' => strtoupper((string) $this->input('currency', '')),
            'account_type' => strtolower((string) $this->input('account_type', 'domestic')),
        ]);
    }

    public function rules(): array
    {
        return [
            'label' => ['nullable', 'string', 'max:100'],
            'bank_name' => ['required', 'string', 'max:255'],
            'account_number' => ['required', 'string', 'max:255'],
            'iban' => ['nullable', 'string', 'max:255'],
            'swift_bic' => ['nullable', 'string', 'max:32'],
            'currency' => ['required', 'string', Rule::in(array_keys(config('currency', [])))],
            'account_type' => ['required', 'in:domestic,foreign'],
            'payment_instructions' => ['nullable', 'string', 'max:5000'],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
