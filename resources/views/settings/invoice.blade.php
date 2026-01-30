<x-layout>
    <div class="page-header pt-3 pb-2 mb-4 border-bottom">
        <h1>Manage settings</h1>
    </div>

    <x-forms.form method="POST" action="{{ route('settings.invoice.update', $settings) }}" class="d-flex flex-column justify-content-start gap-3">
        @method('PUT')

        <x-forms.input label="Company name" name="company_name" type="text" :value="$settings->company_name" required/>
        <x-forms.input label="Company address" name="company_address" type="text" :value="$settings->company_address" required/>
        <x-forms.input label="Company email" name="company_email" type="email" :value="$settings->company_email" required/>
        <x-forms.input label="Company phone" name="company_phone" type="tel" :value="$settings->company_phone" required/>
        <x-forms.input label="PIB" name="pib" type="number" :value="$settings->pib" required/>
        <x-forms.input label="IBAN" name="iban" type="text" :value="$settings->iban"/>
        <x-forms.input label="SWIFT" name="swift" type="text" :value="$settings->swift"/>
        <x-forms.select label="Default currency" name="default_currency" :options="get_currencies()" :value="old('default_currency', $settings->default_currency ?? null)"/>
        <x-forms.input label="Default due days" name="default_due_days" type="number" :value="$settings->swift"/>
        <x-forms.input label="Invoice footer note" name="footer_note" type="text" :value="$settings->footer_note"/>

        <div class="form-actions">
            <x-forms.button>Save Settings</x-forms.button>
        </div>
    </x-forms.form>
</x-layout>
