@props(['heading', 'text' => null])
<div class="max-w-md py-12">
    <h2 class="heading">{{ $heading }}</h2>
    @if ($text)<p class="mt-1 text-ink-secondary">{{ $text }}</p>@endif
    @if (trim($slot))<div class="mt-4">{{ $slot }}</div>@endif
</div>
