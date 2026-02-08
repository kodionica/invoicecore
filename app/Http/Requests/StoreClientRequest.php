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
            'name'    => [ 'required', 'string', 'max:255' ],
            'pib'     => [ 'nullable', 'string', 'unique:clients,pib' ],
            'mb'      => [ 'nullable', 'string', 'unique:clients,mb' ],
            'address' => [ 'nullable', 'string' ],
            'city'    => [ 'nullable', 'string' ],
            'country' => [ 'nullable', 'string' ],
            'email'   => [ 'nullable', 'email' ],
            'phone'   => [ 'nullable', 'string' ],
        ];
    }
}
