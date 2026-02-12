@php use Carbon\Carbon; @endphp
<x-layout>

    <div class="card-box table-responsive">
        <p class="text-muted font-13 m-b-30">
            The Buttons extension for DataTables provides a common set of options, API methods and styling to display buttons on a page that will interact with a DataTable. The core library provides
            the based framework upon which plug-ins can built.
        </p>
        <div id="datatable-buttons_wrapper" class="dataTables_wrapper container-fluid dt-bootstrap no-footer">
            <div class="dt-buttons btn-group">
                <button type="button" class="btn btn-outline-primary btn--csv" data-option="csv">CSV</button>
                <button type="button" class="btn btn-outline-primary btn--pdf" data-option="pdf">PDF</button>
                <button type="button" class="btn btn-outline-primary btn--print" data-option="print">Print</button>
            </div>
            <div class="dataTables_length" id="datatable-buttons_length">
                <label>Show
                    <select name="datatable-buttons_length" class="form-control input-sm">
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select> entries
                </label>
            </div>
            <div id="datatable-buttons_filter" class="dataTables_filter">
                <label>Search:<input type="search" class="form-control input-sm" placeholder="" aria-controls="datatable-buttons"></label>
            </div>
            <table id="datatable-buttons" class="table table-striped">
                <thead>
                <tr role="row">
                    <th>
                        <x-forms.checkbox id="select-all-checkbox" name="" class="select-all-checkbox"/>
                    </th>
                    <th>Invoice</th>
                    <th>Date</th>
                    <th>Due date</th>
                    <th>Payed via</th>
                    <th>Total</th>
                    <th>Client</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                @foreach($invoices as $invoice)
                    <tr role="row" class="{{ $loop->odd ? 'odd' : 'even' }}">
                        <td>
                            <x-forms.checkbox name="selected_invoices[]" value="{{ $invoice->id }}" class="select-all"/>
                        </td>
                        <td>
                            <span class="text">
                                <a href="{{ route('invoices.show', $invoice ) }}">{{ $invoice->invoice_number }}</a>
                            </span>
                        </td>
                        <td>{{ Carbon::create($invoice->invoice_date)->format('d.m.Y') }}</td>
                        <td>{{ $invoice->due_date ? Carbon::create($invoice->due_date)->format('d.m.Y') : 'N/A' }}</td>
                        <td>{{ $invoice->payments->first()?->method ?: 'N/A' }}</td>
                        <td>{{ Number::currency($invoice->total_amount, $invoice->currency) }}</td>
                        <td>
                            <a href="{{ route('clients.edit', $invoice->client) }}">{{ $invoice->client->name }}</a>
                        </td>
                        <td>
                            <div class="actions">
                                <a href="{{ route('invoices.edit', $invoice) }}" class="action__item action__item--edit">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-pencil-fill" viewBox="0 0 16 16">
                                        <path
                                            d="M12.854.146a.5.5 0 0 0-.707 0L10.5 1.793 14.207 5.5l1.647-1.646a.5.5 0 0 0 0-.708zm.646 6.061L9.793 2.5 3.293 9H3.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.207zm-7.468 7.468A.5.5 0 0 1 6 13.5V13h-.5a.5.5 0 0 1-.5-.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.5-.5V10h-.5a.5.5 0 0 1-.175-.032l-.179.178a.5.5 0 0 0-.11.168l-2 5a.5.5 0 0 0 .65.65l5-2a.5.5 0 0 0 .168-.11z"/>
                                    </svg>
                                </a>
                                @if($invoice->pdf_path)
                                    <a href="{{ $invoice->pdf_path }}" class="action__item action__item--pdf" download>
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-pencil-fill" viewBox="0 0 16 16">
                                            <path
                                                d="M12.854.146a.5.5 0 0 0-.707 0L10.5 1.793 14.207 5.5l1.647-1.646a.5.5 0 0 0 0-.708zm.646 6.061L9.793 2.5 3.293 9H3.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.207zm-7.468 7.468A.5.5 0 0 1 6 13.5V13h-.5a.5.5 0 0 1-.5-.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.5-.5V10h-.5a.5.5 0 0 1-.175-.032l-.179.178a.5.5 0 0 0-.11.168l-2 5a.5.5 0 0 0 .65.65l5-2a.5.5 0 0 0 .168-.11z"/>
                                        </svg>
                                    </a>
                                @endif
                                <form action="{{ route('invoices.destroy', $invoice) }}" method="POST" class="action__item action__item--remove">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="action-icon btn btn-link p-0 m-0 align-baseline">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-trash3-fill" viewBox="0 0 16 16">
                                            <path
                                                d="M11 1.5v1h3.5a.5.5 0 0 1 0 1h-.538l-.853 10.66A2 2 0 0 1 11.115 16h-6.23a2 2 0 0 1-1.994-1.84L2.038 3.5H1.5a.5.5 0 0 1 0-1H5v-1A1.5 1.5 0 0 1 6.5 0h3A1.5 1.5 0 0 1 11 1.5m-5 0v1h4v-1a.5.5 0 0 0-.5-.5h-3a.5.5 0 0 0-.5.5M4.5 5.029l.5 8.5a.5.5 0 1 0 .998-.06l-.5-8.5a.5.5 0 1 0-.998.06m6.53-.528a.5.5 0 0 0-.528.47l-.5 8.5a.5.5 0 0 0 .998.058l.5-8.5a.5.5 0 0 0-.47-.528M8 4.5a.5.5 0 0 0-.5.5v8.5a.5.5 0 0 0 1 0V5a.5.5 0 0 0-.5-.5"/>
                                        </svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <div class="dataTables_info" id="datatable-buttons_info" role="status" aria-live="polite">Showing 1 to 10 of 57 entries</div>
            <div class="dataTables_paginate paging_simple_numbers" id="datatable-buttons_paginate">
                <ul class="pagination">
                    <li class="paginate_button previous disabled" id="datatable-buttons_previous"><a href="#" aria-controls="datatable-buttons" data-dt-idx="0" tabindex="0">Previous</a></li>
                    <li class="paginate_button active"><a href="#" aria-controls="datatable-buttons" data-dt-idx="1" tabindex="0">1</a></li>
                    <li class="paginate_button "><a href="#" aria-controls="datatable-buttons" data-dt-idx="2" tabindex="0">2</a></li>
                    <li class="paginate_button "><a href="#" aria-controls="datatable-buttons" data-dt-idx="3" tabindex="0">3</a></li>
                    <li class="paginate_button next" id="datatable-buttons_next"><a href="#" aria-controls="datatable-buttons" data-dt-idx="7" tabindex="0">Next</a></li>
                </ul>
            </div>
        </div>
    </div>
</x-layout>
