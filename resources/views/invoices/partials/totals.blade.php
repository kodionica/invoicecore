@php
    /**
     * @global \App\Models\User $user
     * @global \App\Models\Invoice $invoice
     */
@endphp

<div class="container-fluid mt-3">
    <div class="row">
        <div class="col-md-8">
            <p>{{ $user->invoiceSettings->footer_note }}</p>
        </div>
        <div class="col-md-4">
            <div class="table-responsive invoice__totals">
                <table class="table">
                    <tbody>
                    <tr>
                        <th>Način plaćanja</th>
                        {{--                                    <td class="text-end">{{ $invoice->payments->first()->method }}</td>--}}
                        <td>Uplata na račun</td>
                    </tr>
                    <tr>
                        <th>Ukupno svega</th>
                        <td>{{ Number::currency($invoice->total_amount, $invoice->currency) }}</td>
                    </tr>
                    </tbody>
                </table>
            </div>

{{--            <p class="invoice__notice">VAT is not charged according to Article 33 of the VAT Law of the Republic of Serbia.</p>--}}
            <p class="invoice__notice">Poreski obveznik nije u sistemu PDV-a. PDV nije obračunat na fakturi u skladu sa članom 33. Zakona o porezu na dodatu vrednost.</p>
        </div>
    </div>
</div>
