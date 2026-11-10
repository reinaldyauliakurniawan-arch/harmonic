<x-app title="Meetings">
    <x-page-header title="Meetings" description="Each team's Weekly Meeting: what's coming and how past meetings went." />
    @if (! $teams)
        <x-empty heading="No teams yet" text="Meetings belong to teams. Add seats in Winning Formation first." />
    @else
        <form method="GET" class="filters mb-6" x-data="autoSubmit" @change="submit"><x-filter name="team" label="Team" :options="$teams" :value="$team" /><button class="btn btn-sm btn-ghost" data-apply>Apply</button></form>
        <section class="mb-10">
            <h2 class="heading mb-2">Coming up</h2>
            @forelse ($upcoming as $e)
                <a href="{{ route('org.meetings.run', $e) }}" class="flex min-h-11 items-center justify-between border-b border-line hover:bg-hover"><span>{{ $e->title }}</span><span class="text-small text-ink-secondary">{{ \App\Services\Calendar::formatDate($cal->local($e->starts_at)) }} {{ $cal->local($e->starts_at)->format('H:i') }}</span></a>
            @empty
                <p class="text-ink-secondary">No Weekly Meeting scheduled. The team leader sets the cadence on the <a class="link" href="{{ route('org.teams.show', $team) }}">team page</a>.</p>
            @endforelse
        </section>
        @if ($last13->count() > 1)
            <section class="mb-10">
                <h2 class="heading mb-2">Average rating, last {{ $last13->count() }} meetings</h2>
                <x-line-chart :labels="$last13->map(fn ($m) => $m->scheduled_for->format('j M'))->all()" :series="[['name' => 'Rating', 'values' => $last13->map(fn ($m) => isset($avg13[$m->id]) ? round((float) $avg13[$m->id], 1) : null)->all()]]" :floor="1" :ceiling="10" summary="Average meeting rating over recent meetings." />
            </section>
        @endif
        <section>
            <h2 class="heading mb-2">History</h2>
            @if ($meetings->isEmpty() && $skipped->isEmpty())
                <x-empty heading="No meetings yet" text="Once a Weekly Meeting runs, its recap shows here." />
            @else
                <div class="relative overflow-x-auto"><table class="tbl">
                    <thead><tr><th>Date</th><th class="num">Minutes</th><th class="num">Rating</th><th class="num">Tasks created</th><th class="num">Problems solved</th></tr></thead>
                    <tbody>
                    @foreach ($meetings as $m)
                        <tr>
                            <td><a class="link" href="{{ route('org.meetings.run', $m->event_id) }}">{{ \App\Services\Calendar::formatDate($cal->local($m->scheduled_for)) }}</a>@if ($m->isLive()) <span class="badge">Live</span>@endif</td>
                            <td class="num">{{ $m->ended_at ? (int) $m->started_at->diffInMinutes($m->ended_at) : '–' }}</td>
                            <td class="num">{{ isset($ratings[$m->id]) ? number_format($ratings[$m->id], 1) : '–' }}</td>
                            <td class="num">{{ $meetingsSvc->tasksCreated($m)->count() }}</td>
                            <td class="num">{{ $solved[$m->id] ?? 0 }}</td>
                        </tr>
                    @endforeach
                    @foreach ($skipped as $e)
                        <tr><td>{{ \App\Services\Calendar::formatDate($cal->local($e->starts_at)) }}</td><td colspan="4" class="text-ink-secondary">Skipped</td></tr>
                    @endforeach
                    </tbody>
                </table></div>
                {{ $meetings->links() }}
            @endif
        </section>
    @endif
</x-app>
