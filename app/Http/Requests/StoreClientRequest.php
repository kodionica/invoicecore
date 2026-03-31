<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClientRequest extends FormRequest {
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array {
        return [
            'name'                => [ 'required', 'string', 'max:255' ],
            'tax_id'              => [ 'nullable', 'string', 'unique:clients,tax_id' ],
            'registration_number' => [ 'nullable', 'string', 'unique:clients,registration_number' ],
            'address'             => [ 'nullable', 'string' ],
            'city'                => [ 'nullable', 'string' ],
            'country'             => [ 'nullable', 'string' ],
            'email'               => [ 'nullable', 'email' ],
            'phone'               => [ 'nullable', 'string' ],
            'client_type'         => [ 'nullable', 'string', Rule::in( $this->allowedClientTypes() ) ],
        ];
    }
}
