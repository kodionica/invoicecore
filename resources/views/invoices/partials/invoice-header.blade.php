@php
    /**
     * @global \App\Models\Company $company
     * @global \App\Models\Client $client
     * @global  \App\Models\Invoice $invoice
     *
     */
        use Carbon\Carbon;
@endphp

<div class="invoice__header">
    <div class="invoice__company">
        <div class="invoice__company__data">
            <p class="section-label section-label--small">Od:</p>
            <p class="section-label section-label--large">{{ $company->name }}</p>
            <p><strong>Adresa: </strong>{{ $company->address }}</p>
            <p><strong>Grad: </strong>{{ $company->city }}</p>
            <p><strong>Telefon: </strong>{{ $company->phone }}</p>
            <p><strong>Email: </strong>{{ $company->email }}</p>
            <p><strong>PIB: </strong>{{ $company->tax_id }}</p>
            <p><strong>MB: </strong>{{ $company->registration_number }}</p>
            <p><strong>Broj računa: </strong>{{ $company->bank_account }}</p>
            <p><strong>IBAN: </strong>{{ $company->iban }}</p>
            <p><strong>SWIFT: </strong>{{ $company->swift }}</p>
        </div>

        @if($company->logo_path)
            <div class="invoice__company__logo">
                <img src="{{ public_path('storage/' . $company->logo_path) }}" alt="Logo">
            </div>
        @endif
    </div>
    <div class="invoice__client">
        <p class="section-label section-label--small">Za:</p>
        <p class="section-label section-label--large">{{ $client->name }}</p>
        <p><strong>Adresa: </strong>{{ $client->address }}</p>
        <p><strong>Grad: </strong>{{ $client->city }}</p>
        <p><strong>Država: </strong>{{ $client->country }}</p>
        @if($client->email)
            <p><strong>Email: </strong>{{ $client->email }}</p>
        @endif
        @if($client->phone)
            <p><strong>Email: </strong>{{ $client->phone }}</p>
        @endif
        <p><strong>VAT: </strong>{{ $client->tax_id }}</p>
        <p><strong>ID: </strong>{{ $client->registration_number }}</p>
    </div>
    <div class="invoice__data">
        <p>Faktura: <strong>{{ $invoice->invoice_number }}</strong></p>
        <p>Datum izdavanja: <strong>{{ Carbon::create($invoice->issue_date)->format('d.m.Y') }}</strong></p>
        <p>Datum prometa:<strong>{{ Carbon::create($invoice->issue_date)->format('d.m.Y') }}</strong></p>
        <p>Rok za plaćanje: <strong>{{ Carbon::create($invoice->due_date)->format('d.m.Y') }}</strong></p>
    </div>
</div>
