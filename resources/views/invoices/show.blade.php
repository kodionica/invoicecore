@section('page-style')
    @vite('resources/css/invoice.scss')
@endsection

<x-layout>
    @if(!request()->routeIs('invoice.pdf'))
        <x-page-header heading="Faktura {{ $invoice->invoice_number }}" link_title="Nazad" :link_url="route('invoices.index')" wrapper_class="pt-3 pb-2 mb-4 border-bottom"/>
    @endif

    <div class="invoice-view">
        @include('invoices.partials.invoice-header', compact('user','invoice'))

        @include('invoices.partials.products', compact('invoice'))

        @include('invoices.partials.totals', compact('invoice', 'user'))

        @includeWhen(!request()->routeIs('invoice.pdf'), 'invoices.partials.actions', compact('invoice'))
    </div>
</x-layout>
