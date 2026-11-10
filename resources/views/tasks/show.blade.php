@php
    $today = \Carbon\Carbon::parse($today->toDateString());
    $overdue = $task->isOpen() && $task->due_date->lt($today);
    $consulted = $task->people->where('pivot.role', 'consulted')->pluck('id')->all();
    $informed = $task->people->where('pivot.role', 'informed')->pluck('id')->all();
    $openSteps = $task->steps->whereNull('done_at')->count();
@endphp
<x-app :title="$task->title">
    <x-page-header :title="$task->title" :description="$task->output" />
    @if ($source)<p class="-mt-6 mb-6 text-small text-ink-secondary">From: {{ $source[0] }}, @if ($source[2])<a href="{{ $source[2] }}" class="link">{{ $source[1] }}</a>@else{{ $source[1] }}@endif</p>@endif
    <x-errors />
    @if (session('confirm_done') && session('confirm_done')[0] === $task->id)
        <div class="banner banner-brass mb-6" role="alert">
            <p>{{ session('confirm_done')[1] }} {{ \Illuminate\Support\Str::plural('step', session('confirm_done')[1]) }} {{ session('confirm_done')[1] === 1 ? "isn't" : "aren't" }} checked. Mark as done anyway?</p>
            <form method="POST" action="{{ route('org.tasks.status', $task) }}" class="mt-3">@csrf<input type="hidden" name="status" value="done"><input type="hidden" name="confirmed" value="1"><button class="btn btn-primary">Mark as done</button></form>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_300px] lg:items-start">
        <div class="box min-w-0 space-y-8">
            <section>
                <div class="flex flex-wrap items-center gap-3">
                    <span class="badge">{{ ucfirst($task->status) }}</span>
                    <span class="text-small {{ $overdue ? 'cell-off font-medium' : 'text-ink-secondary' }}">Due {{ \App\Services\Calendar::formatDate($task->due_date) }}@if ($overdue), {{ (int) $task->due_date->diffInDays($today) }} days overdue @endif</span>
                </div>
                @if ($canStatus)
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach (['todo' => 'Move to to do', 'doing' => 'Start', 'done' => 'Mark as done', 'cancelled' => 'Cancel task'] as $s => $label)
                            @continue($s === $task->status || ($s === 'todo' && $task->status === 'todo'))
                            <form method="POST" action="{{ route('org.tasks.status', $task) }}">@csrf<input type="hidden" name="status" value="{{ $s }}"><button class="btn btn-sm {{ $s === 'cancelled' ? 'btn-danger' : '' }}">{{ in_array($task->status, ['done', 'cancelled']) && $s === 'todo' ? 'Reopen' : $label }}</button></form>
                        @endforeach
                    </div>
                @endif
            </section>

            <section>
                <h2 class="heading mb-2">Steps</h2>
                @forelse ($task->steps as $step)
                    <form method="POST" action="{{ route('org.tasks.step', [$task, $step]) }}" class="flex min-h-11 items-center gap-2 border-b border-line">
                        @csrf
                        <button class="flex items-center gap-2 text-left" @disabled(! $canEdit) aria-pressed="{{ $step->done_at ? 'true' : 'false' }}">
                            <span class="inline-flex h-4 w-4 items-center justify-center rounded-[4px] border border-bar {{ $step->done_at ? 'bg-brass text-on-brass' : '' }}">@if ($step->done_at)<x-icon name="check" :size="12" />@endif</span>
                            <span class="{{ $step->done_at ? 'text-ink-secondary line-through' : '' }}">{{ $step->title }}</span>
                        </button>
                    </form>
                @empty
                    <p class="text-small text-ink-secondary">No steps. Add them in the form below if this task needs a checklist.</p>
                @endforelse
            </section>

            @if ($canEdit)
                <section>
                    <h2 class="heading mb-3">Details</h2>
                    <form method="POST" action="{{ route('org.tasks.update', $task) }}" class="grid gap-4 sm:grid-cols-2" novalidate>
                        @csrf @method('PUT')
                        <x-field name="title" label="Title" :value="$task->title" required class="sm:col-span-2" />
                        <x-field name="output" label="Output" :value="$task->output" type="textarea" rows="2" help="What will exist when this is done?" class="sm:col-span-2" />
                        <x-field name="responsible_id" label="Responsible" type="select" :options="$members" :value="$task->responsible_id" />
                        <x-field name="accountable_id" label="Accountable" type="select" :options="$members" :value="$task->accountable_id" />
                        <div>
                            <label class="field-label" for="consulted">Consulted</label>
                            <select id="consulted" name="consulted[]" multiple class="input min-h-24">@foreach ($members as $id => $n)<option value="{{ $id }}" @selected(in_array($id, old('consulted', $consulted)))>{{ $n }}</option>@endforeach</select>
                            @error('consulted')<p class="error">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="field-label" for="informed">Informed</label>
                            <select id="informed" name="informed[]" multiple class="input min-h-24">@foreach ($members as $id => $n)<option value="{{ $id }}" @selected(in_array($id, old('informed', $informed)))>{{ $n }}</option>@endforeach</select>
                            @error('informed')<p class="error">{{ $message }}</p>@enderror
                        </div>
                        <x-field name="due_date" label="Due" type="date" :value="$task->due_date->toDateString()" required />
                        <x-field name="team_seat_id" label="Team" type="select" :options="['' => 'No team'] + $teams" :value="$task->team_seat_id" />
                        <x-field name="definition_of_done" label="Definition of done" type="textarea" rows="2" :value="$task->definition_of_done" class="sm:col-span-2" />
                        <div class="sm:col-span-2">
                            <label class="field-label" for="steps">Steps</label>
                            <textarea id="steps" class="input" rows="4" name="steps_text">{{ old('steps_text', $task->steps->pluck('title')->join(PHP_EOL)) }}</textarea>
                            <p class="help">One per line, up to 30.</p>
                            @error('steps')<p class="error">{{ $message }}</p>@enderror
                        </div>
                        <x-field name="board_id" label="Board" type="select" :options="['' => 'No board'] + $boards->pluck('title', 'id')->all()" :value="$task->board_id" />
                        <div class="flex items-end sm:col-span-2"><button class="btn btn-primary">Save changes</button></div>
                    </form>
                </section>
            @endif
        </div>

        <aside class="box order-first lg:order-none lg:sticky lg:top-10 lg:self-start space-y-4 text-small">
            <dl class="rows">
                <div class="flex justify-between py-2"><dt class="text-ink-secondary">Responsible</dt><dd>{{ $task->responsible->name }}</dd></div>
                <div class="flex justify-between py-2"><dt class="text-ink-secondary">Accountable</dt><dd>{{ $task->accountable->name }}</dd></div>
                <div class="flex justify-between gap-4 py-2"><dt class="text-ink-secondary">Consulted</dt><dd class="text-right">{{ $task->people->where('pivot.role', 'consulted')->pluck('name')->join(', ') ?: '–' }}</dd></div>
                <div class="flex justify-between gap-4 py-2"><dt class="text-ink-secondary">Informed</dt><dd class="text-right">{{ $task->people->where('pivot.role', 'informed')->pluck('name')->join(', ') ?: '–' }}</dd></div>
                <div class="flex justify-between py-2"><dt class="text-ink-secondary">Created by</dt><dd>{{ $task->creator->name }}</dd></div>
                @if ($task->definition_of_done)<div class="py-2"><dt class="text-ink-secondary">Definition of done</dt><dd class="mt-1">{{ $task->definition_of_done }}</dd></div>@endif
                @if ($task->board)<div class="flex justify-between py-2"><dt class="text-ink-secondary">Board</dt><dd><a class="link" href="{{ route('org.boards.show', $task->board) }}">{{ $task->board->title }}</a></dd></div>@endif
            </dl>
            @if ($canDelete)<x-confirm :action="route('org.tasks.destroy', $task)" method="DELETE" :message="'Delete '.$task->title.'?'" label="Delete task" />@endif
        </aside>
    </div>
</x-app>
