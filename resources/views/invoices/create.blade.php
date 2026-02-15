<x-layouts.layout>
    <x-page-header heading="Dodaj fakturu"/>

    <x-forms.form method="POST" action="{{ route('invoices.store') }}">
        <x-forms.select label="Valuta" name="currency" :value="$active_company->currency" :options="$currencies"/>
        <x-forms.select label="Klijent" name="client_id" :options="$clients"/>
        <x-forms.input label="Rok za isplatu (dana)" name="due_date" type="number" :value="$active_company->payment_due_days" required/>
        <x-forms.select label="Metod plaćanja" name="payment_method" :options="$payment_types"/>

       @include('invoices.partials.invoice-items')

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Napravi fakturu</button>
            <a href="{{ route('invoices.index') }}" class="btn btn-outline-danger">Otkaži</a>
        </div>
    </x-forms.form>

</x-layouts.layout>
