<div class="sidepanel fade-in" role="dialog" aria-modal="true" aria-labelledby="new-event-title">
    <div class="mb-4 flex items-center justify-between">
        <h2 id="new-event-title" class="heading">New event</h2>
        <a href="{{ route('org.calendar.index', $base + ['date' => $date->toDateString()]) }}" class="btn btn-ghost btn-sm" aria-label="Close"><x-icon name="x" /></a>
    </div>
    <form method="POST" action="{{ route('org.calendar.store') }}" class="space-y-4" novalidate>
        @csrf
        <x-field name="type" label="Type" type="select" :options="$creatable" :value="request('type')" />
        <x-field name="title" label="Title" :value="request('title')" help="Guided events default to the guide's name." maxlength="160" />
        <x-field name="scope" label="Who can see it" type="select" :options="['org' => 'Organization', 'team' => 'A team', 'private' => 'Only me']" help="Sessions are team events, Check-ins are for the two of you, Reflection and Matrix are private." />
        <x-field name="team_seat_id" label="Team" type="select" :options="['' => 'No team'] + $teams" />
        @if ($partners)<x-field name="partner_id" label="Check-in with" type="select" :options="['' => 'Choose a person'] + $partners" :value="request('partner_id')" />@endif
        <x-field name="date" label="Date" type="date" :value="$date->toDateString()" required />
        <div class="grid grid-cols-2 gap-3">
            <x-field name="start_time" label="Starts" type="time" value="09:00" />
            <x-field name="end_time" label="Ends" type="time" value="10:00" />
        </div>
        <label class="flex min-h-11 items-center gap-2"><input type="hidden" name="all_day" value="0"><input type="checkbox" name="all_day" value="1" class="h-4 w-4 accent-brass"> All day</label>
        <div class="grid grid-cols-2 gap-3">
            <x-field name="repeat" label="Repeats" type="select" :options="['none' => 'Does not repeat', 'weekly' => 'Weekly', 'biweekly' => 'Every 2 weeks', 'monthly' => 'Monthly', 'quarterly' => 'Quarterly']" />
            <x-field name="until" label="Until (optional)" type="date" />
        </div>
        <button class="btn btn-primary">Create event</button>
    </form>
</div>
