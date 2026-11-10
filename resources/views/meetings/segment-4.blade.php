<p class="mb-4 text-small">Completion last week: <span class="font-medium {{ $rate !== null && $rate < 90 ? 'cell-off' : '' }}">{{ $rate !== null ? $rate.'%' : 'no tasks due' }}</span> <span class="text-ink-secondary">(aim for 90%)</span></p>
@forelse ($tasks as $t)
    <div class="flex flex-wrap items-center gap-3">
        <div class="min-w-0 flex-1"><x-task-row :task="$t" :today="$today" /></div>
        @if ($t->isOpen() && auth()->user()->can('status', $t))
            <form method="POST" action="{{ route('org.tasks.status', $t) }}">@csrf<input type="hidden" name="status" value="done"><input type="hidden" name="confirmed" value="1"><button class="btn btn-sm">Mark done</button></form>
        @endif
    </div>
@empty
    <p class="text-ink-secondary">No tasks due in the last 7 days or overdue.</p>
@endforelse
