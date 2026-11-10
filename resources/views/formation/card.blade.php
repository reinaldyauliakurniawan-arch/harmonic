@php $resp = $seat->responsibilities ?? []; @endphp
<div class="seat-card panel p-3" data-search="{{ strtolower($seat->title.' '.($seat->holder?->name ?? '')) }}">
    <div class="flex items-start justify-between gap-2">
        <p class="subheading">{{ $seat->title }}</p>
        @if (in_array($seat->id, $leadership, true))<span class="badge">Leadership</span>@endif
    </div>
    <p class="mt-1 flex items-center gap-2 text-small">
        @if ($seat->holder)<x-avatar :member="$seat->holder" />{{ $seat->holder->name }}@else<span class="pill-status is-off">Vacant</span>@endif
    </p>
    @if ($resp)
        <ul class="mt-2 list-disc pl-4 text-small text-ink-secondary">
            @foreach (array_slice($resp, 0, 3) as $r)<li>{{ $r }}</li>@endforeach
        </ul>
        @if (count($resp) > 3)<p class="text-caption text-ink-muted">+{{ count($resp) - 3 }} more</p>@endif
    @endif
    @if ($f->isTeam($seat->id) || $canManage)
        <div class="mt-2 flex items-center justify-between gap-2 border-t border-line pt-2 text-caption">
            @if ($f->isTeam($seat->id))<a class="text-ink-secondary hover:underline" href="{{ route('org.teams.show', $seat) }}">{{ $seat->teamLabel() }}</a>@else<span></span>@endif
            @if ($canManage)<a class="link" href="{{ route('org.formation.edit', $seat) }}" aria-label="Manage {{ $seat->title }}">Manage</a>@endif
        </div>
    @endif
</div>
