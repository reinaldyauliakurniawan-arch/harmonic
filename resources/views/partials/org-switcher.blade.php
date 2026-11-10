@if ($others->isNotEmpty())
    <details class="relative mt-1">
        <summary class="flex min-h-8 cursor-pointer list-none items-center gap-1 text-small text-ink-secondary hover:text-ink">{{ $org->name }}<x-icon name="chevron-down" :size="14" /></summary>
        <div class="panel float absolute left-0 z-30 mt-1 w-56 p-1">
            <p class="px-3 py-1.5 text-caption text-ink-muted">Switch organization</p>
            @foreach ($others as $m)
                <a href="{{ route('org.home', ['organization' => $m->organization->slug]) }}" class="menu-item">{{ $m->organization->name }}</a>
            @endforeach
        </div>
    </details>
@else
    <p class="mt-1 truncate text-small text-ink-secondary">{{ $org->name }}</p>
@endif
