<x-app width="reading" title="Raise a problem">
    <x-page-header title="Raise a problem" description="Name what's in the way. The team decides when to solve it." />
    <x-errors />
    <form method="POST" action="{{ route('org.problems.store') }}" class="box grid gap-4 sm:grid-cols-2" novalidate>
        @csrf
        @foreach (['source_type', 'source_id', 'back'] as $k)@if (! empty($preset[$k]))<input type="hidden" name="{{ $k }}" value="{{ $preset[$k] }}">@endif @endforeach
        <x-field name="team_seat_id" label="Team" type="select" :options="$teams" :value="$team" />
        <x-field name="horizon" label="Horizon" type="select" :options="['short' => 'Short-term', 'long' => 'Long-term']" :value="$preset['horizon'] ?? 'short'" />
        <x-field name="title" label="Title" :value="$preset['title'] ?? null" required maxlength="160" class="sm:col-span-2" />
        <x-field name="description" label="Description (optional)" type="textarea" rows="3" :value="$preset['description'] ?? null" maxlength="2000" class="sm:col-span-2" />
        <x-field name="owner_id" label="Owner (optional)" type="select" :options="['' => 'No owner'] + $members" help="A member of that team." />
        <div class="sm:col-span-2"><button class="btn btn-primary">Raise problem</button></div>
    </form>
</x-app>
