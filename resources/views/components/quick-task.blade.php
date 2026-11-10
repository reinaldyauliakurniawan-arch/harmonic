@props(['sourceType' => null, 'sourceId' => null, 'team' => null, 'heading' => 'Add a task', 'note' => null, 'title' => null, 'responsible' => null])
@php
    $tenant = app(\App\Services\Tenant::class);
    $members = \App\Services\Tasks::memberOptions();
    $due = \App\Services\Calendar::for($tenant->organization)->today()->addDays(7)->toDateString();
@endphp
<form method="POST" action="{{ route('org.tasks.store') }}" {{ $attributes->merge(['class' => 'grid grid-cols-[repeat(auto-fit,minmax(180px,1fr))] items-end gap-3']) }} novalidate>
    @csrf
    @if ($sourceType)<input type="hidden" name="source_type" value="{{ $sourceType }}"><input type="hidden" name="source_id" value="{{ $sourceId }}">@endif
    @if ($team)<input type="hidden" name="team_seat_id" value="{{ $team }}">@endif
    <p class="subheading col-span-full">{{ $heading }}</p>
    @if ($note)<p class="help -mt-2 col-span-full">{{ $note }}</p>@endif
    <x-field name="title" label="Title" :value="$title" required maxlength="160" />
    <x-field name="output" label="Output" placeholder="What will exist when this is done?" required maxlength="280" />
    <x-field name="responsible_id" label="Responsible" type="select" :options="$members" :value="$responsible ?? $tenant->membership->id" />
    <x-field name="due_date" label="Due" type="date" :value="$due" required />
    <button class="btn">Add task</button>
</form>
