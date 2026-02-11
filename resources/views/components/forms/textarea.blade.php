@props(['label' => '', 'wrapper_class' => '', 'name'])

@php
    $defaults = [
        'id' => $name,
        'name' => $name,
        'class' => 'form-control' . ($errors->first($name) ? ' is-invalid' : ''),
        'value' => old($name),
        'placeholder' => $label,
    ];
@endphp

<x-forms.field :$label :$name :$wrapper_class>
    <textarea {{ $attributes($defaults) }}>{{ $attributes['value'] }}</textarea>
</x-forms.field>
