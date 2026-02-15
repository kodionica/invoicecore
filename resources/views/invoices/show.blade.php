@section('page-style')
    @vite('resources/css/invoice.scss')
@endsection

<x-layouts.layout>
    <x-page-header heading="Faktura {{ $invoice->invoice_number }}" link_title="Nazad" :link_url="route('invoices.index')" wrapper_class="pt-3 pb-2 mb-4 border-bottom"/>

    <div class="invoice-view">
        @include('invoices.partials.invoice-header', compact('company', 'client', 'invoice'))

        @include('invoices.partials.products', compact('invoice_items', 'company'))

        @include('invoices.partials.totals', compact('invoice', 'company'))

        @include( 'invoices.partials.actions', compact('invoice'))
    </div>
</x-layouts.layout>
