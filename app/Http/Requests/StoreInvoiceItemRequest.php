<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvoiceItemRequest extends FormRequest {
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array {
        return [
            'items'               => [ 'required', 'array' ],
            'items.*.name'        => [ 'required', 'string' ],
            'items.*.quantity'    => [ 'required', 'numeric', 'min:0.01' ],
            'items.*.price'       => [ 'required', 'numeric', 'min:0' ],
            'items.*.tax_percent' => [ 'required', 'numeric', 'min:0' ],
        ];
    }
}
