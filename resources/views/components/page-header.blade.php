@props(['title', 'description' => null])
<header class="mb-8 flex flex-wrap items-end justify-between gap-4">
    <div class="min-w-0">
        <h1 class="title">{{ $title }}</h1>
        @if ($description)<p class="prose-c mt-1 text-ink-secondary">{{ $description }}</p>@endif
    </div>
    @isset($actions)<div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>@endisset
</header>
