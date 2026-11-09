<?php

namespace App\Http\Requests;

use App\Services\IpsQrService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTaxScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $company = (int) $this->route('company');
        abort_unless((int) $user->active_company_id === $company && $user->companies()->whereKey($company)->exists(), 404);

        return true;
    }

    public function rules(): array
    {
        return [
            'request_id' => ['required', 'uuid'],
            'starts_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31'],
            'frequency' => ['required', Rule::in(['once', 'monthly', 'quarterly', 'yearly'])],
            'period_count' => ['exclude_if:frequency,once', 'nullable', 'integer', 'between:1,1200'],
            'due_day' => ['exclude_if:frequency,once', 'required', 'integer', 'between:1,31'],
            'due_month_offset' => ['exclude_if:frequency,once', 'required', 'integer', 'between:0,12'],
            'due_on' => ['exclude_unless:frequency,once', 'required', 'date_format:Y-m-d', 'after_or_equal:starts_on', 'before_or_equal:2100-12-31'],
            'roll_forward' => ['required', 'boolean'],
            'reminder_days' => ['nullable', 'integer', 'between:0,90'],
            'note' => ['nullable', 'string', 'max:4000'],
            'items' => ['required', 'array', 'list', 'min:1', 'max:4'],
            'items.*' => ['required', 'array:category,name,amount_minor,payment_details'],
            'items.*.category' => ['required', 'string', 'max:100', 'distinct:ignore_case'],
            'items.*.name' => ['required', 'string', 'max:150'],
            ...self::versionRules('items.*.'),
        ];
    }

    public static function versionRules(string $prefix = ''): array
    {
        return [
            $prefix.'amount_minor' => ['present', 'nullable', 'integer', 'between:0,'.IpsQrService::MAX_AMOUNT_MINOR],
            $prefix.'payment_details' => ['present', 'array:'.implode(',', IpsQrService::PAYMENT_TAGS)],
            $prefix.'payment_details.*' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function after(IpsQrService $ips): array
    {
        return [function (Validator $validator) use ($ips) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            foreach ($validator->validated()['items'] as $index => $item) {
                foreach ($ips->paymentDetailErrors($item['payment_details'], false) as $tag => $error) {
                    $validator->errors()->add("items.$index.payment_details.$tag", $error);
                }
            }
        }];
    }
}
