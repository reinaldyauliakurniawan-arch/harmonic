<x-app title="Tasks">
    <x-page-header title="Tasks" description="Every task in your organization: who is responsible, what it produces, and when it is due.">
        <x-slot:actions><a href="{{ route('org.boards.index') }}" class="btn">Boards</a></x-slot:actions>
    </x-page-header>
    <x-errors />

    <details class="mb-8" @if ($errors->any()) open @endif>
        <summary class="btn btn-primary list-none"><x-icon name="plus" />Add a task</summary>
        <div class="panel mt-3 p-4"><x-quick-task :team="$tab === 'team' ? $team : null" heading="New task" /></div>
    </details>

    <div class="border-b border-line">
        @foreach (['mine' => 'Mine', 'team' => 'Team', 'all' => 'All'] as $k => $l)
            <a href="{{ route('org.tasks.index', ['tab' => $k]) }}" class="tab" @if ($tab === $k) aria-current="page" @endif>{{ $l }}</a>
        @endforeach
    </div>
    <form method="GET" class="filters py-3" x-data="autoSubmit" @change="submit">
        <input type="hidden" name="tab" value="{{ $tab }}">
        @if ($tab !== 'mine')
            <label>Team <select name="team" class="input input-sm">@if ($tab === 'all')<option value="">Any</option>@endif @foreach ($teams as $id => $l)<option value="{{ $id }}" @selected((string) $team === (string) $id)>{{ $l }}</option>@endforeach</select></label>
        @endif
        <label>Status <select name="status" class="input input-sm">@foreach (['open' => 'Open', 'todo' => 'To do', 'doing' => 'Doing', 'done' => 'Done', 'cancelled' => 'Cancelled'] as $k => $l)<option value="{{ $k }}" @selected(($q['status'] ?? 'open') === $k)>{{ $l }}</option>@endforeach</select></label>
        <label>Due <select name="due" class="input input-sm">@foreach (['' => 'Any time', 'overdue' => 'Overdue', 'this_week' => 'This week', 'next_week' => 'Next week', 'later' => 'Later'] as $k => $l)<option value="{{ $k }}" @selected(($q['due'] ?? '') === $k)>{{ $l }}</option>@endforeach</select></label>
        <label>Person <select name="person" class="input input-sm"><option value="">Anyone</option>@foreach ($members as $id => $l)<option value="{{ $id }}" @selected((string) ($q['person'] ?? '') === (string) $id)>{{ $l }}</option>@endforeach</select></label>
        <label>From <select name="source" class="input input-sm">@foreach (['' => 'Anywhere', 'none' => 'Added directly', 'problem' => 'Problems', 'mission' => 'Missions', 'milestone' => 'Milestones', 'event' => 'Events', 'meeting' => 'Meetings', 'procedure' => 'Procedures'] as $k => $l)<option value="{{ $k }}" @selected(($q['source'] ?? '') === $k)>{{ $l }}</option>@endforeach</select></label>
        <button class="btn btn-sm btn-ghost" data-apply>Apply</button>
    </form>

    @if ($tasks->isEmpty())
        <x-empty :heading="$tab === 'mine' ? 'Nothing on your plate' : 'No tasks match'" :text="$tab === 'mine' ? 'Tasks where you are Responsible or Accountable show up here. Add one above, or pick some up in the next Weekly Meeting.' : 'Change the filters above to see more.'" />
    @else
        <div class="rows">@foreach ($tasks as $t)<x-task-row :task="$t" :today="$today" />@endforeach</div>
        {{ $tasks->links() }}
    @endif
</x-app>
