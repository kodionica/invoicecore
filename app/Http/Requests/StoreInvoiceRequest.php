<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvoiceRequest extends FormRequest {
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array {
        return [
            'issue_date'     => [ 'required', 'date' ],
            'due_date'       => [ 'nullable', 'date' ],
            'currency'       => [ 'nullable', 'string' ],
            'payment_method' => [ 'nullable', 'string' ],
            'note'           => [ 'nullable', 'string' ],
        ];
    }
}
