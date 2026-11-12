@php
    $typeNames = \App\Http\Controllers\Org\CalendarController::TYPES;
    $byDay = $events->groupBy(fn ($e) => $cal->local($e->starts_at)->toDateString());
    $base = ['view' => $view, 'filter' => $filter, 'team' => $q['team'] ?? null, 'due' => $q['due'] ?? null];
    $prev = match ($view) { 'week' => $date->subWeek(), 'agenda' => $date->subDays(28), default => $date->startOfMonth()->subMonth() };
    $next = match ($view) { 'week' => $date->addWeek(), 'agenda' => $date->addDays(28), default => $date->startOfMonth()->addMonth() };
    $today = $cal->today()->toDateString();
@endphp
<x-app title="Calendar" wide>
    <x-page-header title="Calendar" description="Meetings, sessions, guided events, and your due dates in one place.">
        <x-slot:actions><a href="{{ route('org.calendar.index', $base + ['date' => $date->toDateString(), 'new' => 1]) }}" class="btn btn-primary">New event</a></x-slot:actions>
    </x-page-header>
    <x-errors />

    <div class="mb-4 flex flex-wrap items-center justify-between gap-x-6 gap-y-3 border-b border-line pb-3">
        <div class="flex items-center gap-1">
            <a href="{{ route('org.calendar.index', $base + ['date' => $prev->toDateString()]) }}" class="icon-btn" aria-label="Previous"><x-icon name="chevron-left" /></a>
            <a href="{{ route('org.calendar.index', $base + ['date' => $next->toDateString()]) }}" class="icon-btn" aria-label="Next"><x-icon name="chevron-right" /></a>
            <h2 class="heading ml-2 mr-3">{{ $view === 'month' ? $date->format('F Y') : \App\Services\Calendar::formatDate($from).' – '.\App\Services\Calendar::formatDate($to->subDay()) }}</h2>
            <a href="{{ route('org.calendar.index', $base + ['date' => $today]) }}" class="btn btn-sm">Today</a>
        </div>
        <form method="GET" class="filters" x-data="autoSubmit" @change="submit">
            <input type="hidden" name="date" value="{{ $date->toDateString() }}">
            <x-filter name="view" label="View" :options="['month' => 'Month', 'week' => 'Week', 'agenda' => 'Agenda']" :value="$view" />
            <x-filter name="filter" label="Show" :options="['all' => 'Everything', 'mine' => 'Mine', 'team' => 'A team', 'org' => 'Organization']" :value="$filter" />
            @if ($filter === 'team')<x-filter name="team" label="Team" :options="['' => 'Any'] + $teams" :value="$q['team'] ?? ''" />@endif
            <label class="min-h-8"><input type="checkbox" name="due" value="1" class="h-4 w-4 accent-brass" @checked($q['due'] ?? false)> Due dates</label>
            <button class="btn btn-sm btn-ghost" data-apply>Apply</button>
        </form>
    </div>

    @php
        $days = [];
        for ($d = $from; $d->lt($to); $d = $d->addDay()) { $days[] = $d; }
        $eventLink = fn ($e) => route('org.calendar.index', $base + ['date' => $date->toDateString(), 'event' => $e->id]);
    @endphp

    @if ($view !== 'agenda')
        <div class="{{ $view === 'month' ? 'hidden md:grid' : 'grid' }} grid-cols-7 border-l border-t border-line">
            @foreach (array_slice($days, 0, 7) as $d)<div class="border-b border-r border-line px-2 py-1 label text-ink-secondary">{{ $d->format('D') }}</div>@endforeach
            @foreach ($days as $d)
                @php $key = $d->toDateString(); @endphp
                <div class="min-h-28 border-b border-r border-line p-1.5 {{ $view === 'month' && $d->month !== $date->month ? 'bg-hover/50 text-ink-muted' : '' }} {{ $key === $today ? 'shadow-[inset_0_2px_0_var(--color-brass)]' : '' }}" @if ($key === $today) aria-current="date" @endif>
                    <p class="text-caption {{ $key === $today ? 'font-semibold text-brass' : '' }}">{{ $d->day }}</p>
                    @foreach ($byDay[$key] ?? [] as $e)
                        <a href="{{ $eventLink($e) }}" class="mt-1 block truncate rounded-[6px] bg-surface px-1.5 py-0.5 text-caption shadow-[var(--shadow-group)] hover:bg-hover">@unless ($e->all_day){{ $cal->local($e->starts_at)->format('H:i') }} @endunless{{ $e->title }}</a>
                    @endforeach
                    @foreach ($markers[$key] ?? [] as $m)
                        <a href="{{ $m['url'] }}" class="mt-1 block truncate text-caption text-ink-secondary hover:underline">{{ $m['label'] }}</a>
                    @endforeach
                </div>
            @endforeach
        </div>
    @endif

    @if ($view === 'agenda' || $view === 'month')
        <div class="{{ $view === 'month' ? 'md:hidden' : '' }}">
            @php $any = false; @endphp
            @foreach ($days as $d)
                @php $key = $d->toDateString(); $list = $byDay[$key] ?? collect(); $mk = $markers[$key] ?? collect(); @endphp
                @continue($list->isEmpty() && $mk->isEmpty())
                @php $any = true; @endphp
                <h3 class="subheading mt-4 {{ $key === $today ? 'text-brass' : '' }}">{{ \App\Services\Calendar::formatDate($d) }}</h3>
                <div class="rows mt-2">
                    @foreach ($list as $e)
                        <a href="{{ $eventLink($e) }}" class="flex min-h-11 items-center gap-3 hover:bg-hover">
                            <span class="w-24 shrink-0 text-small text-ink-secondary">{{ $e->all_day ? 'All day' : $cal->local($e->starts_at)->format('H:i').'–'.$cal->local($e->ends_at)->format('H:i') }}</span>
                            <span>{{ $e->title }}</span>
                        </a>
                    @endforeach
                    @foreach ($mk as $m)<a href="{{ $m['url'] }}" class="flex min-h-11 items-center gap-3 text-small text-ink-secondary hover:bg-hover"><span class="w-24 shrink-0">Due</span>{{ $m['label'] }}</a>@endforeach
                </div>
            @endforeach
            @unless ($any)<x-empty heading="Nothing scheduled" text="Events, meetings, and guided events you can see appear here." />@endunless
        </div>
    @endif

    @if ($q['new'] ?? false)
        @include('calendar.new')
    @endif
    @if ($panel)
        @include('calendar.panel')
    @endif
</x-app>
