<div class="table-responsive invoice__products">
    <table class="table">
        <thead>
        <tr>
            <th class="invoice__item invoice__item--product">Usluga/Proizvod</th>
            <th class="invoice__item invoice__item--quantity">Količina</th>
            <th class="invoice__item invoice__item--price">Cena</th>
            <th class="invoice__item invoice__item--total">Ukupno</th>
        </tr>
        </thead>
        <tbody class="table-group-divider">
        <tr>
            <td class="invoice__item invoice__item--product">Usluge računarskog programiranja</td>
            <td class="invoice__item invoice__item--quantity">1</td>
            <td class="invoice__item invoice__item--price">{{ Number::currency($invoice->total_amount, $invoice->currency) }}</td>
            <td class="invoice__item invoice__item--total">{{ Number::currency($invoice->total_amount * 1, $invoice->currency) }}</td>
        </tr>
        @foreach($invoice->items as $item)
            <tr>
                <td class="invoice__item invoice__item--product">{{ $item->description }}</td>
                <td class="invoice__item invoice__item--quantity">{{ $item->quantity }}</td>
                <td class="invoice__item invoice__item--price">{{ Number::currency($item->unit_price, $invoice->currency) }}</td>
                <td class="invoice__item invoice__item--total">{{ Number::currency($item->unit_price * $item->quantity, $invoice->currency) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
