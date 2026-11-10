@php
    $labels = array_map(fn ($w) => $w->format('j M'), $weeks);
    $series = array_map(fn ($w) => isset($vals[$w->toDateString()]) ? $vals[$w->toDateString()]->value : null, $weeks);
    $status = array_map(fn ($w) => $vals[$w->toDateString()]->status ?? null, $weeks);
    $goal = $metric->operator === 'between' ? [$metric->goal_value, $metric->goal_value_max] : $metric->goal_value;
    $filled = count(array_filter($series, fn ($v) => $v !== null));
@endphp
<x-app :title="$metric->name">
    <x-page-header :title="$metric->name" :description="$metric->description ?: 'Owned by '.($metric->ownerSeat?->title ?? 'a removed seat').', '.($metric->ownerSeat?->holder?->name ?? 'Vacant')">
        <x-slot:actions>
            <a href="{{ route('org.metrics.index', ['team' => $metric->team_seat_id]) }}" class="btn">Back to the dashboard</a>
            @if ($canEdit)<form method="POST" action="{{ route('org.metrics.archive', $metric) }}">@csrf<button class="btn">{{ $metric->archived_at ? 'Restore' : 'Archive' }}</button></form>@endif
        </x-slot:actions>
    </x-page-header>
    <p class="mb-2 text-small text-ink-secondary">On track when {{ \App\Services\Metrics::goalLabel($metric->operator, $metric->goal_value, $metric->goal_value_max, $metric->unit, (int) $metric->decimals, $currency) }}</p>
    <section class="mb-10">
        <h2 class="heading mb-3">Last four quarters</h2>
        @if ($filled)
            <x-line-chart :labels="$labels" :series="[['name' => $metric->name, 'values' => $series]]" :goal="$goal" :status="$status" :summary="$metric->name.': '.$filled.' weeks entered in the last 52 weeks.'" />
        @else
            <p class="text-ink-secondary">No values yet. Enter them on the dashboard or in My Numbers.</p>
        @endif
    </section>
    <section class="mb-10 max-w-2xl">
        <h2 class="heading mb-3">Notes</h2>
        @if ($canEnter)
            <form method="POST" action="{{ route('org.metrics.value', $metric) }}" class="mb-4 grid gap-3 sm:grid-cols-[180px_1fr_auto] sm:items-end" novalidate>
                @csrf
                <input type="hidden" name="note_only" value="1">
                <x-field name="week_start" label="Week" type="select" :options="collect($weeks)->reverse()->filter(fn ($w) => isset($vals[$w->toDateString()]))->mapWithKeys(fn ($w) => [$w->toDateString() => 'W'.$cal->weekNumber($w).', '.$w->format('j M Y')])->all()" />
                <x-field name="note" label="Note" maxlength="280" />
                <button class="btn">Save note</button>
            </form>
        @endif
        @forelse ($notes as $n)
            <div class="border-b border-line py-2"><p class="text-caption text-ink-muted">Week of {{ \App\Services\Calendar::formatDate($n->week_start) }}</p><p>{{ $n->note }}</p></div>
        @empty
            <p class="text-small text-ink-secondary">No notes yet.</p>
        @endforelse
    </section>
    @if ($canEdit)
        <section>
            <h2 class="heading mb-3">Edit metric</h2>
            @include('metrics.form', ['metric' => $metric, 'team' => $metric->team_seat_id])
        </section>
    @endif
</x-app>
