@php $members = \App\Services\Tasks::memberOptions(); $me = app(\App\Services\Tenant::class)->membership->id; @endphp
@if ($strict)
    <form method="POST" action="{{ route('org.tasks.store') }}" class="panel grid gap-3 p-4 sm:grid-cols-2" novalidate>
        @csrf
        <input type="hidden" name="full" value="1"><input type="hidden" name="strict" value="1">
        <input type="hidden" name="source_type" value="problem"><input type="hidden" name="source_id" value="{{ $p->id }}"><input type="hidden" name="team_seat_id" value="{{ $p->team_seat_id }}">
        <p class="subheading sm:col-span-2">Add a task (full RACI+)</p>
        <x-field name="title" label="Title" required maxlength="160" class="sm:col-span-2" />
        <x-field name="output" label="Output" placeholder="What will exist when this is done?" maxlength="280" class="sm:col-span-2" />
        <x-field name="responsible_id" label="Responsible" type="select" :options="$members" :value="$me" />
        <x-field name="accountable_id" label="Accountable" type="select" :options="$members" :value="$me" />
        <x-field name="due_date" label="Due" type="date" :value="now()->addDays(7)->toDateString()" />
        <x-field name="definition_of_done" label="Definition of done" maxlength="2000" />
        <x-field name="steps_text" label="Steps (one per line)" type="textarea" rows="3" class="sm:col-span-2" />
        <div><button class="btn">Add task</button></div>
    </form>
@else
    <x-quick-task source-type="problem" :source-id="$p->id" :team="$p->team_seat_id" heading="Add a task" />
@endif
