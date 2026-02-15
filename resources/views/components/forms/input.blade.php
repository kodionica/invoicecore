@props(['label' => '', 'wrapper_class' => '', 'value' => null, 'name'])

@php
    $defaults = [
        'type' => 'text',
        'id' => $name,
        'name' => $name,
        'class' => 'form-control' . ($errors->first($name) ? ' is-invalid' : ''),
        'autocomplete' => $name,
        'placeholder' => $label,
    ];

    $value = old($name, $value);

@endphp

<x-forms.field :$label :$name :$wrapper_class>
    <input {{ $attributes->except('value')->merge($defaults) }} value="{{ $value }}">

    {{ $slot }}
</x-forms.field>
