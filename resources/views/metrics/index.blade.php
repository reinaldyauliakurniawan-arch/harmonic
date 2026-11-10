@php
    $now = $cal->currentWeekStart()->toDateString();
@endphp
<x-app title="Metrics Dashboard" wide>
    <x-page-header title="Metrics Dashboard" description="Each team's weekly numbers against their goals. Click a cell to enter a value.">
        <x-slot:actions>
            @if ($canCreate && $team)<a href="{{ route('org.metrics.create', ['team' => $team]) }}" class="btn btn-primary">Add a metric</a>@endif
        </x-slot:actions>
    </x-page-header>

    @if (! $teams)
        <x-empty heading="No teams yet" text="A team is a seat with seats under it. Add seats in Winning Formation first.">
            <a href="{{ route('org.formation.index') }}" class="btn">Open Winning Formation</a>
        </x-empty>
    @else
        <form method="GET" class="filters mb-4" x-data="autoSubmit" @change="submit">
            <label>Team <select name="team" class="input input-sm">@foreach ($teams as $id => $l)<option value="{{ $id }}" @selected($team === $id)>{{ $l }}</option>@endforeach</select></label>
            <input type="hidden" name="qi" value="{{ $qi }}">
            <label class="min-h-8"><input type="checkbox" name="trailing" value="1" class="h-4 w-4 accent-brass" @checked($trailing)> Last 13 weeks</label>
            <label class="min-h-8"><input type="checkbox" name="archived" value="1" class="h-4 w-4 accent-brass" @checked($archived)> Archived</label>
            <button class="btn btn-sm btn-ghost" data-apply>Apply</button>
        </form>
        @unless ($trailing)
            <div class="mb-4 flex items-center gap-2">
                <a class="btn btn-sm" href="{{ route('org.metrics.index', ['team' => $team, 'qi' => $qi - 1]) }}" aria-label="Previous quarter"><x-icon name="chevron-left" /></a>
                <span class="heading">{{ $cal->quarterLabel($weeks[0]) }}</span>
                <a class="btn btn-sm" href="{{ route('org.metrics.index', ['team' => $team, 'qi' => $qi + 1]) }}" aria-label="Next quarter"><x-icon name="chevron-right" /></a>
                @if ($qi !== $currentQi)<a class="btn btn-sm btn-ghost" href="{{ route('org.metrics.index', ['team' => $team]) }}">This quarter</a>@endif
            </div>
        @endunless
        @if ($count > 15)<p class="help mb-3">Most teams track 5–15 numbers.</p>@endif

        @if ($groups->isEmpty())
            <x-empty :heading="$archived ? 'No archived metrics' : 'No metrics for '.$teams[$team].' yet'" text="A metric is a weekly number with a goal, owned by one seat.">
                @if ($canCreate && ! $archived)<a href="{{ route('org.metrics.create', ['team' => $team]) }}" class="btn btn-primary">Add a metric</a>@endif
            </x-empty>
        @else
            <p class="help min-h-5" role="status" id="grid-status" data-sort-status></p>
            <div class="relative overflow-x-auto" x-data="metricGrid">
                <table class="tbl score min-w-[960px]" @click="edit" @keydown.enter="edit">
                    <thead>
                        <tr>
                            <th class="first min-w-56">Metric</th>
                            <th>Goal</th>
                            @foreach ($weeks as $i => $w)
                                @php $qstart = $trailing && $i > 0 && $cal->quarterStartFor($w)->equalTo($w); @endphp
                                <th class="num {{ $w->toDateString() === $now ? 'now' : '' }} {{ $qstart ? 'qend' : '' }}" title="Week of {{ \App\Services\Calendar::formatDate($w) }}">W{{ $cal->weekNumber($w) }}</th>
                            @endforeach
                            <th class="num qend">Quarter</th>
                        </tr>
                    </thead>
                    @foreach ($groups as $seatId => $list)
                        @php $seat = $f->seat($seatId); $canSort = ! $archived && auth()->user()->can('update', $list->first()) && $list->count() > 1; @endphp
                        <tbody @if ($canSort) x-data="sortable" data-url="{{ route('org.metrics.order') }}" @endif>
                            <tr class="group-row"><th colspan="{{ count($weeks) + 3 }}" class="first"><span class="label text-ink">{{ $seat?->title ?? 'Removed seat' }}</span> <span class="text-small font-normal text-ink-secondary">{{ $seat?->holder?->name ?? 'Vacant' }}</span></th></tr>
                            @foreach ($list as $m)
                                @php $mv = $values[$m->id] ?? collect(); @endphp
                                <tr data-url="{{ route('org.metrics.value', $m) }}" data-sort-id="{{ $m->id }}" @if ($canSort) @dragover.prevent="over" @drop.prevent="drop" @endif>
                                    <td class="first">
                                        <div class="flex items-center gap-1">
                                            @if ($canSort)<span class="-ml-1 cursor-grab text-ink-muted" draggable="true" @dragstart="start" @dragend="end" aria-hidden="true"><x-icon name="grip-vertical" :size="14" /></span>@endif
                                            <a href="{{ route('org.metrics.show', $m) }}" class="min-w-0 flex-1 truncate font-medium hover:underline">{{ $m->name }}</a>
                                            @if ($canSort)
                                                <span class="row-tools"><button type="button" class="icon-btn" @click="up" aria-label="Move {{ $m->name }} up"><x-icon name="chevron-up" :size="14" /></button><button type="button" class="icon-btn" @click="down" aria-label="Move {{ $m->name }} down"><x-icon name="chevron-down" :size="14" /></button></span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap text-ink-secondary">{{ \App\Services\Metrics::goalLabel($m->operator, $m->goal_value, $m->goal_value_max, $m->unit, (int) $m->decimals, $currency) }}</td>
                                    @foreach ($weeks as $i => $w)
                                        @php
                                            $key = $w->toDateString(); $v = $mv[$key] ?? null; $st = $metrics->cellStatus($v, $w, $m);
                                            $editable = ! $archived && $metrics->canEnter($m, $w);
                                            $qstart = $trailing && $i > 0 && $cal->quarterStartFor($w)->equalTo($w);
                                        @endphp
                                        <td class="num whitespace-nowrap {{ $key === $now ? 'now font-medium' : '' }} {{ $st === 'missing' ? 'cell-missing' : '' }} {{ $qstart ? 'qend' : '' }} {{ $editable ? 'cursor-text' : '' }}"
                                            data-week="{{ $key }}" data-editable="{{ $editable ? 1 : 0 }}" data-raw="{{ $v ? rtrim(rtrim(number_format($v->value, $m->decimals, '.', ''), '0'), '.') : '' }}"
                                            data-label="{{ $m->name }}, week {{ $cal->weekNumber($w) }}" @if ($editable) tabindex="0" @endif>@include('metrics.cell', ['v' => $v, 'metric' => $m, 'status' => $st, 'editable' => $editable])</td>
                                    @endforeach
                                    @php $roll = $metrics->rollup($m, collect($rollupWeeks)->map(fn ($w) => $mv[$w->toDateString()] ?? null)); @endphp
                                    <td class="num qend whitespace-nowrap font-medium">{{ \App\Services\Metrics::format($roll, $m->unit, (int) $m->decimals, $currency) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    @endforeach
                </table>
                <dialog class="dialog" data-note-dialog aria-labelledby="note-title">
                    <form method="dialog" class="space-y-3" @submit.prevent="saveNote">
                        <h2 id="note-title" class="heading">Note for this week</h2>
                        <label for="note-text" class="field-label">Note</label>
                        <textarea id="note-text" class="input" rows="3" maxlength="280"></textarea>
                        <p class="help">Up to 280 characters. Leave it empty to remove the note.</p>
                        <div class="flex justify-end gap-2"><button type="button" class="btn" @click="closeNote">Cancel</button><button class="btn btn-primary">Save note</button></div>
                    </form>
                </dialog>
            </div>
            <p class="help mt-3">Enter saves and moves down, Tab saves and moves right, Escape cancels. Clearing a cell removes the value.</p>
        @endif
    @endif
</x-app>
