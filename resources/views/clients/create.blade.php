<x-layout>
    <div class="page-header pt-3 pb-2 mb-4 border-bottom">
        <h1>Add client</h1>
    </div>

    <x-forms.form method="POST" action="{{ route('clients.index') }}" class="d-flex flex-column justify-content-start gap-3">
        <x-forms.input label="Name" name="name" type="text" required/>
        <x-forms.input label="Email" name="email" type="email" required/>
        <x-forms.input label="Address" name="address" type="text" required/>
        <x-forms.input label="Country" name="country" type="text" required/>
        <x-forms.input label="VAT Number" name="vat_number" type="text" required/>
        <x-forms.input label="Company number" name="company_number" type="text"/>

        <div class="form-actions">
            <x-forms.button>Add Client</x-forms.button>
            <a href="{{ route('clients.index') }}" class="btn btn-outline-danger">Cancel</a>
        </div>
    </x-forms.form>
</x-layout>
