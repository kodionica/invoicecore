@props(['method' => 'POST', 'action' => ''])

@php
    $htmlMethod = strtoupper($method);
    $spoofMethod = null;

    if (!in_array($htmlMethod, ['GET', 'POST'])) {
        $spoofMethod = $htmlMethod;
        $htmlMethod = 'POST';
    }

    $formAttributes = $attributes->merge([
        'method' => $htmlMethod,
        'action' => $action,
        'class' => 'form needs-validation ' . ($errors->any() ? 'was-validated' : ''),
    ]);

@endphp

<form {{ $formAttributes }}>
    @if ($htmlMethod === 'POST')
        @csrf
        @method($method)
    @endif

    {{ $slot }}
</form>
