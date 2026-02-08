<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanySettingsRequest extends FormRequest {
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
            'invoice_prefix'       => [ 'required', 'string', 'max:10' ],
            'invoice_start_number' => [ 'required', 'integer', 'min:1' ],
            'invoice_next_number'  => [ 'required', 'integer', 'min:1' ],
            'currency'             => [ 'required', 'string', 'size:3' ],
            'default_tax_percent'  => [ 'required', 'integer', 'min:0', 'max:100' ],
            'payment_due_days'     => [ 'required', 'integer', 'min:0' ],
            'invoice_note'         => [ 'nullable', 'string' ],
            'vat_enabled'          => [ 'boolean' ],
            'other_settings'       => [ 'nullable', 'array' ],
        ];
    }
}
