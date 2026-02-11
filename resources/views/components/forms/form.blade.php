@php
    $form_classes = 'form needs-validation ' . ($errors->any() ? 'was-validated' : '');
@endphp

<form {{ $attributes(['class' => $form_classes, 'method' => 'GET']) }}>
    @if ($attributes->get('method', 'GET') !== 'GET')
        @csrf
        @method($attributes->get('method'))
    @endif

    {{ $slot }}
</form>
