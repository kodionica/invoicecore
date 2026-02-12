<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCompanyRequest extends FormRequest {
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool {
        return true;
    }

    protected function prepareForValidation() {
        $this->merge( [
                          'vat_enabled' => $this->has( 'vat_enabled' ),
                      ] );
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array {
        return [
            'name'                => [ 'required', 'string', 'max:255' ],
            'tax_id'              => [ 'required', 'string', 'unique:companies,tax_id' ],
            'registration_number' => [ 'required', 'string', 'unique:companies,registration_number' ],
            'address'             => [ 'nullable', 'string' ],
            'city'                => [ 'nullable', 'string' ],
            'country'             => [ 'nullable', 'string' ],
            'email'               => [ 'nullable', 'email' ],
            'phone'               => [ 'nullable', 'string' ],
            'bank_account'        => [ 'nullable', 'string' ],
            'iban'                => [ 'nullable', 'string' ],
            'swift'               => [ 'nullable', 'string' ],
            'currency'            => [ 'required', 'string', 'max:3' ],
            'vat_enabled'         => [ 'nullable', 'boolean' ],
            'logo'                => [ 'nullable', 'mimes:png,jpg,jpeg,webp,svg', 'max:2048' ],
        ];
    }
}
