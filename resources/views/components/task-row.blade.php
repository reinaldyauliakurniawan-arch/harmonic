@props(['task', 'today'])
@php
    $todayDate = \Carbon\Carbon::parse($today->toDateString());
    $overdue = $task->isOpen() && $task->due_date->lt($todayDate);
    $days = $overdue ? (int) $task->due_date->diffInDays($todayDate) : 0;
    $steps = $task->steps->count();
    $done = $task->steps->whereNotNull('done_at')->count();
    $closed = in_array($task->status, ['done', 'cancelled'], true);
    $canTick = $task->status !== 'cancelled' && auth()->user()->can('status', $task);
    $confirm = session('confirm_done') && session('confirm_done')[0] === $task->id ? session('confirm_done')[1] : 0;
@endphp
<div>
<div class="grid grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-x-3 gap-y-1 py-2 sm:grid-cols-[auto_minmax(0,1fr)_150px_120px_84px]">
    @if ($canTick)
        <form method="POST" action="{{ route('org.tasks.status', $task) }}">@csrf<input type="hidden" name="status" value="{{ $task->status === 'done' ? 'todo' : 'done' }}">
            <button class="task-check" aria-pressed="{{ $task->status === 'done' ? 'true' : 'false' }}" aria-label="{{ $task->status === 'done' ? 'Reopen' : 'Mark done' }}: {{ $task->title }}"><span>@if ($task->status === 'done')<x-icon name="check" :size="12" />@endif</span></button>
        </form>
    @else
        <span class="task-check is-static" aria-hidden="true"><span>@if ($task->status === 'done')<x-icon name="check" :size="12" />@endif</span></span>
    @endif
    <div class="min-w-0">
        <a href="{{ route('org.tasks.show', $task) }}" class="font-medium hover:underline {{ $closed ? 'text-ink-secondary line-through' : '' }}">{{ $task->title }}</a>
        <p class="truncate text-small text-ink-secondary">{{ $task->output }}@if ($steps), {{ $done }}/{{ $steps }} steps @endif</p>
    </div>
    <div class="hidden min-w-0 items-center gap-2 text-small sm:flex"><x-avatar :member="$task->responsible" /><span class="truncate">{{ $task->responsible->name }}</span></div>
    <div class="col-start-3 row-start-1 text-right text-small sm:col-start-auto sm:row-start-auto sm:text-left {{ $overdue ? 'cell-off font-medium' : 'text-ink-secondary' }}">
        @if ($overdue)<span class="inline-flex items-center gap-1"><x-icon name="triangle" :size="10" />{{ $days }} {{ \Illuminate\Support\Str::plural('day', $days) }} overdue</span>@else{{ $task->due_date->format('D, j M') }}@endif
    </div>
    <div class="hidden text-right sm:block">
        <span class="pill-status {{ ['doing' => 'is-doing', 'done' => 'is-done'][$task->status] ?? '' }}">{{ ['todo' => 'To do', 'doing' => 'Doing', 'done' => 'Done', 'cancelled' => 'Cancelled'][$task->status] }}</span>
    </div>
</div>
@if ($confirm)
    <div class="mb-3 flex flex-wrap items-center gap-3 rounded-[8px] bg-info-subtle px-3 py-2 text-small" role="alert">
        <span>{{ $confirm }} {{ \Illuminate\Support\Str::plural('step', $confirm) }} {{ $confirm === 1 ? "isn't" : "aren't" }} checked. Mark as done anyway?</span>
        <form method="POST" action="{{ route('org.tasks.status', $task) }}">@csrf<input type="hidden" name="status" value="done"><input type="hidden" name="confirmed" value="1"><button class="btn btn-sm btn-primary">Mark as done</button></form>
    </div>
@endif
</div>
