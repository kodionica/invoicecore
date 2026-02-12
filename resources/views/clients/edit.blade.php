<x-layouts.layout>
    <x-page-header heading="Izmeni klijenta"/>

    <x-forms.form method="PATCH" action="{{ route('clients.edit', $client) }}">
        <x-forms.input label="Ime" name="name" type="text" required value="{{ $client->name }}"/>
        <x-forms.input label="Email" name="email" type="email" value="{{ $client->email }}"/>
        <x-forms.input label="Adresa" name="address" type="text" value="{{ $client->address }}"/>
        <x-forms.input label="Grad" name="city" type="text" value="{{ $client->city }}"/>
        <x-forms.input label="Država" name="country" type="text" value="{{ $client->country }}"/>
        <x-forms.input label="Telefon" name="phone" type="tel" value="{{ $client->phone }}"/>
        <x-forms.input label="PIB/VAT" name="tax_id" type="text" required value="{{ $client->tax_id }}"/>
        <x-forms.input label="Matični broj" name="registration_number" type="text" value="{{ $client->registration_number }}"/>

        <div class="form-actions">
            <button class="btn btn-primary" type="submit">Dodaj klijenta</button>
            <button class="btn btn-danger" type="submit" form="delete-form">Obriši klijenta</button>
            <a href="{{ route('clients.index') }}" class="btn btn-outline-danger">Otkaži</a>
        </div>
    </x-forms.form>

    <x-forms.form method="DELETE" action="{{ route('clients.destroy', $client) }}" class="sr-only" id="delete-form"/>
</x-layouts.layout>
