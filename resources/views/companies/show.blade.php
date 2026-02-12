<x-layouts.layout>
    <div class="page-header pt-3 pb-2 mb-4 border-bottom">
        <h1>Izmeni</h1>
    </div>

    <x-forms.form method="PATCH" action="{{ route('companies.update', $company) }}" enctype="multipart/form-data">
        <x-forms.input name="name" label="Naziv firme" autocomplete="organization" :value="$company->name"/>
        <x-forms.input name="tax_id" label="PIB/VAT" :value="$company->tax_id"/>
        <x-forms.input name="registration_number" label="Matični broj" :value="$company->registration_number"/>
        <x-forms.input name="address" label="Adresa" autocomplete="street-address" :value="$company->address"/>
        <x-forms.input name="city" label="Grad" autocomplete="address-level2" :value="$company->city"/>
        <x-forms.input name="country" label="Država" autocomplete="country-name" :value="$company->country"/>
        <x-forms.input name="email" label="Email" autocomplete="email" type="email" :value="$company->email"/>
        <x-forms.input name="phone" label="Telefon" autocomplete="tel" type="tel" :value="$company->phone"/>
        <x-forms.input name="bank_account" label="Bankovni račun" :value="$company->bank_account"/>
        <x-forms.input name="iban" label="IBAN" :value="$company->iban"/>
        <x-forms.input name="swift" label="SWIFT" :value="$company->swift"/>
        <x-forms.select name="currency" label="Valuta" :options="$currencies" :value="$company->currency"/>
        <x-forms.checkbox name="vat_enabled" label="U sistemu PDV?" :value="$company->vat_enabled"/>

        <x-forms.input name="invoice_prefix" label="Prefiks za fakture" :value="$company->invoice_prefix"/>
        <x-forms.input name="invoice_start_number" label="Početni broj fakture" type="number" :value="$company->invoice_start_number"/>
        <x-forms.input name="invoice_next_number" label="Sledeći broj fakture" type="number" :value="$company->invoice_next_number"/>
        <x-forms.input name="default_tax_percent" label="Osnovna visina poreza u procentima" :value="$company->default_tax_percent"/>
        <x-forms.input name="payment_due_days" label="Rok za plaćanje" type="number" :value="$company->payment_due_days"/>
        <x-forms.textarea name="invoice_note" label="Dodatni tekst za fakturu" :value="$company->invoice_note"/>

        <div class="form-group">
            <x-forms.input name="logo" label="Logo" type="file"/>
            <img src="{{ asset("storage/$company->logo_path") }}" alt="{{ $company->name }} logo">
        </div>

        <div class="form-group columns-12">
            <button type="submit" class="btn btn-primary">Ažuriraj</button>
            <a href="{{ route('companies.index') }}" class="btn btn-outline-danger">Otkaži</a>
        </div>
    </x-forms.form>
</x-layouts.layout>
