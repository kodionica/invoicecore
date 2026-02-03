@props(['heading' => '', 'link_title' => '', 'link_url' => '', 'wrapper_class' => ''])

@php
    $wrapper_class .= ' wrapper_class';
@endphp

<div class="{{ $wrapper_class }}">
    <h1>{{ $heading }}</h1>
    <a href="{{ $link_url }}">{{ $link_title }}</a>
</div>
