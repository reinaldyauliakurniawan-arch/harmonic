@forelse ($missions as $m)
    <div class="flex flex-wrap items-center gap-3 border-b border-line py-3">
        <a class="min-w-48 flex-1 font-medium hover:underline" href="{{ route('org.missions.show', $m) }}">{{ $m->title }}</a>
        <span class="text-small text-ink-secondary">{{ $m->owner->name }}</span>
        <x-status :value="$m->status" />
        @if ($m->owner_id === $me)
            @foreach (['on_track' => 'On track', 'off_track' => 'Off track'] as $s => $l)
                <form method="POST" action="{{ route('org.missions.status', $m) }}">@csrf<input type="hidden" name="status" value="{{ $s }}"><button class="btn btn-sm">{{ $l }}</button></form>
            @endforeach
        @endif
        @if ($participant)<form method="POST" action="{{ route('org.meetings.drop', $meeting) }}">@csrf<input type="hidden" name="type" value="mission"><input type="hidden" name="id" value="{{ $m->id }}"><button class="btn btn-sm">Drop to problems</button></form>@endif
    </div>
@empty
    <p class="text-ink-secondary">No active Missions for this team this quarter.</p>
@endforelse
