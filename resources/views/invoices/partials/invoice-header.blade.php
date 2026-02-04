@php
    /**
     * @global \App\Models\User $user
     * @global  \App\Models\Invoice $invoice
     *
     */
        use Carbon\Carbon;

@endphp

<div class="invoice__header">
    <div class="invoice__company">
        <div class="invoice__company__data">
            <p class="section-label section-label--small">Od:</p>
            <p class="section-label section-label--large">{{ $user->invoiceSettings->company_name }}</p>
            <p><strong>Adresa: </strong>{{ $user->invoiceSettings->company_address }}</p>
            <p><strong>Telefon: </strong>{{ $user->invoiceSettings->company_phone }}</p>
            <p><strong>Email: </strong>{{ $user->invoiceSettings->company_email }}</p>
            <p><strong>PIB: </strong>{{ $user->invoiceSettings->pib }}</p>
            <p><strong>MB: </strong>{{ $user->invoiceSettings->mb }}</p>
            <p><strong>Broj računa: </strong>{{ $user->invoiceSettings->bank_account }}</p>
            <p><strong>IBAN: </strong>{{ $user->invoiceSettings->iban }}</p>
            <p><strong>SWIFT: </strong>{{ $user->invoiceSettings->swift }}</p>
        </div>

        <div class="invoice__company__logo">
            <img src="{{ public_path('storage/' . $user->invoiceSettings->logo_path) }}" alt="Logo">
        </div>
    </div>
    <div class="invoice__client">
        <p class="section-label section-label--small">Za:</p>
        <p class="section-label section-label--large">{{ $invoice->client->name }}</p>
        <p><strong>Adresa: </strong>{{ $invoice->client->address }}</p>
        <p><strong>Država: </strong>{{ $invoice->client->country }}</p>
        @if($invoice->client->email)
            <p><strong>Email: </strong>{{ $invoice->client->email }}</p>
        @endif
        <p><strong>ID: </strong>{{ $invoice->client->company_number }}</p>
        <p><strong>VAT: </strong>{{ $invoice->client->vat_number }}</p>
    </div>
    <div class="invoice__data">
        <p>Faktura: <strong>{{ $invoice->invoice_number }}</strong></p>
        <p>Datum izdavanja: <strong>{{ Carbon::create($invoice->invoice_date)->format('d.m.Y') }}</strong></p>
        <p>Datum prometa:<strong>{{ Carbon::create($invoice->invoice_date)->format('d.m.Y') }}</strong></p>
        <p>Rok za plaćanje: <strong>{{ Carbon::create($invoice->due_date)->format('d.m.Y') }}</strong></p>
    </div>
</div>
