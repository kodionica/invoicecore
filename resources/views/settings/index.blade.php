<x-layout>
    <div class="page-header pt-3 pb-2 mb-4 border-bottom">
        <h1>Izmeni podatke firme</h1>
    </div>

    <x-forms.form method="POST" action="{{ route('settings.invoice.update', $settings) }}" class="d-flex flex-column justify-content-start gap-3" enctype="multipart/form-data">
        @method('PUT')

        <x-forms.input label="Naziv" name="company_name" type="text" :value="$settings->company_name" required/>
        <div class="company-logo">
            <div class="input-group">
                <input type="file" class="form-control" id="logo" name="logo" aria-describedby="Logo" aria-label="Logo">
                <img src="{{ asset('storage/' . $settings->logo_path) }}" alt="" width="50" height="50">
            </div>
        </div>
        <x-forms.input label="Adresa" name="company_address" type="text" :value="$settings->company_address" required/>
        <x-forms.select label="Država" name="company_state" :options="get_currencies()" :value="old('company_state', $settings->company_state ?? null)"/>
        <x-forms.input label="Email" name="company_email" type="email" :value="$settings->company_email" required/>
        <x-forms.input label="Telefon" name="company_phone" type="tel" :value="$settings->company_phone" required/>
        <x-forms.input label="PIB" name="pib" type="number" :value="$settings->pib" required/>
        <x-forms.input label="Matični broj" name="mb" type="number" :value="$settings->mb" required/>
        <x-forms.input label="Tekući žiro račun" name="bank_account" type="text" :value="$settings->bank_account"/>
        <x-forms.input label="IBAN" name="iban" type="text" :value="$settings->iban"/>
        <x-forms.input label="SWIFT" name="swift" type="text" :value="$settings->swift"/>
        <x-forms.select label="Osnovna valuta" name="default_currency" :options="get_currencies()" :value="old('default_currency', $settings->default_currency ?? null)"/>
        <x-forms.input label="Osnovni rok za uplatu" name="default_due_days" type="number" :value="$settings->default_due_days"/>
        <x-forms.textarea label="Dodatni tekst za fakturu" name="footer_note" :value="$settings->footer_note"/>

        <div class="form-actions">
            <x-forms.button>Sačuvaj</x-forms.button>
        </div>
    </x-forms.form>
</x-layout>
