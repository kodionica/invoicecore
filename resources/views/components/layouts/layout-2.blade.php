<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark light"/>
    <title>{{ config('app.name', 'Laravel') }}</title>

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @hasSection('page-style')
            @yield('page-style')
        @else
            @vite('resources/css/app.scss')
        @endif
    @endif
</head>
<body class="{{ $body_class ?? '' }}">

<main id="main" class="container">
    @if(session()->has('flash'))
        <x-notices.notice :notice="session('flash')"/>
    @endif

    {{ $slot }}
</main>

@if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
    @hasSection('page-script')
        @yield('page-script')
    @else
        @vite('resources/js/app.js')
    @endif
@endif
</body>
</html>
