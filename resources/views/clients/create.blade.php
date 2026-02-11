<x-layouts.layout>
    <x-page-header heading="Dodaj klijenta"/>

    <x-forms.form method="POST" action="{{ route('client.index') }}">
        <x-forms.input label="Ime" name="name" type="text" required/>
        <x-forms.input label="Email" name="email" type="email"/>
        <x-forms.input label="Adresa" name="address" type="text"/>
        <x-forms.input label="Grad" name="city" type="text"/>
        <x-forms.input label="Država" name="country" type="text"/>
        <x-forms.input label="Telefon" name="phone" type="tel"/>
        <x-forms.input label="PIB/VAT" name="tax_id" type="text" required/>
        <x-forms.input label="Matični broj" name="registration_number" type="text"/>

        <div class="form-actions">
            <button class="btn btn-primary" type="submit">Dodaj klijenta</button>
            <a href="{{ route('client.index') }}" class="btn btn-outline-danger">Otkaži</a>
        </div>
    </x-forms.form>
</x-layouts.layout>
