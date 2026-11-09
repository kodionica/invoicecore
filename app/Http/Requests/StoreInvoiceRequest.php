<?php

namespace App\Http\Requests;

use Illuminate\Support\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use App\Models\Company;

class StoreInvoiceRequest extends FormRequest {
    public static function ipsRules(): array
    {
        return [
            'ips_payment_details' => ['sometimes', 'array:SF,RO,S'],
            'ips_payment_details.*' => ['nullable', 'string', 'max:100'],
        ];
    }

    public static function validateIpsDetails($validator, $details): void
    {
        if ($validator->errors()->isNotEmpty() || ! is_array($details)) {
            return;
        }

        foreach (app(\App\Services\IpsQrService::class)->paymentDetailErrors($details, false) as $tag => $error) {
            $validator->errors()->add('ips_payment_details.'.$tag, $error);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array {
        return [
            'client_id'      => [ 'required', 'integer' ],
            'issue_date'     => [ 'nullable', 'date' ],
            'service_date' => ['sometimes', 'date_format:Y-m-d'],
            'due_date'       => [ 'nullable', 'date' ],
            'invoice_number' => [ 'nullable', 'string', 'max:255' ],
            'document_locale' => ['nullable', \Illuminate\Validation\Rule::in(array_keys(\App\Support\ClientProfile::LANGUAGES))],
            'currency'       => [ 'nullable', 'string', 'size:3' ],
            'payment_method' => [ 'nullable', 'string' ],
            'note'           => [ 'nullable', 'string' ],
            'include_in_kpo' => [ 'sometimes', 'boolean' ],
            'vat_enabled_at_issue' => [ 'sometimes', 'boolean' ],
            ...StoreInvoiceRequest::ipsRules(),
            'bank_account_id' => [ 'sometimes', 'nullable', 'integer' ],

            'items'               => [ 'required', 'array', 'min:1' ],
            'items.*.name'        => [ 'required', 'string' ],
            'items.*.description' => [ 'nullable', 'string' ],
            'items.*.quantity'    => [ 'required', 'decimal:0,4', 'min:0.0001' ],
            'items.*.price'       => [ 'required', 'decimal:0,4', 'min:0' ],
            'items.*.income_category' => [ 'nullable', 'string', 'in:product,service,unclassified' ],
        ];
    }

    public function withValidator( $validator ): void {
        $validator->after( function ( $validator ) {
            StoreInvoiceRequest::validateIpsDetails($validator, $this->input('ips_payment_details'));
            $issueDate = $this->input( 'issue_date' );
            $dueDate   = $this->input( 'due_date' );
            $timezone = $this->user()?->activeCompany?->effectiveTimezone() ?? Company::DEFAULT_TIMEZONE;

            if ($issueDate && Carbon::parse($issueDate)->lt(Carbon::today($timezone))) {
                $validator->errors()->add('issue_date', 'Datum izdavanja ne može biti u prošlosti.');
            }

            if ( ! $dueDate ) {
                return;
            }

            $baseDate = $issueDate ? Carbon::parse( $issueDate ) : Carbon::today($timezone);
            $due      = Carbon::parse( $dueDate );

            if ( $due->lt( $baseDate ) ) {
                $validator->errors()->add( 'due_date', 'Rok plaćanja ne može biti pre datuma izdavanja.' );
            }

        } );
    }
}
