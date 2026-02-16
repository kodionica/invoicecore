@php
    /**
     * @global \App\Models\Company $company
     * @global \App\Models\Invoice $invoice
     */
@endphp

<div class="container-fluid mt-3">
    <div class="row">
        <div class="col-md-8">
            <p>{{ $company->invoice_note }}</p>
        </div>
        <div class="col-md-4">
            <div class="table-responsive invoice__totals">
                <table class="table">
                    <tbody>
                    <tr>
                        <th>Način plaćanja</th>
                        <td>{{ config('payment')[$invoice->payment_method] ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <th>Ukupno svega</th>
                        <td>{{ Number::currency($invoice->total, $invoice->currency) }}</td>
                    </tr>
                    </tbody>
                </table>
            </div>

            @if(!$company->vat_enabled)
                {{--            <p class="invoice__notice">VAT is not charged according to Article 33 of the VAT Law of the Republic of Serbia.</p>--}}
                <p class="invoice__notice">Poreski obveznik nije u sistemu PDV-a. PDV nije obračunat na fakturi u skladu sa članom 33. Zakona o porezu na dodatu vrednost.</p>
            @endif
        </div>
    </div>
</div>
