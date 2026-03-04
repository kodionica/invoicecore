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
            'client_id'      => [ 'required', 'integer' ],
            'due_date'       => [ 'nullable', 'integer' ],
            'invoice_number' => [ 'nullable', 'string', 'max:255' ],
            'currency'       => [ 'nullable', 'string', 'size:3' ],
            'payment_method' => [ 'nullable', 'string' ],
            'note'           => [ 'nullable', 'string' ],

            'items'               => [ 'required', 'array', 'min:1' ],
            'items.*.name'        => [ 'required', 'string' ],
            'items.*.description' => [ 'nullable', 'string' ],
            'items.*.quantity'    => [ 'required', 'decimal:0,4', 'min:0.0001' ],
            'items.*.price'       => [ 'required', 'decimal:0,4', 'min:0' ],
        ];
    }
}
