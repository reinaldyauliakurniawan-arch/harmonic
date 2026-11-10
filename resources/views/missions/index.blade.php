@php
    $base = array_filter(['level' => $q['level'] ?? null, 'team' => $q['team'] ?? null, 'owner' => $q['owner'] ?? null, 'view' => $view]);
    $countFor = fn ($list) => $list->count();
    $render = fn ($m, $indent = 0) => view('missions.row', ['m' => $m, 'cal' => $cal, 'indent' => $indent, 'teams' => $teams])->render();
@endphp
<x-app title="Missions">
    <x-page-header title="Missions" description="Three to seven 90-day priorities per team and person, each with a clear definition of done.">
        <x-slot:actions><a href="{{ route('org.missions.create') }}" class="btn btn-primary">New Mission</a></x-slot:actions>
    </x-page-header>
    <div class="mb-4 flex items-center gap-2">
        <a class="btn btn-sm" href="{{ route('org.missions.index', $base + ['qi' => $qi - 1]) }}" aria-label="Previous quarter"><x-icon name="chevron-left" /></a>
        <span class="heading">{{ $cal->quarterLabel($cal->quarterStart($qi)) }}</span>
        <a class="btn btn-sm" href="{{ route('org.missions.index', $base + ['qi' => $qi + 1]) }}" aria-label="Next quarter"><x-icon name="chevron-right" /></a>
    </div>
    <form method="GET" class="filters mb-6 border-b border-line pb-4" x-data="autoSubmit" @change="submit">
        <input type="hidden" name="qi" value="{{ $qi }}">
        <x-filter name="level" label="Level" :options="['' => 'All', 'company' => 'Company', 'team' => 'Team', 'personal' => 'Personal']" :value="$q['level'] ?? ''" />
        <x-filter name="team" label="Team" :options="['' => 'Any'] + $teams" :value="$q['team'] ?? ''" />
        <x-filter name="owner" label="Owner" :options="['' => 'Anyone'] + $members" :value="$q['owner'] ?? ''" />
        <x-filter name="view" label="View" :options="['list' => 'List', 'cascade' => 'Cascade']" :value="$view" />
        <button class="btn btn-sm btn-ghost" data-apply>Apply</button>
    </form>
    @if ($missions->isEmpty())
        <x-empty heading="No Missions this quarter" text="Set three to seven priorities for the quarter, each with a clear definition of done.">
            <a href="{{ route('org.missions.create') }}" class="btn btn-primary">Set a Mission</a>
        </x-empty>
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
