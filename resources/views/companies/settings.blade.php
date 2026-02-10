<x-layouts.layout>
    <div class="page-header pt-3 pb-2 mb-4 border-bottom">
        <h1>Izmeni podešavanja firme</h1>
    </div>

    <x-forms.form method="POST" action="{{ route('company.settings.update', $company) }}" enctype="multipart/form-data" class="d-flex flex-column justify-content-start gap-3">
        @method('PATCH')

        <div class="form-row">
            <div class="form-floating ">
                <input type="text" name="invoice_prefix" class="form-control" id="invoice_prefix" placeholder="Prefiks za fakture" required value="{{ $company_settings->invoice_prefix }}">
                <label for="invoice_prefix">Prefiks za fakture</label>
            </div>
            <div class="form-floating ">
                <input type="number" name="invoice_start_number" class="form-control" id="invoice_start_number" placeholder="Početni broj fakture" required value="{{ $company_settings->invoice_start_number }}">
                <label for="invoice_start_number">Početni broj fakture</label>
            </div>
            <div class="form-floating ">
                <input type="number" name="invoice_next_number" class="form-control" id="invoice_next_number" placeholder="Sledeći broj fakture" required value="{{ $company_settings->invoice_next_number }}">
                <label for="invoice_next_number">Sledeći broj fakture</label>
            </div>
            <div class="form-floating ">
                <select name="currency" class="form-control" id="currency">
                    @foreach(config('currency') as $code => $currency)
                        <option value="{{ $code }}" @selected($code === $company_settings->currency)>{{ $currency['name'] }}</option>
                    @endforeach
                </select>
                <label for="currency">Valuta</label>
            </div>
            <div class="form-floating ">
                <input type="number" name="default_tax_percent" class="form-control" id="default_tax_percent" placeholder="Osnovna visina poreza u procentima" min="0" max="100"
                       value="{{ $company_settings->default_tax_percent }}">
                <label for="default_tax_percent">Osnovna visina poreza u procentima</label>
            </div>
            <div class="form-floating ">
                <input type="number" name="payment_due_days" class="form-control" id="payment_due_days" placeholder="Rok za plaćanje" value="{{ $company_settings->payment_due_days }}">
                <label for="payment_due_days">Rok za plaćanje</label>
            </div>
            <div class="form-floating ">
                <textarea name="invoice_note" class="form-control" id="invoice_note" placeholder="Dodatni tekst za fakturu">{{ $company_settings->invoice_note }}</textarea>
                <label for="invoice_note">Dodatni tekst za fakturu</label>
            </div>
            <div class="form-check form-switch ">
                <input type="checkbox" name="vat_enabled" value="1" class="form-check-input" id="vat_enabled" @checked($company_settings->vat_enabled)>
                <label class="form-check-label" for="vat_enabled">U sistemu PDV?</label>
            </div>

            <div class="form-group columns-12">
                <button type="submit" class="btn btn-primary">Sačuvaj podešavanja</button>
                <a href="{{ route('company.show', $company) }}" class="btn btn-outline-danger">Otkaži</a>
            </div>
        </div>
    </x-forms.form>
</x-layouts.layout>
