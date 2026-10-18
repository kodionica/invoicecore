<?php

namespace App\Http\Requests;

use App\Models\Company;
use Illuminate\Support\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class KpoRegenerationPreviewRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->missing('year')) {
            $timezone = $this->user()?->activeCompany?->effectiveTimezone() ?? Company::DEFAULT_TIMEZONE;
            $this->merge(['year' => Carbon::now($timezone)->year]);
        }
        if ($this->has('include_historical')) {
            $this->merge(['include_historical' => filter_var($this->input('include_historical'), FILTER_VALIDATE_BOOLEAN)]);
        }
    }

    public function rules(): array
    {
        return [
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'include_historical' => ['sometimes', 'boolean'],
            'invoice_ids' => ['sometimes', 'array', 'min:1'],
            'invoice_ids.*' => ['integer', 'distinct', 'exists:invoices,id'],
        ];
    }
}
