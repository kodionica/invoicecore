@props(['label', 'name', 'wrapper_class' => '','value' => '', 'options' => []])

@php
    $defaults = [
        'id' => $name,
        'name' => $name,
        'class' => 'form-select' . ($errors->first($name) ? ' is-invalid' : ''),
    ];
@endphp

<x-forms.field :$label :$name :$wrapper_class>
    <select {{ $attributes($defaults) }}>
        @foreach($options as $option)
            <option value="{{ $option['id'] }}" @selected($value === $option['id'])>{{ $option['name'] }}</option>
        @endforeach
    </select>
</x-forms.field>
