<x-app title="The Problem Queue">
    <x-page-header title="The Problem Queue" description="Short-term problems are worked every week; long-term ones at quarterly sessions. Anyone can raise one for any team.">
        <x-slot:actions><a href="{{ route('org.problems.create', ['team' => $team]) }}" class="btn btn-primary">Raise a problem</a></x-slot:actions>
    </x-page-header>
    @if (! $teams)
        <x-empty heading="No teams yet" text="Problems belong to a team. Add seats in Winning Formation first." />
    @else
        <form method="GET" class="filters mb-4" x-data="autoSubmit" @change="submit">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <x-filter name="team" label="Team" :options="$teams" :value="$team" />
            <button class="btn btn-sm btn-ghost" data-apply>Apply</button>
        </form>
        <div class="mb-4 border-b border-line">
            @foreach (['short' => 'Short-term', 'long' => 'Long-term', 'solved' => 'Solved', 'dropped' => 'Dropped'] as $k => $l)
                <a class="tab" href="{{ route('org.problems.index', ['team' => $team, 'tab' => $k]) }}" @if ($tab === $k) aria-current="page" @endif>{{ $l }}</a>
            @endforeach
        </div>
        @if ($problems->isEmpty())
            @if (in_array($tab, ['short', 'long'], true))
                <x-empty :heading="$tab === 'short' ? 'No short-term problems' : 'No long-term problems'" :text="$tab === 'short' ? 'Raise anything that gets in the way this week. The team ranks the top three and solves them in the Weekly Meeting.' : 'Bigger issues that need a quarterly session go here.'">
                    <a href="{{ route('org.problems.create', ['team' => $team, 'horizon' => $tab]) }}" class="btn btn-primary">Raise a problem</a>
                </x-empty>
            @else
                <x-empty :heading="$tab === 'solved' ? 'Nothing solved yet' : 'Nothing dropped'" :text="$tab === 'solved' ? 'Problems this team solves with I-S.O.L.V.E land here, with their evaluation.' : 'Problems dropped as not worth solving, or as duplicates, land here with the reason.'" />
            @endif
        @else
            <div class="rows">
                @foreach ($problems as $p)
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-2 py-3">
                        @if (in_array($tab, ['short', 'long']))
                            <span class="w-6 text-center font-semibold {{ $p->rank ? 'text-brass' : 'text-ink-muted' }}" aria-label="{{ $p->rank ? 'Rank '.$p->rank : 'Not ranked' }}">{{ $p->rank ?? '–' }}</span>
                        @endif
                        <a href="{{ route('org.problems.show', $p) }}" class="min-w-48 flex-1 font-medium hover:underline">{{ $p->title }}</a>
                        <span class="text-small text-ink-secondary">{{ $p->owner?->name ?? 'No owner' }}</span>
                        @if ($p->source_type)<span class="badge">{{ \App\Services\Problems::SOURCES[$p->source_type] ?? $p->source_type }}</span>@endif
                        @if ($p->status === 'solving')<span class="badge">Solving</span>@endif
                        <span class="text-caption text-ink-muted">opened {{ $p->created_at->diffForHumans() }}</span>
                        @if ($canMaintain && in_array($tab, ['short', 'long']))
                            <form method="POST" action="{{ route('org.problems.maintain', $p) }}" class="flex items-center gap-1">
                                @csrf <input type="hidden" name="action" value="rank">
                                <label class="sr-only" for="rk-{{ $p->id }}">Rank for {{ $p->title }}</label>
                                <select id="rk-{{ $p->id }}" name="rank" class="input min-h-8 w-24 py-1 text-small">
                                    <option value="">No rank</option>@foreach ([1, 2, 3] as $r)<option value="{{ $r }}" @selected($p->rank === $r)>Rank {{ $r }}</option>@endforeach
                                </select>
                                <button class="btn btn-sm">Set</button>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>
            {{ $problems->links() }}
        @endif
    @endif
</x-app>
