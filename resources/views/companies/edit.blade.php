<x-layout>
    <div class="page-header pt-3 pb-2 mb-4 border-bottom">
        <h1>Edit client: {{ $client->name }}</h1>
        <a href="{{ route('clients.index') }}">Back</a>
    </div>

    <x-forms.form method="POST" action="{{ route('clients.edit', $client) }}" class="d-flex flex-column justify-content-start gap-3">
        @method('PATCH')

        <x-forms.input label="Name" name="name" type="text" :value="$client->name" required/>
        <x-forms.input label="Email" name="email" type="email" :value="$client->email" required/>
        <x-forms.input label="Address" name="address" type="text" :value="$client->address" required/>
        <x-forms.input label="Country" name="country" type="text" :value="$client->country" required/>
        <x-forms.input label="VAT Number" name="vat_number" type="text" :value="$client->vat_number" required/>
        <x-forms.input label="Company number" name="company_number" type="text" :value="$client->company_number"/>

        <div class="form-actions">
            <x-forms.button form="delete-form">Update Client</x-forms.button>
            <x-forms.button class="btn-danger">Delete Client</x-forms.button>
        </div>
    </x-forms.form>

    <form action="{{ route('clients.destroy', $client) }}" method="POST" class="sr-only" id="delete-form">
        @csrf
        @method('DELETE')
    </form>
</x-layout>
