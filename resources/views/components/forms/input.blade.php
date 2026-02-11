@props(['label' => '', 'wrapper_class' => '', 'name'])

@php
    $defaults = [
        'type' => 'text',
        'id' => $name,
        'name' => $name,
        'class' => 'form-control' . ($errors->first($name) ? ' is-invalid' : ''),
        'value' => old($name),
        'autocomplete' => $name,
        'placeholder' => $label,
    ];
@endphp

<x-forms.field :$label :$name :$wrapper_class>
    <input {{ $attributes($defaults) }}>
</x-forms.field>
