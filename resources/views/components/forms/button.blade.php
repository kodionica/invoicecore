@php
    $classes = 'btn ' . ($attributes->get('class') ?? 'btn-primary');
@endphp

<button {{ $attributes(['class' => $classes]) }}>{{ $slot }}</button>
