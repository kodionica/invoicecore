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
            'pib'          => [ 'required', 'string', 'size:9', 'unique:companies,pib' ],
            'mb'           => [ 'required', 'string', 'size:8', 'unique:companies,mb' ],
            'address'      => [ 'string' ],
            'city'         => [ 'string' ],
            'country'      => [ 'string' ],
            'email'        => [ 'email' ],
            'phone'        => [ 'string' ],
            'bank_account' => [ 'string' ],
            'iban'         => [ 'string' ],
            'swift'        => [ 'string' ],
        ];
    }
}
