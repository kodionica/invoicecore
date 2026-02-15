@props(['label' => '', 'wrapper_class' => '', 'value' => null, 'name'])

@php
    $defaults = [
        'id' => $name,
        'name' => $name,
        'class' => 'form-control' . ($errors->first($name) ? ' is-invalid' : ''),
        'placeholder' => $label,
    ];

    $value = old($name, $value);
@endphp

<x-forms.field :$label :$name :$wrapper_class>
    <textarea {{ $attributes->except('value')->merge($defaults) }}>{{ $attributes['value'] }}</textarea>
</x-forms.field>
