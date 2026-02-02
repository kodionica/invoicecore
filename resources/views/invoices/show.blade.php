@php
    use Carbon\Carbon;
@endphp
<x-layout>
    <div class="page-header pt-3 pb-2 mb-4 border-bottom">
        <h1>Invoice</h1>
        <a href="{{ route('invoices.index') }}">Back</a>
    </div>

    <div class="card mt-5 invoice-view">
        <div class="card-body">
            <div class="invoice__header">
                <div class="invoice__company">
                    <p><strong>{{ $user->invoiceSettings->company_name }}</strong></p>
                    <p>{{ $user->invoiceSettings->company_address }}</p>
                    <p>{{ $user->invoiceSettings->company_phone }}</p>
                    <p>{{ $user->invoiceSettings->company_email }}</p>
                    <p><strong>IBAN: </strong>{{ $user->invoiceSettings->iban }}</p>
                    <p><strong>SWIFT: </strong>{{ $user->invoiceSettings->swift }}</p>
                    <p><strong>PIB: </strong>{{ $user->invoiceSettings->pib }}</p>
                    <p><strong>MB: </strong>{{ $user->invoiceSettings->mb }}</p>
                </div>
                <div class="invoice__client">
                    <p class="section-label">Račun za:</p>
                    <p>{{ $invoice->client->name }}</p>
                    <p>{{ $invoice->client->address }}, {{ $invoice->client->country }}</p>
                    <p>{{ $invoice->client->email }}</p>
                    <p><strong>ID: </strong>{{ $invoice->client->company_number }}</p>
                    <p><strong>VAT: </strong>{{ $invoice->client->vat_number }}</p>
                </div>
                <div class="invoice__data">
                    <p class="section-label">Faktura</p>
                    <p>#{{ $invoice->invoice_number }}</p>
                </div>

                <div class="invoice__total-section">
                    <p class="section-label">Za uplatu</p>
                    <p class=""><strong>Datum izdavanja:</strong> {{ Carbon::create($invoice->invoice_date)->format('d.m.Y') }}</p>
                    <p class=""><strong>Rok za plaćanje:</strong> {{ Carbon::create($invoice->due_date)->format('d.m.Y') }}</p>
                </div>
            </div>
            <div class="container-fluid mt-5 d-flex justify-content-center w-100">
                <div class="table-responsive w-100">
                    <table class="table table-bordered">
                        <thead>
                        <tr>
                            <th>Description</th>
                            <th class="text-end">Quantity</th>
                            <th class="text-end">Unit cost</th>
                            <th class="text-end">Total</th>
                        </tr>
                        </thead>
                        <tbody>
                        <tr class="text-end">
                            <td class="text-start">Freelance usluge programiranja</td>
                            <td>1</td>
                            <td>{{ Number::currency($invoice->total_amount, $invoice->currency) }}</td>
                            <td>{{ Number::currency($invoice->total_amount * 1, $invoice->currency) }}</td>
                        </tr>
                        @foreach($invoice->items as $item)
                            <tr class="text-end">
                                <td class="text-start">{{ $item->description }}</td>
                                <td>{{ $item->quantity }}</td>
                                <td>{{ Number::currency($item->unit_price, $invoice->currency) }}</td>
                                <td>{{ Number::currency($item->unit_price * $item->quantity, $invoice->currency) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="container-fluid mt-5 w-100">
                <div class="row">
                    <div class="col-md-6 ms-auto">
                        <div class="table-responsive">
                            <table class="table">
                                <tbody>
                                <tr>
                                    <td>Payment method</td>
                                    {{--                                    <td class="text-end">{{ $invoice->payments->first()->method }}</td>--}}
                                    <td class="text-end">Nema</td>
                                </tr>
                                <tr class="bg-light">
                                    <td class="text-bold-800">Total</td>
                                    <td class="text-bold-800 text-end">{{ Number::currency($invoice->total_amount, $invoice->currency) }}</td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="container-fluid mt-5 d-flex justify-content-end">
                <div class="actions d-flex gap-3">
                    <a href="{{ route('invoice.pdf', $invoice->id) }}" class="btn btn-outline-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                             stroke-linejoin="round"
                             data-lucide="printer" class="lucide lucide-printer me-2 icon-md">
                            <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                            <path d="M6 9V3a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v6"></path>
                            <rect x="6" y="14" width="12" height="8" rx="1"></rect>
                        </svg>
                        PDF</a>
                    <a href="javascript:;" class="btn btn-outline-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                             stroke-linejoin="round"
                             data-lucide="printer" class="lucide lucide-printer me-2 icon-md">
                            <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                            <path d="M6 9V3a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v6"></path>
                            <rect x="6" y="14" width="12" height="8" rx="1"></rect>
                        </svg>
                        Print</a>
                    <a href="javascript:;" class="btn btn-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                             stroke-linejoin="round"
                             data-lucide="send" class="lucide lucide-send me-3 icon-md">
                            <path d="M14.536 21.686a.5.5 0 0 0 .937-.024l6.5-19a.496.496 0 0 0-.635-.635l-19 6.5a.5.5 0 0 0-.024.937l7.93 3.18a2 2 0 0 1 1.112 1.11z"></path>
                            <path d="m21.854 2.147-10.94 10.939"></path>
                        </svg>
                        Send Invoice</a>
                </div>
            </div>
        </div>
    </div>
</x-layout>
