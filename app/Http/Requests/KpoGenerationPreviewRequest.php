<?php

namespace App\Http\Requests;

use App\Models\Company;
use Illuminate\Support\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class KpoGenerationPreviewRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->missing('year')) {
            $timezone = $this->user()?->activeCompany?->effectiveTimezone() ?? Company::DEFAULT_TIMEZONE;
            $this->merge(['year' => Carbon::now($timezone)->year]);
        }
    }

    public function rules(): array
    {
        return [
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            // Query-string clients (including Axios) may serialize booleans as strings.
            'include_historical' => ['sometimes', 'in:0,1,true,false'],
        ];
    }
}
