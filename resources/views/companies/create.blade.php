<x-layouts.layout>
    <div class="page-header pt-3 pb-2 mb-4 border-bottom">
        <h1>Dodaj firmu</h1>
    </div>

    <x-forms.form method="POST" action="{{ route('companies.index') }}" enctype="multipart/form-data">
        <x-forms.input name="name" label="Naziv firme" autocomplete="organization"/>
        <x-forms.input name="tax_id" label="PIB/VAT"/>
        <x-forms.input name="registration_number" label="Matični broj"/>
        <x-forms.input name="address" label="Adresa" autocomplete="street-address"/>
        <x-forms.input name="city" label="Grad" autocomplete="address-level2"/>
        <x-forms.input name="country" label="Država" autocomplete="country-name"/>
        <x-forms.input name="email" label="Email" autocomplete="email" type="email"/>
        <x-forms.input name="phone" label="Telefon" autocomplete="tel" type="tel"/>
        <x-forms.input name="bank_account" label="Bankovni račun"/>
        <x-forms.input name="iban" label="IBAN"/>
        <x-forms.input name="swift" label="SWIFT"/>
        <x-forms.select name="currency" label="Valuta" :options="$currencies"/>
        <x-forms.checkbox name="vat_enabled" label="U sistemu PDV?"/>
        <x-forms.input name="logo" label="Logo" type="file"/>

        <div class="form-group columns-12">
            <button type="submit" class="btn btn-primary">Dodaj firmu</button>
            <a href="{{ route('companies.index') }}" class="btn btn-outline-danger">Otkaži</a>
        </div>
    </x-forms.form>
</x-layouts.layout>
