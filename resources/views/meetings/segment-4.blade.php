<p class="mb-4 text-small">Completion last week: <span class="font-medium {{ $rate !== null && $rate < 90 ? 'cell-off' : '' }}">{{ $rate !== null ? $rate.'%' : 'no tasks due' }}</span> <span class="text-ink-secondary">(aim for 90%)</span></p>
@if ($tasks->isEmpty())
    <p class="text-ink-secondary">No tasks due in the last 7 days or overdue.</p>
@else
    <div class="rows">@foreach ($tasks as $t)<x-task-row :task="$t" :today="$today" />@endforeach</div>
@endif
