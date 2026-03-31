<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClientRequest extends FormRequest {
    private function allowedClientTypes(): array {
        return collect( config( 'client-type', [] ) )
            ->pluck( 'value' )
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array {
        $client = $this->route( 'client' );

        return [
            'name'                => [ 'sometimes', 'string', 'max:255' ],
            'tax_id'              => [ 'sometimes', 'nullable', 'string', Rule::unique( 'clients', 'tax_id' )->ignore( $client ) ],
            'registration_number' => [ 'sometimes', 'nullable', 'string', Rule::unique( 'clients', 'registration_number' )->ignore( $client ) ],
            'address'             => [ 'sometimes', 'nullable', 'string' ],
            'city'                => [ 'sometimes', 'nullable', 'string' ],
            'country'             => [ 'sometimes', 'nullable', 'string' ],
            'email'               => [ 'sometimes', 'email' ],
            'phone'               => [ 'sometimes', 'nullable', 'string' ],
            'client_type'         => [ 'sometimes', 'nullable', 'string', Rule::in( $this->allowedClientTypes() ) ],
        ];
    }
}
