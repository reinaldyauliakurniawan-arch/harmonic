<x-app :title="$team->teamLabel()">
    <x-page-header :title="$team->teamLabel()" :description="'Led by '.($team->holder?->name ?? 'a vacant seat').'.'" />
    <x-errors />
    <div class="grid gap-10 lg:grid-cols-2">
        <section>
            <h2 class="heading mb-2">Members</h2>
            <div class="rows">@foreach ($members as $m)<div class="flex min-h-11 items-center gap-2"><x-avatar :member="$m" />{{ $m->name }}</div>@endforeach</div>
            <div class="mt-4 flex flex-wrap gap-2">
                <a class="btn btn-sm" href="{{ route('org.metrics.index', ['team' => $team->id]) }}">Metrics</a>
                <a class="btn btn-sm" href="{{ route('org.problems.index', ['team' => $team->id]) }}">Problems</a>
                <a class="btn btn-sm" href="{{ route('org.missions.index', ['team' => $team->id]) }}">Missions</a>
                <a class="btn btn-sm" href="{{ route('org.tasks.index', ['tab' => 'team', 'team' => $team->id]) }}">Tasks</a>
                <a class="btn btn-sm" href="{{ route('org.meetings.index', ['team' => $team->id]) }}">Meeting history</a>
            </div>
            <p class="mt-6 text-small">Task completion last week: <span class="font-medium {{ $rate !== null && $rate < 90 ? 'cell-off' : '' }}">{{ $rate !== null ? $rate.'%' : 'no tasks due' }}</span> <span class="text-ink-secondary">(aim for 90%)</span></p>
        </section>
        <section>
            <h2 class="heading mb-2">Weekly Meeting</h2>
            @if ($team->meeting_weekday)
                <p>{{ ['', 'Mondays', 'Tuesdays', 'Wednesdays', 'Thursdays', 'Fridays', 'Saturdays', 'Sundays'][$team->meeting_weekday] }} at {{ substr($team->meeting_time, 0, 5) }}, {{ $team->meeting_minutes }} minutes.</p>
                @if ($next)<a class="btn btn-primary mt-3" href="{{ route('org.meetings.run', $next) }}">Open next meeting, {{ \App\Services\Calendar::formatDate($cal->local($next->starts_at)) }}</a>@endif
            @else
                <p class="text-ink-secondary">No cadence set yet.</p>
            @endif
            @if ($canLead)
                <form method="POST" action="{{ route('org.teams.cadence', $team) }}" class="mt-6 grid gap-3 sm:grid-cols-3 sm:items-end" novalidate>
                    @csrf @method('PUT')
                    <x-field name="meeting_weekday" label="Day" type="select" :options="[1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday']" :value="$team->meeting_weekday ?? 1" />
                    <x-field name="meeting_time" label="Starts" type="time" :value="$team->meeting_time ? substr($team->meeting_time, 0, 5) : '09:00'" />
                    <x-field name="meeting_minutes" label="Minutes" type="number" min="60" max="120" :value="$team->meeting_minutes ?? 90" />
                    <div class="sm:col-span-3"><button class="btn">Save cadence</button></div>
                </form>
            @endif
        </section>
    </div>
</x-app>
