@php
    /**
     * @global \App\Models\InvoiceItem[] $invoice_items
     * @global \App\Models\Company $company
     */
@endphp

<div class="table-responsive invoice__products">
    <table class="table">
        <thead>
        <tr>
            <th class="invoice__item invoice__item--product">Usluga/Proizvod</th>
            <th class="invoice__item invoice__item--product">Opis</th>
            <th class="invoice__item invoice__item--quantity">Količina</th>
            <th class="invoice__item invoice__item--price">Cena</th>
            @if($company->vat_enabled)
                <th class="invoice__item invoice__item--sub-total">Ukupno bez PDV-a</th>
                <th class="invoice__item invoice__item--tax">PDV</th>
            @endif
            <th class="invoice__item invoice__item--total">Ukupno</th>
        </tr>
        </thead>
        <tbody class="table-group-divider">
        @foreach($invoice_items as $item)
            <tr>
                <td class="invoice__item invoice__item--product">{{ $item->name }}</td>
                <td class="invoice__item invoice__item--product">{{ $item->description }}</td>
                <td class="invoice__item invoice__item--quantity">{{ $item->quantity }}</td>
                <td class="invoice__item invoice__item--price">{{ Number::currency($item->price, $invoice->currency) }}</td>
                @if($company->vat_enabled)
                    <td class="invoice__item invoice__item--sub-total">{{ Number::currency($item->sub_total, $invoice->currency) }}</td>
                    <td class="invoice__item invoice__item--tax">{{ Number::currency($item->tax_amount, $invoice->currency) }}</td>
                @endif
                <td class="invoice__item invoice__item--total">{{ Number::currency($item->total, $invoice->currency) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
