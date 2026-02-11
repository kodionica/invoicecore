<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClientRequest extends FormRequest {
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
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
        ];
    }
}
