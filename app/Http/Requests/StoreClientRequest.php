<?php

namespace App\Http\Requests;

use App\Support\ClientProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreClientRequest extends FormRequest
{
    public function validator(): Validator
    {
        return ClientProfile::validator(
            ClientProfile::normalize($this->all(), true),
            (int) $this->user()->active_company_id
        );
    }
}
