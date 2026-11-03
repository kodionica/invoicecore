<?php

namespace App\Http\Requests;

use App\Support\ClientProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('client'));
    }

    public function validator(): Validator
    {
        return ClientProfile::validator(
            ClientProfile::normalize($this->all(), false),
            (int) $this->user()->active_company_id,
            $this->route('client')
        );
    }
}
