<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanyRequest extends FormRequest {
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
        $company = $this->route( 'company' );

        return [
            'name'                => [ 'sometimes', 'string', 'max:255' ],
            'tax_id'              => [ 'sometimes', 'string', Rule::unique( 'companies', 'tax_id' )->ignore( $company ) ],
            'registration_number' => [ 'sometimes', 'string', Rule::unique( 'companies', 'registration_number' )->ignore( $company ) ],
            'address'             => [ 'sometimes', 'string' ],
            'city'                => [ 'sometimes', 'string' ],
            'country'             => [ 'sometimes', 'string' ],
            'email'               => [ 'sometimes', 'email' ],
            'phone'               => [ 'sometimes', 'string' ],
            'bank_account'        => [ 'sometimes', 'string' ],
            'iban'                => [ 'sometimes', 'string' ],
            'swift'               => [ 'sometimes', 'string' ],
            'logo'                => [ 'sometimes', 'mimes:png,jpg,jpeg,webp,svg', 'max:2048' ],
            'remove_logo'         => [ 'sometimes', 'boolean' ],
        ];
    }
}
