<x-layout>
    <div class="page-header pt-3 pb-2 mb-4 border-bottom">
        <h1>Add invoice</h1>
    </div>

    <x-forms.form method="POST" action="{{ route('invoices.index') }}" class="d-flex flex-column justify-content-start gap-3">
        <x-forms.input label="Service/Product" name="service" type="text" required/>
        <x-forms.input label="Quantity" name="quantity" type="number" required/>
        <x-forms.input label="Price" name="price" type="text" required/>
        <x-forms.select label="Currency" name="currency" :options="$currencies" />
        <x-forms.select label="Client" name="client_id" :options="$clients" />

        <div class="form-actions">
            <x-forms.button>Add Invoice</x-forms.button>
            <a href="{{ route('invoices.index') }}" class="btn btn-outline-danger">Cancel</a>
        </div>
    </x-forms.form>
</x-layout>
