<x-app :title="$board->title" wide>
    <x-page-header :title="$board->title" :description="$board->archived_at ? 'Archived. Its tasks still show in lists.' : 'Drag a card to another column, or use its menu. The column sets the status.'">
        <x-slot:actions>
            @if ($canEdit)
                <form method="POST" action="{{ route('org.boards.archive', $board) }}">@csrf<button class="btn">{{ $board->archived_at ? 'Restore board' : 'Archive board' }}</button></form>
            @endif
        </x-slot:actions>
    </x-page-header>
    <x-errors />
    @foreach ($errors->get('column') as $e)<p class="error mb-4">{{ $e }}</p>@endforeach

    <div x-data="board" data-url="{{ route('org.boards.move', [$board, '__TASK__']) }}">
    <p class="error mb-2" role="status" data-board-status></p>
    <div class="flex gap-0 overflow-x-auto pb-4">
        @foreach ($board->columns as $col)
            @php $cards = $tasks[$col->id] ?? collect(); @endphp
            <section class="flex w-72 shrink-0 flex-col border-r border-line px-3 first:pl-0" data-column="{{ $col->id }}" @dragover.prevent="over" @dragleave="leave" @drop.prevent="drop" aria-label="{{ $col->title }}">
                <h2 class="label mb-3 flex items-center justify-between">{{ $col->title }} <span class="text-ink-muted">{{ $cards->count() }}</span></h2>
                <div class="flex min-h-24 flex-col gap-2" data-dropzone>
                    @foreach ($cards as $t)
                        @php $steps = $t->steps->count(); $doneSteps = $t->steps->whereNotNull('done_at')->count(); $overdue = $t->isOpen() && $t->due_date->lt($today); @endphp
                        <article class="rounded-[10px] bg-surface p-3 shadow-[var(--shadow-group)]" @can('status', $t) draggable="true" data-task="{{ $t->id }}" @dragstart="start" @dragend="end" @else data-task="{{ $t->id }}" @endcan>
                            <a href="{{ route('org.tasks.show', $t) }}" class="block font-medium hover:underline">{{ $t->title }}</a>
                            <div class="mt-2 flex flex-wrap items-center gap-3 text-small text-ink-secondary">
                                <x-avatar :member="$t->responsible" />
                                <span class="{{ $overdue ? 'cell-off' : '' }}">{{ $t->due_date->format('j M') }}</span>
                                @if ($steps)<span>{{ $doneSteps }}/{{ $steps }}</span>@endif
                                @can('status', $t)
                                <details class="ml-auto open:basis-full open:text-right">
                                    <summary class="icon-btn list-none" aria-label="Move {{ $t->title }} to another column"><x-icon name="ellipsis" /></summary>
                                    <div class="panel mt-1 w-full p-1 text-left">
                                        <p class="px-2 py-1 text-caption text-ink-muted">Move to</p>
                                        @foreach ($board->columns as $c)
                                            @continue($c->id === $col->id)
                                            <form method="POST" action="{{ route('org.boards.move', [$board, $t]) }}">@csrf<input type="hidden" name="column_id" value="{{ $c->id }}"><button class="menu-item w-full text-left">{{ $c->title }}</button></form>
                                        @endforeach
                                    </div>
                                </details>
                                @endcan
                            </div>
                        </article>
                    @endforeach
                </div>
                @if ($canEdit)
                    <details class="mt-3 text-small">
                        <summary class="cursor-pointer text-ink-secondary">Column settings</summary>
                        <form method="POST" action="{{ route('org.boards.columns.update', [$board, $col]) }}" class="mt-2 flex gap-1">@csrf @method('PUT')<label class="sr-only" for="ct-{{ $col->id }}">Column title</label><input id="ct-{{ $col->id }}" name="title" class="input min-h-8 py-1 text-small" value="{{ $col->title }}" maxlength="60"><button class="btn btn-sm">Rename</button></form>
                        <div class="mt-2 flex flex-wrap gap-1">
                            <form method="POST" action="{{ route('org.boards.columns.update', [$board, $col]) }}">@csrf @method('PUT')<input type="hidden" name="move" value="left"><button class="btn btn-sm">Move left</button></form>
                            <form method="POST" action="{{ route('org.boards.columns.update', [$board, $col]) }}">@csrf @method('PUT')<input type="hidden" name="move" value="right"><button class="btn btn-sm">Move right</button></form>
                            @unless ($col->is_done_column)
                                <form method="POST" action="{{ route('org.boards.columns.update', [$board, $col]) }}">@csrf @method('PUT')<input type="hidden" name="done" value="1"><button class="btn btn-sm">Make done column</button></form>
                                <x-confirm :action="route('org.boards.columns.destroy', [$board, $col])" method="DELETE" :message="'Delete column '.$col->title.'?'" label="Delete" />
                            @endunless
                        </div>
                    </details>
                @endif
            </section>
        @endforeach
        @if ($canEdit && $board->columns->count() < 8)
            <details class="shrink-0 px-3 open:w-60" @if ($errors->has('title') && old('_column')) open @endif>
                <summary class="btn btn-sm list-none"><x-icon name="plus" />Add column</summary>
                <form method="POST" action="{{ route('org.boards.columns.store', $board) }}" class="mt-3" novalidate>
                    @csrf
                    <input type="hidden" name="_column" value="1">
                    <x-field name="title" label="Column title" maxlength="60" required />
                    <button class="btn btn-sm mt-2">Add column</button>
                </form>
            </details>
        @endif
    </div>
    </div>

    @unless ($board->archived_at)
        <section class="mt-8 border-t border-line pt-6">
            <form method="POST" action="{{ route('org.tasks.store') }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[2fr_2fr_1fr_1fr_auto] lg:items-end" novalidate>
                @csrf
                <input type="hidden" name="full" value="1"><input type="hidden" name="board_id" value="{{ $board->id }}">
                @if ($board->team_seat_id)<input type="hidden" name="team_seat_id" value="{{ $board->team_seat_id }}">@endif
                <p class="subheading sm:col-span-2 lg:col-span-5">Add a card</p>
                <x-field name="title" label="Title" required />
                <x-field name="output" label="Output" placeholder="What will exist when this is done?" required />
                <x-field name="responsible_id" label="Responsible" type="select" :options="$members" :value="app(\App\Services\Tenant::class)->membership->id" />
                <x-field name="due_date" label="Due" type="date" :value="$today->addDays(7)->toDateString()" />
                <button class="btn">Add card</button>
            </form>
        </section>
    @endunless
</x-app>
