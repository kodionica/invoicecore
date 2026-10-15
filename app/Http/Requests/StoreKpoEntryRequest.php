<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreKpoEntryRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'invoice_id' => ['nullable', 'integer', 'exists:invoices,id'],
            'entry_date' => ['required', 'date'],
            'income_type' => ['nullable', 'string', 'max:100'],
            'source_type' => ['required', Rule::in(['manual', 'invoice'])],
            'currency' => ['required_if:source_type,manual', 'nullable', 'string', 'size:3'],
            'amount_original' => ['required_if:source_type,manual', 'nullable', 'numeric', 'min:0'],
            'amount_rsd' => ['required_if:source_type,manual', 'nullable', 'numeric', 'min:0'],
            'adjustment_rsd' => ['nullable', 'numeric'],
            'description' => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->input('source_type') === 'invoice' && ! $this->filled('invoice_id')) {
                $validator->errors()->add('invoice_id', 'Faktura je obavezna za izvor tipa invoice.');
            }

            if ($this->input('source_type') === 'manual' && $this->filled('invoice_id')) {
                $validator->errors()->add('invoice_id', 'Ručni zapis ne može imati fakturu kao izvor.');
            }
        });
    }
}
