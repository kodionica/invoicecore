@props(['label', 'name', 'wrapper_class' => '', 'value' => '', 'options' => []])

@php
    $defaults = [
        'id' => $name,
        'name' => $name,
        'class' => 'form-select' . ($errors->first($name) ? ' is-invalid' : ''),
    ];
@endphp

<x-forms.field :$label :$name :$wrapper_class>
    <select {{ $attributes($defaults) }}>
        @foreach($options as $option_value => $option)
            <option value="{{ $option_value }}" @selected($value === $option_value)>{{ $option }}</option>
        @endforeach
    </select>
</x-forms.field>
