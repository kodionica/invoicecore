<x-layout>
    <div class="page-header pt-3 pb-2 mb-4 border-bottom">
        <h1>Edit client: {{ $client->name }}</h1>
        <a href="{{ route('client.index') }}">Back</a>
    </div>

    <x-forms.form method="POST" action="/clients/{{ $client->id }}">
        @method('PATCH')

        <x-forms.input label="Name" name="name" type="text" :value="$client->name" required/>
        <x-forms.input label="Email" name="email" type="email" :value="$client->email" required/>
        <x-forms.input label="Address" name="address" type="text" :value="$client->address" required/>
        <x-forms.input label="Country" name="country" type="text" :value="$client->country" required/>
        <x-forms.input label="VAT Number" name="vat_number" type="text" :value="$client->vat_number" required/>
        <x-forms.input label="Company number" name="company_number" type="text" :value="$client->company_number"/>

        <x-forms.button>Update Client</x-forms.button>

    </x-forms.form>
</x-layout>
