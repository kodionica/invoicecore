@props(['label', 'name', 'wrapper_class' => ''])

<div class="{{ $wrapper_class }}">
    {{ $slot }}

    @if ($label)
        <label class="" for="{{ $name }}">{{ $label }}</label>
    @endif

    <x-forms.error :error="$errors->first($name)"/>
</div>
