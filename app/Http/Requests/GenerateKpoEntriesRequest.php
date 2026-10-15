<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateKpoEntriesRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'entries' => ['required', 'array', 'min:1'],
            'entries.*.invoice_id' => ['required', 'integer', 'distinct', 'exists:invoices,id'],
            'entries.*.entry_date' => ['required', 'date'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $year = (int) $this->input('year');

            foreach ($this->input('entries', []) as $index => $entry) {
                if (empty($entry['entry_date'])) {
                    continue;
                }

                try {
                    $entryYear = (int) date('Y', strtotime($entry['entry_date']));
                } catch (\Throwable) {
                    continue;
                }

                if ($entryYear !== $year) {
                    $validator->errors()->add("entries.{$index}.entry_date", 'Datum KPO evidencije mora pripadati izabranoj godini.');
                }
            }
        });
    }
}
