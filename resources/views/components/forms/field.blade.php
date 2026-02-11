@props(['label' => '', 'wrapper_class' => '', 'name' ])

@php
    $wrapper_class .= ' form-floating';
@endphp

<div class="{{ $wrapper_class }}">
    {{ $slot }}

    @if ($label)
        <label for="{{ $name }}">{{ $label }}</label>
    @endif

    <x-forms.error :error="$errors->first($name)"/>
</div>
