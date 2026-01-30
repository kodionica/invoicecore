@props(['label', 'name', 'wrapper_class' => '','value' => '', 'options' => []])

@php
    $defaults = [
        'id' => $name,
        'name' => $name,
        'class' => 'form-control ' . ($errors->first($name) ? 'is-invalid' : ''),
    ];

    $wrapper_class .= ' form-floating';
@endphp

<x-forms.field :$label :$name :$wrapper_class>
    <select {{ $attributes($defaults) }}>
        @foreach($options as $option)
            <option value="{{ $option['id'] }}" @selected($value === $option['id'])>{{ $option['name'] }}</option>
        @endforeach
    </select>
</x-forms.field>
