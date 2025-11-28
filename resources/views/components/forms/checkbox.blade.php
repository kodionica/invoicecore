@props(['label', 'name', 'wrapper_class'])

@php
    $defaults = [
        'type' => 'checkbox',
        'id' => $name,
        'name' => $name,
        'value' => old($name),
        'class' => 'form-check-input',
    ];

    $wrapper_class .= ' form-check';
@endphp

<x-forms.field :$label :$name :$wrapper_class>
    <input {{ $attributes($defaults) }}>
</x-forms.field>
