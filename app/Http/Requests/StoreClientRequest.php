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
            'pib'     => [ 'nullable', 'string', 'size:9' ],
            'address' => [ 'nullable', 'string' ],
            'email'   => [ 'nullable', 'email' ],
        ];
    }
}
