@props(['notice'])

@php
    $alert_class = match ($notice['type']) {
        'info' => 'alert-primary',
        'success' => 'alert-success',
        'warning' => 'alert-warning',
        'error' => 'alert-danger',
    };
@endphp

<div class="alert {{ $alert_class }} alert-dismissible fade show" role="alert">
    {{ $notice['message'] }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
