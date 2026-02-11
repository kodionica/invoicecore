<x-layouts.layout>
    <div class="page-header pt-3 pb-2 mb-4 border-bottom">
        <h1>Izmeni</h1>
    </div>

    <x-forms.form method="POST" action="{{ route('company.update', $company) }}" enctype="multipart/form-data" class="d-flex flex-column justify-content-start gap-3">
        @method('PATCH')

        <div class="form-row">
            <div class="form-floating ">
                <input type="text" name="name" class="form-control" id="name" placeholder="Naziv firme" required autocomplete="organization" value="{{ $company->name }}">
                <label for="name">Naziv firme</label>
            </div>
            <div class="form-floating ">
                <input type="text" name="tax_id" class="form-control" id="tax_id" placeholder="PIB" required value="{{ $company->tax_id }}">
                <label for="tax_id">PIB/VAT</label>
            </div>
            <div class="form-floating ">
                <input type="text" name="registration_number" class="form-control" id="registration_number" placeholder="Matični broj" required value="{{ $company->registration_number }}">
                <label for="registration_number">Matični broj</label>
            </div>
            <div class="form-floating ">
                <input type="text" name="address" class="form-control" id="address" placeholder="Adresa" autocomplete="street-address" value="{{ $company->address }}">
                <label for="address">Adresa</label>
            </div>
            <div class="form-floating ">
                <input type="text" name="city" class="form-control" id="city" placeholder="Grad" autocomplete="address-level2" value="{{ $company->city }}">
                <label for="city">Grad</label>
            </div>
            <div class="form-floating ">
                <input type="text" name="country" class="form-control" id="country" placeholder="Država" autocomplete="country-name" value="{{ $company->country }}">
                <label for="country">Država</label>
            </div>
            <div class="form-floating ">
                <input type="email" name="email" class="form-control" id="email" placeholder="Email" autocomplete="email" value="{{ $company->email }}">
                <label for="email">Email</label>
            </div>
            <div class="form-floating ">
                <input type="tel" name="phone" class="form-control" id="phone" placeholder="Telefon" autocomplete="tel" value="{{ $company->phone }}">
                <label for="phone">Telefon</label>
            </div>
            <div class="form-floating ">
                <input type="text" name="bank_account" class="form-control" id="bank_account" placeholder="Bankovni račun" value="{{ $company->bank_account }}">
                <label for="bank_account">Bankovni račun</label>
            </div>
            <div class="form-floating ">
                <input type="text" name="iban" class="form-control" id="iban" placeholder="IBAN" value="{{ $company->iban }}">
                <label for="iban">IBAN</label>
            </div>
            <div class="form-floating ">
                <input type="text" name="swift" class="form-control" id="swift" placeholder="SWIFT" value="{{ $company->swift }}">
                <label for="swift">SWIFT</label>
            </div>
            <div class="form-floating">
                <input type="file" name="logo" class="form-control" id="logo">
                <label for="logo" class="visually-hidden">Logo</label>
                {{--                TODO: Add option to remove the logo --}}

                <img src="{{ asset("storage/$company->logo_path") }}" alt="{{ $company->name }} logo">
            </div>

            <div class="form-group columns-12">
                <button type="submit" class="btn btn-primary">Ažuriraj</button>
                <a href="{{ route('company.index') }}" class="btn btn-outline-danger">Otkaži</a>
                <a href="{{ route('company.settings.edit', $company) }}" class="btn btn-outline-primary">Izmeni podešavanja firme</a>
            </div>
        </div>
    </x-forms.form>
</x-layouts.layout>
