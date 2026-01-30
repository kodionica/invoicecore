@php
    use Carbon\Carbon;
@endphp
<x-layout>
    <div class="page-header pt-3 pb-2 mb-4 border-bottom">
        <h1>Invoice</h1>
        <a href="{{ route('invoices.index') }}">Back</a>
    </div>

    <div class="card mt-5">
        <div class="card-body">
            <div class="container-fluid d-flex justify-content-between">
                <div class="col-lg-3 ps-0">
                    <p class="mt-1 mb-1"><b>{{ $user->invoiceSettings->company_name }}</b></p>
                    <p class="mb-0">{{ $user->invoiceSettings->company_address }}</p>
                    <p class="mb-0">{{ $user->invoiceSettings->company_phone }}</p>
                    <p class="mb-0">{{ $user->invoiceSettings->company_email }}</p>
                    <h5 class="mt-5 mb-2 text-secondary">Invoice to:</h5>
                    <p class="mb-0">{{ $invoice->client->name }}</p>
                    <p class="mb-0">{{ $invoice->client->address }}</p>
                    <p class="mb-0">{{ $invoice->client->email }}</p>
                </div>
                <div class="col-lg-3 pe-0">
                    <h4 class="fw-bold text-uppercase text-end mt-4 mb-2">Invoice</h4>
                    <h6 class="text-end mb-5 pb-4"># {{ $invoice->invoice_number }}</h6>
                    <p class="text-end mb-1">Balance Due</p>
                    <h4 class="text-end fw-normal">{{ Number::currency($invoice->total_amount, $invoice->currency) }}</h4>
                    <h6 class="mb-0 mt-3 text-end fw-normal"><span class="text-secondary">Invoice Date :</span> {{ Carbon::create($invoice->invoice_date)->format('d.m.Y') }}</h6>
                    <h6 class="text-end fw-normal"><span class="text-secondary">Due Date :</span> {{ Carbon::create($invoice->due_date)->format('d.m.Y') }}</h6>
                </div>
            </div>
            <div class="container-fluid mt-5 d-flex justify-content-center w-100">
                <div class="table-responsive w-100">
                    <table class="table table-bordered">
                        <thead>
                        <tr>
                            <th>#</th>
                            <th>Description</th>
                            <th class="text-end">Quantity</th>
                            <th class="text-end">Unit cost</th>
                            <th class="text-end">Total</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($invoice->items as $item)
                            <tr class="text-end">
                                <td class="text-start">{{ $loop->iteration }}</td>
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
                                    <td class="text-end">{{ $invoice->payments->first()->method }}</td>
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
            <div class="container-fluid mt-5 d-flex justify-content-between">
                <div class="signature border-top pt-1 col-3">
                    <p class="mb-1">Authorized person</p>
                </div>
                <div class="actions d-flex gap-3">
                    <a href="javascript:;" class="btn btn-outline-primary">
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
