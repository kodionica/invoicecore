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

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array {
        return [
            'name'         => [ 'required', 'string', 'max:255' ],
            'tax_id'       => [ 'required', 'string', 'unique:companies,tax_id' ],
            'company_id'   => [ 'required', 'string', 'unique:companies,company_id' ],
            'address'      => [ 'nullable', 'string' ],
            'city'         => [ 'nullable', 'string' ],
            'country'      => [ 'nullable', 'string' ],
            'email'        => [ 'nullable', 'email' ],
            'phone'        => [ 'nullable', 'string' ],
            'bank_account' => [ 'nullable', 'string' ],
            'iban'         => [ 'nullable', 'string' ],
            'swift'        => [ 'nullable', 'string' ],
            'logo'         => [ 'nullable', 'mimes:png,jpg,jpeg,webp,svg', 'max:2048' ],
        ];
    }
}
