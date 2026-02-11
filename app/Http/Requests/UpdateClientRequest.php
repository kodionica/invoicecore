<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClientRequest extends FormRequest {
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array {
        $client = $this->route( 'client' );

        return [
            'name'                => [ 'sometimes', 'string', 'max:255' ],
            'tax_id'              => [ 'sometimes', 'string', Rule::unique( 'clients', 'tax_id' )->ignore( $client ) ],
            'registration_number' => [ 'sometimes', 'string', Rule::unique( 'clients', 'registration_number' )->ignore( $client ) ],
            'address'             => [ 'sometimes', 'string' ],
            'city'                => [ 'sometimes', 'string' ],
            'country'             => [ 'sometimes', 'string' ],
            'email'               => [ 'sometimes', 'email' ],
            'phone'               => [ 'sometimes', 'string' ],
        ];
    }
}
