@props(['heading' => '', 'link_title' => '', 'link_url' => '', 'wrapper_class' => ''])

@php
    $wrapper_class .= ' page-header';
@endphp

<div class="{{ $wrapper_class }}">
    <h1>{{ $heading }}</h1>

    @if($link_url)
        <a href="{{ $link_url }}">{{ $link_title }}</a>
    @endif
</div>
