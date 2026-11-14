@php
    $weeks = $cal->weeksOfQuarter($cal->quarterStart($qi));
    $thisWeek = $cal->currentWeekStart()->toDateString();
    $fmt = fn ($d) => \App\Services\Calendar::formatDate($d);
    $indent = fn ($depth, $sub) => ($sub ? ['pl-8', 'pl-12', 'pl-16', 'pl-20'] : ['pl-3', 'pl-6', 'pl-10', 'pl-14'])[min($depth, 3)];
    $today = $timeline['today'];
    $diamond = fn ($at, $state) => '<svg x="'.$at.'%" y="22" overflow="visible"><path class="gantt-diamond '.$state.'" d="M0 -6 L6 0 L0 6 L-6 0 Z" /></svg>';
@endphp
<p class="mb-3 text-small text-ink-secondary">Each Mission spans the quarter, filled by its milestone progress. Diamonds mark milestones; thin bars run from the day a task was added to its due date.</p>
<div class="gantt panel" role="region" aria-label="Missions timeline, {{ $cal->quarterLabel($cal->quarterStart($qi)) }}" tabindex="0">
    <div class="gantt-row gantt-head" aria-hidden="true">
        <div class="gantt-label text-caption text-ink-secondary">Mission</div>
        <div class="gantt-weeks">
            @foreach ($weeks as $i => $w)
                <span class="{{ $w->toDateString() === $thisWeek ? 'is-now' : '' }}" title="{{ $fmt($w) }}">W{{ $i + 1 }}</span>
            @endforeach
        </div>
    </div>
    @foreach ($timeline['rows'] as $row)
        @php $m = $row['m']; $more = $row['milestones'] || $row['tasks']; @endphp
        <details class="gantt-group" open>
            <summary class="gantt-row">
                <div class="gantt-label {{ $indent($row['depth'], false) }}">
                    <span class="flex min-w-0 items-center gap-1">
                        <span class="gantt-caret {{ $more ? '' : 'invisible' }}"><x-icon name="chevron-right" :size="14" /></span>
                        <a href="{{ route('org.missions.show', $m) }}" class="truncate font-medium hover:underline">{{ $m->title }}</a>
                    </span>
                    <span class="flex min-w-0 items-center gap-2 pl-5 text-caption text-ink-secondary"><span class="truncate">{{ $m->owner->name }}</span><x-status :value="$m->status" /></span>
                </div>
                <svg class="gantt-track" height="44" role="img" aria-label="{{ $row['progress'] !== null ? $row['progress'].'% of milestones done' : 'No milestones yet' }}">
                    @if ($today !== null)<line class="gantt-today" x1="{{ $today }}%" x2="{{ $today }}%" y1="0" y2="44" />@endif
                    <rect class="gantt-bar is-{{ $m->status }}" x="0" y="15" width="100%" height="14" rx="7" />
                    @if ($row['progress'])<rect class="gantt-fill" x="0" y="15" width="{{ $row['progress'] }}%" height="14" rx="7" />@endif
                    @foreach ($row['milestones'] as $x)
                        @if ($x['at'] !== null){!! $diamond($x['at'], $x['ms']->done_at ? 'is-done' : ($x['late'] ? 'is-late' : '')) !!}@endif
                    @endforeach
                </svg>
            </summary>
            @foreach ($row['milestones'] as $x)
                <div class="gantt-row gantt-sub">
                    <div class="gantt-label {{ $indent($row['depth'], true) }}">
                        <span class="truncate {{ $x['ms']->done_at ? 'text-ink-secondary line-through' : '' }}">{{ $x['ms']->title }}</span>
                        <span class="truncate text-caption {{ $x['late'] ? 'cell-off' : 'text-ink-secondary' }}">Milestone, {{ $x['ms']->due_date ? $x['ms']->due_date->format('D, j M') : 'no date' }}{{ $x['ms']->done_at ? ', done' : ($x['late'] ? ', late' : '') }}</span>
                    </div>
                    <svg class="gantt-track" height="44" aria-hidden="true">
                        @if ($today !== null)<line class="gantt-today" x1="{{ $today }}%" x2="{{ $today }}%" y1="0" y2="44" />@endif
                        @if ($x['at'] !== null){!! $diamond($x['at'], $x['ms']->done_at ? 'is-done' : ($x['late'] ? 'is-late' : '')) !!}@endif
                    </svg>
                </div>
            @endforeach
            @foreach ($row['tasks'] as $x)
                @php $t = $x['t']; @endphp
                <div class="gantt-row gantt-sub">
                    <div class="gantt-label {{ $indent($row['depth'], true) }}">
                        <a href="{{ route('org.tasks.show', $t) }}" class="truncate hover:underline {{ $t->isOpen() ? '' : 'text-ink-secondary line-through' }}">{{ $t->title }}</a>
                        <span class="truncate text-caption {{ $x['late'] ? 'cell-off' : 'text-ink-secondary' }}">Task, {{ $t->responsible->name }}, due {{ $t->due_date->format('D, j M') }}{{ $x['late'] ? ', overdue' : '' }}</span>
                    </div>
                    <svg class="gantt-track" height="44" aria-hidden="true">
                        @if ($today !== null)<line class="gantt-today" x1="{{ $today }}%" x2="{{ $today }}%" y1="0" y2="44" />@endif
                        <rect class="gantt-task {{ $t->status === 'done' ? 'is-done' : ($x['late'] ? 'is-late' : '') }}" x="{{ $x['from'] }}%" y="18" width="{{ $x['width'] }}%" height="8" rx="4" />
                    </svg>
                </div>
            @endforeach
        </details>
    @endforeach
</div>
