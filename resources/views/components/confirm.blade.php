@props(['action', 'method' => 'POST', 'message', 'label', 'btn' => 'btn btn-sm btn-danger'])
<form method="POST" action="{{ $action }}" x-data="confirmForm" data-message="{{ $message }}" @submit="ask" {{ $attributes->merge(['class' => 'inline']) }}>
    @csrf
    @if ($method !== 'POST') @method($method) @endif
    {{ $slot }}
    <button type="submit" class="{{ $btn }}">{{ $label }}</button>
</form>
