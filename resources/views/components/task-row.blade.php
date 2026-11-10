@props(['task', 'today'])
@php
    $todayDate = \Carbon\Carbon::parse($today->toDateString());
    $overdue = $task->isOpen() && $task->due_date->lt($todayDate);
    $days = $overdue ? (int) $task->due_date->diffInDays($todayDate) : 0;
    $steps = $task->steps->count();
    $done = $task->steps->whereNotNull('done_at')->count();
    $closed = in_array($task->status, ['done', 'cancelled'], true);
@endphp
<div class="grid grid-cols-[1fr_auto] items-center gap-x-4 gap-y-1 py-3 sm:grid-cols-[minmax(0,1fr)_150px_120px_84px]">
    <div class="min-w-0">
        <a href="{{ route('org.tasks.show', $task) }}" class="font-medium hover:underline {{ $closed ? 'text-ink-secondary line-through' : '' }}">{{ $task->title }}</a>
        <p class="truncate text-small text-ink-secondary">{{ $task->output }}@if ($steps), {{ $done }}/{{ $steps }} steps @endif</p>
    </div>
    <div class="hidden min-w-0 items-center gap-2 text-small sm:flex"><x-avatar :member="$task->responsible" /><span class="truncate">{{ $task->responsible->name }}</span></div>
    <div class="col-start-2 row-start-1 text-right text-small sm:col-start-auto sm:row-start-auto sm:text-left {{ $overdue ? 'cell-off font-medium' : 'text-ink-secondary' }}">
        @if ($overdue)<span class="inline-flex items-center gap-1"><x-icon name="triangle" :size="10" />{{ $days }} {{ \Illuminate\Support\Str::plural('day', $days) }} overdue</span>@else{{ $task->due_date->format('D, j M') }}@endif
    </div>
    <div class="hidden text-right sm:block">
        <span class="pill-status {{ ['doing' => 'is-doing', 'done' => 'is-done'][$task->status] ?? '' }}">{{ ['todo' => 'To do', 'doing' => 'Doing', 'done' => 'Done', 'cancelled' => 'Cancelled'][$task->status] }}</span>
    </div>
</div>
