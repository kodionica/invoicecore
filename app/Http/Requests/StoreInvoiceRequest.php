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
            'client_id'    => [ 'required', 'exists:clients,id' ],
            'invoice_date' => [ 'required', 'date' ],
            'due_date'     => [ 'nullable', 'date' ],
            'note'         => [ 'nullable', 'string' ],
        ];
    }
}
