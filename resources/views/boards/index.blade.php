<x-app title="Boards">
    <x-page-header title="Boards" description="Boards show tasks as cards in columns. A task's column sets its status." />
    <x-errors />
    <form method="POST" action="{{ route('org.boards.store') }}" class="mb-8 grid max-w-2xl gap-3 sm:grid-cols-[1fr_200px_auto] sm:items-end" novalidate>
        @csrf
        <x-field name="title" label="Board title" required maxlength="120" />
        <x-field name="team_seat_id" label="Team (optional)" type="select" :options="['' => 'No team'] + $teams" />
        <button class="btn btn-primary">Create board</button>
    </form>
    <div class="mb-4 border-b border-line">
        <a class="tab" href="{{ route('org.boards.index') }}" @unless ($archived) aria-current="page" @endunless>Active</a>
        <a class="tab" href="{{ route('org.boards.index', ['archived' => 1]) }}" @if ($archived) aria-current="page" @endif>Archived</a>
    </div>
    @if ($boards->isEmpty())
        <x-empty heading="Start a board" text="Create a board for a project or a team. It starts with To do, Doing, and Done." />
    @else
        <div class="rows">
            @foreach ($boards as $b)
                <div class="flex min-h-12 items-center justify-between gap-3">
                    <a href="{{ route('org.boards.show', $b) }}" class="font-medium hover:underline">{{ $b->title }}</a>
                    <span class="text-small text-ink-secondary">{{ $b->tasks_count }} open {{ \Illuminate\Support\Str::plural('task', $b->tasks_count) }}@if ($b->team_seat_id), {{ $teams[$b->team_seat_id] ?? '' }}@endif</span>
                </div>
            @endforeach
        </div>
        {{ $boards->links() }}
    @endif
</x-app>
