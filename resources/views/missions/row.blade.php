@php
    $done = $m->milestones->whereNotNull('done_at')->count();
    $stale = $m->isOpen() && (! $m->status_updated_at || $m->status_updated_at->lt(now()->subDays(7)));
    $needsClosing = $m->isOpen() && $cal->quarterEndFor($m->quarter_start)->lt($cal->today());
@endphp
<div class="flex flex-wrap items-center gap-x-4 gap-y-1 py-3 {{ ['pl-0', 'pl-6', 'pl-12', 'pl-16'][min($indent, 3)] }}">
    <a href="{{ route('org.missions.show', $m) }}" class="min-w-48 flex-1 font-medium hover:underline">{{ $m->title }}</a>
    <span class="text-small text-ink-secondary">{{ $m->owner->name }}@if ($m->team_seat_id), {{ $teams[$m->team_seat_id] ?? '' }}@endif</span>
    @if ($m->milestones->count())<span class="text-small text-ink-secondary">{{ $done }} of {{ $m->milestones->count() }}</span>@endif
    <x-status :value="$m->status" />
    @if ($needsClosing)<span class="badge">Needs closing</span>@elseif ($stale)<span class="text-caption text-ink-muted">Not updated this week</span>@endif
</div>
