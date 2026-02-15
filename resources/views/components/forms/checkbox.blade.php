@props(['label' => '', 'wrapper_class' => '', 'checked' => false, 'name'])

@php
    $defaults = [
        'type' => 'checkbox',
        'id' => $name,
        'name' => $name,
        'value' => 1,
        'class' => 'form-check-input' . ($errors->first($name) ? ' is-invalid' : ''),
        'checked' => $checked,
    ];
@endphp

<x-forms.field :$label :$name :$wrapper_class type="checkbox">
    <input {{ $attributes($defaults) }}>
</x-forms.field>
