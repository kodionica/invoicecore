@props(['label' => '', 'wrapper_class' => '', 'type' => 'text', 'name' ])

@php
    if($type === 'checkbox') {
        $wrapper_class .= ' form-check';
        $label_class = 'form-check-label';
    } else {
        $wrapper_class .= ' form-floating';
        $label_class = 'form-label';
    }
@endphp

<div class="{{ $wrapper_class }}">
    {{ $slot }}

    @if ($label)
        <label class="{{ $label_class }}" for="{{ $name }}">{{ $label }}</label>
    @endif

    <x-forms.error :error="$errors->first($name)"/>
</div>
