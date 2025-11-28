@props(['label', 'name', 'wrapper_class' => ''])

@php
    $defaults = [
        'type' => 'text',
        'id' => $name,
        'name' => $name,
        'class' => 'form-control ' . ($errors->first($name) ? 'is-invalid' : ''),
        'value' => old($name),
        'autocomplete' => $name,
    ];

    $wrapper_class .= ' form-floating';
@endphp

<x-forms.field :$label :$name :$wrapper_class>
    <input {{ $attributes($defaults) }}>
</x-forms.field>
