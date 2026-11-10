<x-app width="reading" title="New Mission">
    <x-page-header title="New Mission" description="A 90-day priority. Company Missions are set by leadership, team Missions by the team leader, personal ones by the owner or anyone above them." />
    <x-errors />
    @php $now = $cal->quarterIndex($cal->today()); @endphp
    <form method="POST" action="{{ route('org.missions.store') }}" class="box grid gap-4 sm:grid-cols-2" novalidate>
        @csrf
        <x-field name="level" label="Level" type="select" :options="['personal' => 'Personal', 'team' => 'Team', 'company' => 'Company']" :value="$preset['level'] ?? 'personal'" />
        <x-field name="team_seat_id" label="Team (team Missions)" type="select" :options="['' => 'No team'] + $teams" :value="$preset['team_seat_id'] ?? ''" />
        <x-field name="title" label="Title" required maxlength="160" class="sm:col-span-2" />
        <x-field name="done_definition" label="Definition of done" type="textarea" rows="3" placeholder="How will everyone know it's done?" maxlength="500" class="sm:col-span-2" />
        <x-field name="owner_id" label="Owner" type="select" :options="$members" :value="$preset['owner_id'] ?? app(\App\Services\Tenant::class)->membership->id" />
        <x-field name="quarter_start" label="Quarter" type="select" :options="[$cal->quarterStart($now)->toDateString() => $cal->quarterLabel($cal->quarterStart($now)).' (current)', $cal->quarterStart($now + 1)->toDateString() => $cal->quarterLabel($cal->quarterStart($now + 1)).' (next)']" />
        <x-field name="parent_id" label="Supports (optional)" type="select" :options="['' => 'None'] + $parents->mapWithKeys(fn ($p) => [$p->id => ucfirst($p->level).': '.$p->title])->all()" class="sm:col-span-2" />
        <div class="sm:col-span-2"><button class="btn btn-primary">Create Mission</button></div>
    </form>
</x-app>
