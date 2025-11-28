@props(['error' => false])

@if ($error)
    <div class="invalid-feedback">{{ $error }}</div>
@endif
