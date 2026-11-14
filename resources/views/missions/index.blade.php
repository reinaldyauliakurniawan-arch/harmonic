@php
    $base = array_filter(['level' => $q['level'] ?? null, 'team' => $q['team'] ?? null, 'owner' => $q['owner'] ?? null, 'view' => $view]);
    $qStart = $cal->quarterStart($qi);
    $tally = collect(['on_track' => 'on track', 'off_track' => 'off track', 'done' => 'done', 'not_done' => 'not done'])
        ->map(fn ($l, $k) => [$missions->where('status', $k)->count(), $l])->filter(fn ($x) => $x[0]);
    $countFor = fn ($list) => $list->count();
    $render = fn ($m, $indent = 0) => view('missions.row', ['m' => $m, 'cal' => $cal, 'indent' => $indent, 'teams' => $teams])->render();
@endphp
<x-app title="Missions">
    <x-page-header title="Missions" description="Three to seven 90-day priorities per team and person, each with a clear definition of done.">
        <x-slot:actions><a href="{{ route('org.missions.create') }}" class="btn btn-primary">New Mission</a></x-slot:actions>
    </x-page-header>
    <div class="mb-4 flex flex-wrap items-center gap-2">
        <a class="btn btn-sm" href="{{ route('org.missions.index', $base + ['qi' => $qi - 1]) }}" aria-label="Previous quarter"><x-icon name="chevron-left" /></a>
        <span class="heading">{{ $cal->quarterLabel($qStart) }}</span>
        <a class="btn btn-sm" href="{{ route('org.missions.index', $base + ['qi' => $qi + 1]) }}" aria-label="Next quarter"><x-icon name="chevron-right" /></a>
        <span class="text-small text-ink-secondary">{{ $qStart->format('j M') }} to {{ $cal->quarterEnd($qi)->format('j M Y') }}@if ($qi !== $current), <a class="link" href="{{ route('org.missions.index', $base) }}">back to this quarter</a>@endif</span>
    </div>
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            @foreach (['list' => 'List', 'cascade' => 'Cascade', 'timeline' => 'Timeline'] as $k => $l)
                <a href="{{ route('org.missions.index', ['view' => $k, 'qi' => $qi] + $base) }}" class="tab" @if ($view === $k) aria-current="page" @endif>{{ $l }}</a>
            @endforeach
        </div>
        @if ($tally->isNotEmpty())
            <p class="text-small text-ink-secondary">{{ $missions->count() }} {{ \Illuminate\Support\Str::plural('Mission', $missions->count()) }}: {{ $tally->map(fn ($x) => $x[0].' '.$x[1])->join(', ') }}</p>
        @endif
    </div>
    <form method="GET" class="filters mb-6 border-b border-line pb-4" x-data="autoSubmit" @change="submit">
        <input type="hidden" name="qi" value="{{ $qi }}">
        <x-filter name="level" label="Level" :options="['' => 'All', 'company' => 'Company', 'team' => 'Team', 'personal' => 'Personal']" :value="$q['level'] ?? ''" />
        <x-filter name="team" label="Team" :options="['' => 'Any'] + $teams" :value="$q['team'] ?? ''" />
        <x-filter name="owner" label="Owner" :options="['' => 'Anyone'] + $members" :value="$q['owner'] ?? ''" />
        <input type="hidden" name="view" value="{{ $view }}">
        <button class="btn btn-sm btn-ghost" data-apply>Apply</button>
    </form>
    @if ($missions->isEmpty() && array_filter(\Illuminate\Support\Arr::only($q, ['level', 'team', 'owner'])))
        <x-empty heading="No Missions match these filters" text="Other Missions this quarter are hidden by the level, team, or owner you picked.">
            <a href="{{ route('org.missions.index', ['qi' => $qi, 'view' => $view]) }}" class="btn">Clear filters</a>
        </x-empty>
    @elseif ($missions->isEmpty())
        <x-empty heading="No Missions this quarter" text="Set three to seven priorities for the quarter, each with a clear definition of done.">
            <a href="{{ route('org.missions.create') }}" class="btn btn-primary">Set a Mission</a>
        </x-empty>
    @elseif ($view === 'timeline')
        @include('missions.timeline')
    @elseif ($view === 'cascade')
        @php
            $byParent = $missions->groupBy('parent_id');
            $walk = function ($m, $d) use (&$walk, $byParent, $render) {
                $html = $render($m, $d);
                foreach ($byParent[$m->id] ?? [] as $c) { $html .= $walk($c, $d + 1); }
                return $html;
            };
            $ids = $missions->pluck('id')->all();
        @endphp
        <div class="rows">
            @foreach ($missions->filter(fn ($m) => ! $m->parent_id || ! in_array($m->parent_id, $ids)) as $m){!! $walk($m, 0) !!}@endforeach
        </div>
    @else
        @foreach (['company' => 'Company', 'team' => 'Team', 'personal' => 'Personal'] as $lvl => $label)
            @php $list = $missions->where('level', $lvl); @endphp
            @continue($list->isEmpty())
            <section class="mb-8">
                <h2 class="heading mb-2">{{ $label }}</h2>
                @if ($list->whereIn('status', ['on_track', 'off_track'])->groupBy(fn ($m) => $lvl === 'personal' ? $m->owner_id : ($m->team_seat_id ?? 0))->contains(fn ($g) => $g->count() > 7))
                    <p class="help mb-2">Fewer is better: most teams carry 3–7.</p>
                @endif
                <div class="rows">@foreach ($list as $m){!! $render($m) !!}@endforeach</div>
            </section>
        @endforeach
    @endif
</x-app>
