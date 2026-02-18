@props(['label' => '', 'label_hidden' => false, 'wrapper_class' => '', 'type' => 'text', 'name' ])

@php
    if($type === 'checkbox') {
        $wrapper_class .= ' form-check';
        $label_class = 'form-check-label';
    } else {
        $wrapper_class .= ' form-group';
        $label_class = 'form-label';
    }

    if($label_hidden) {
        $label_class .= ' sr-only';
    }
@endphp

<div class="{{ $wrapper_class }}">
    {{ $slot }}

    @if ($label)
        <label class="{{ $label_class }}" for="{{ $name }}">{{ $label }}</label>
    @endif

    <x-forms.error :error="$errors->first($name)"/>
</div>
