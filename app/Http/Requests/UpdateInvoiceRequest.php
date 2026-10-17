<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInvoiceRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('invoice_number')) {
            $this->merge([
                'invoice_number' => trim((string) $this->input('invoice_number')),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'client_id' => ['sometimes', 'integer'],
            'invoice_number' => ['sometimes', 'string', 'max:255'],
            'issue_date' => ['sometimes', 'date'],
            'due_date' => ['sometimes', 'date'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'payment_method' => ['sometimes', 'nullable', 'string'],
            'note' => ['sometimes', 'nullable', 'string'],
            'include_in_kpo' => ['sometimes', 'boolean'],
            'vat_enabled_at_issue' => ['sometimes', 'boolean'],
            'kpo_confirmation' => ['sometimes', 'array'],
            'kpo_confirmation.confirmed' => ['required_with:kpo_confirmation', 'boolean'],
            'kpo_confirmation.preview_fingerprint' => ['required_with:kpo_confirmation', 'string', 'size:64'],
            'kpo_confirmation.confirm_manual_overwrite' => ['sometimes', 'boolean'],
            'status' => [
                'sometimes',
                Rule::in(array_map(static fn ($case) => $case->value, \App\Enums\InvoiceStatus::cases())),
            ],
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.id' => ['sometimes', 'integer', 'distinct'],
            'items.*.name' => ['required_with:items', 'string'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.quantity' => ['required_with:items', 'decimal:0,4', 'min:0.0001'],
            'items.*.price' => ['required_with:items', 'decimal:0,4', 'min:0'],
            'items.*.income_category' => ['nullable', 'string', 'in:product,service,unclassified'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $issueDate = $this->input('issue_date');
            $dueDate = $this->input('due_date');

            if ($issueDate && $dueDate && $dueDate < $issueDate) {
                $validator->errors()->add('due_date', 'Rok plaćanja ne može biti pre datuma izdavanja.');
            }
        });
    }
}
