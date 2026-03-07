<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Laravel') }}</title>

    @if (!empty($pdf_css_inline))
        <style>
            {!! $pdf_css_inline !!}
        </style>
    @elseif (!empty($pdf_css_url))
        <link rel="stylesheet" href="{{ $pdf_css_url }}">
    @endif
</head>
<body class="invoices-pdf {{ $body_class ?? '' }}">
<div class="invoice-view">
    @include('invoices.partials.invoice-header', compact('company', 'client', 'invoice'))

    @include('invoices.partials.products', compact('invoice_items', 'company'))

    @include('invoices.partials.totals', compact('invoice', 'company'))
</div>
</body>
</html>
