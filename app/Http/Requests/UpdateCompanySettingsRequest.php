<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanySettingsRequest extends FormRequest {
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array {
        return [
            'address'      => [ 'nullable', 'string', 'max:255' ],
            'city'         => [ 'nullable', 'string', 'max:100' ],
            'country'      => [ 'nullable', 'string', 'max:100' ],
            'email'        => [ 'nullable', 'email' ],
            'phone'        => [ 'nullable', 'string', 'max:50' ],
            'iban'         => [ 'nullable', 'string', 'max:50' ],
            'swift'        => [ 'nullable', 'string', 'max:50' ],
            'invoice_note' => [ 'nullable', 'string' ],
            'is_vat'       => [ 'boolean' ],
        ];
    }
}
