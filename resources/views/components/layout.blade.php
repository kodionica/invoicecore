<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark light"/>
    <title>{{ config('app.name', 'Laravel') }}</title>

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @isset($stle)
            @vite($style)
        @else
            @vite('resources/css/app.scss')
        @endisset
    @endif
</head>
<body class="{{ Auth::user() ? 'user--logged-in' : 'user--guest' }}">
<x-sidebar/>

<x-header/>

<main id="main" class="container">{{ $slot }}</main>

@if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
    @isset($script)
        @vite($script)
    @else
        @vite('resources/js/app.js')
    @endisset
@endif
</body>
</html>
