@props(['label', 'name', 'wrapper_class' => ''])

@php
    $defaults = [
        'id' => $name,
        'name' => $name,
        'class' => 'form-control ' . ($errors->first($name) ? 'is-invalid' : ''),
        'value' => old($name),
    ];

    $wrapper_class .= ' form-floating';
@endphp

<x-forms.field :$label :$name :$wrapper_class>
    <textarea {{ $attributes($defaults) }}>{{ $attributes['value'] }}</textarea>
</x-forms.field>
