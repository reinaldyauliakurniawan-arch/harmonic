@php extract($blocks); @endphp
@if (($part ?? 'main') === 'main')
@if ($banners)
    <section class="mb-8 space-y-2" aria-label="Needs your answer">
        @foreach ($banners as [$text, $label, $url])
            <div class="banner banner-brass flex flex-wrap items-center justify-between gap-3"><span>{{ $text }}</span><a class="btn btn-sm" href="{{ $url }}">{{ $label }}</a></div>
        @endforeach
    </section>
@endif

@if ($numbers->isNotEmpty() || $tasks->isNotEmpty() || $stale->isNotEmpty())
    <section class="card mb-6">
        <h2 class="heading">This week</h2>
        @if ($numbers->isNotEmpty())
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3 border-b border-line pb-3">
                <p>{{ $numbers->count() }} of your numbers {{ $numbers->count() === 1 ? 'isn\'t' : 'aren\'t' }} entered yet: {{ $numbers->pluck('name')->join(', ') }}.</p>
                <a href="{{ route('org.numbers.index') }}" class="btn btn-primary">Enter this week</a>
            </div>
        @endif
        @if ($tasks->isNotEmpty())
            <h3 class="subheading">Tasks overdue or due this week</h3>
            <div class="rows">@foreach ($tasks->load(['responsible.user', 'steps']) as $t)<x-task-row :task="$t" :today="$today" />@endforeach</div>
        @endif
        @if ($stale->isNotEmpty())
            <h3 class="subheading">Missions not updated in 7 days</h3>
            <div class="rows">@foreach ($stale as $m)<a class="flex min-h-11 items-center justify-between hover:bg-hover" href="{{ route('org.missions.show', $m) }}"><span>{{ $m->title }}</span><x-status :value="$m->status" /></a>@endforeach</div>
        @endif
    </section>
@endif

@if ($leader)
    <section class="card mb-6">
        <h2 class="heading">Across your teams</h2>
        @if (isset($leader['metrics']))
            <h3 class="subheading">Last week's metrics off track or not entered</h3>
            <div class="rows">@foreach ($leader['metrics'] as $x)<a class="flex min-h-11 items-center justify-between hover:bg-hover" href="{{ route('org.metrics.show', $x['m']) }}"><span>{{ $x['m']->name }}</span><x-status :value="$x['state']" /></a>@endforeach</div>
        @endif
        @if (isset($leader['evaluate']))
            <h3 class="subheading">Problems to evaluate</h3>
            <div class="rows">@foreach ($leader['evaluate'] as $p)<a class="flex min-h-11 items-center hover:bg-hover" href="{{ route('org.problems.show', $p) }}">{{ $p->title }}</a>@endforeach</div>
        @endif
        @if (isset($leader['closing']))
            <h3 class="subheading">Missions needing closing</h3>
            <div class="rows">@foreach ($leader['closing'] as $m)<a class="flex min-h-11 items-center hover:bg-hover" href="{{ route('org.missions.show', $m) }}">{{ $m->title }}</a>@endforeach</div>
        @endif
        @if (isset($leader['reviews']))
            <h3 class="subheading">Procedure reviews due</h3>
            <div class="rows">@foreach ($leader['reviews'] as $p)<a class="flex min-h-11 items-center hover:bg-hover" href="{{ route('org.procedures.show', $p) }}">{{ $p->title }}</a>@endforeach</div>
        @endif
        @if (isset($leader['vacant']))
            <h3 class="subheading">Vacant seats that own work</h3>
            <ul class="rows mt-2">
                @foreach ($leader['vacant'] as $v)
                    <li class="py-2">{{ collect(['metrics' => $v['metrics'], 'procedures' => $v['procedures']])->filter()->map(fn ($c, $k) => $c.' '.($c === 1 ? rtrim($k, 's') : $k))->join(' and ') }} {{ ($v['metrics'] + $v['procedures']) === 1 ? 'has' : 'have' }} no owner because {{ $v['seat']->title }} is vacant.</li>
                @endforeach
            </ul>
        @endif
        @if (isset($leader['noNumbers']))
            <h3 class="subheading">{{ $leader['noNumbers']->count() }} {{ $leader['noNumbers']->count() === 1 ? 'person' : 'people' }} in your teams {{ $leader['noNumbers']->count() === 1 ? 'has' : 'have' }} no numbers yet</h3>
            <p class="mt-1 text-ink-secondary">{{ $leader['noNumbers']->pluck('name')->join(', ') }}</p>
        @endif
    </section>
@endif
@else
@if ($meetings)
    <section class="card mb-6">
        <h2 class="heading">Meetings</h2>
        <div class="rows">
            @foreach ($meetings as $mt)
                <div class="flex flex-wrap items-center justify-between gap-3 py-2">
                    @php
                        $at = $cal->local($mt['event']->starts_at);
                        $in = (int) $today->diffInDays($at->startOfDay(), false);
                        $when = $mt['live'] ? 'Live now' : ($in <= 0 ? 'Today' : ($in === 1 ? 'Tomorrow' : 'In '.$in.' days'));
                    @endphp
                    <span class="flex min-w-0 flex-col"><span>{{ $mt['team'] }}</span><span class="text-small text-ink-secondary"><span class="{{ $in <= 0 ? 'font-medium text-ink' : '' }}">{{ $when }}</span>, {{ $at->format('D, j M') }} at {{ $at->format('H:i') }}</span></span>
                    <a class="btn btn-sm {{ $mt['live'] ? 'btn-primary' : '' }}" href="{{ route('org.meetings.run', $mt['event']) }}">{{ $mt['live'] ? 'Join' : 'Open' }}</a>
                </div>
            @endforeach
        </div>
    </section>
@endif

@if ($todayEvents->isNotEmpty())
    <section class="card mb-6">
        <h2 class="heading">Today</h2>
        <div class="rows">
            @foreach ($todayEvents as $e)
                <a class="flex min-h-11 items-center gap-3 hover:bg-hover" href="{{ route('org.calendar.index', ['event' => $e->id, 'date' => $today->toDateString()]) }}"><span class="w-14 text-small text-ink-secondary">{{ $e->all_day ? 'All day' : $cal->local($e->starts_at)->format('H:i') }}</span>{{ $e->title }}</a>
            @endforeach
        </div>
    </section>
@endif
@endif
