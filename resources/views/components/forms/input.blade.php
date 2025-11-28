@props(['label', 'name', 'wrapper_class' => 'form-floating'])

@php
    $defaults = [
        'type' => 'text',
        'id' => $name,
        'name' => $name,
        'class' => 'form-control',
        'value' => old($name),
        'autocomplete' => $name,
    ];
@endphp

<x-forms.field :$label :$name :$wrapper_class>
    <input {{ $attributes($defaults) }}>
</x-forms.field>
