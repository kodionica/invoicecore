<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark light"/>
    <title>{{ config('app.name', 'Laravel') }}</title>

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite('resources/css/invoice.scss')
    @endif
</head>
<body class="{{ $body_class ?? '' }}">
<main id="main" class="container">
    <div class="invoice-view">
        @include('invoices.partials.invoice-header', compact('user','invoice'))

        @include('invoices.partials.products', compact('invoice'))

        @include('invoices.partials.totals', compact('invoice', 'user'))
    </div>
</main>
</body>
</html>
