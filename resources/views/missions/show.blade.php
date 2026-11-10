@php $stale = $mission->isOpen() && (! $mission->status_updated_at || $mission->status_updated_at->lt(now()->subDays(7))); @endphp
<x-app :title="$mission->title">
    <x-page-header :title="$mission->title" :description="$mission->done_definition" />
    <x-errors />
    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_300px] lg:items-start">
        <div class="box min-w-0 space-y-8">
            <section class="flex flex-wrap items-center gap-3">
                <x-status :value="$mission->status" />
                @if ($stale)<span class="text-small text-ink-muted">Not updated this week</span>@endif
                @can('status', $mission)
                    @if ($mission->isOpen())
                        <form method="POST" action="{{ route('org.missions.status', $mission) }}">@csrf<input type="hidden" name="status" value="on_track"><button class="btn btn-sm">On track</button></form>
                        <form method="POST" action="{{ route('org.missions.status', $mission) }}">@csrf<input type="hidden" name="status" value="off_track"><button class="btn btn-sm">Off track</button></form>
                    @endif
                @endcan
                @if ($mission->status === 'off_track')
                    <a class="btn btn-sm" href="{{ route('org.problems.create', ['team' => $mission->team_seat_id, 'title' => $mission->title.' is off track', 'source_type' => 'mission', 'source_id' => $mission->id]) }}">Raise problem</a>
                @endif
            </section>

            @if ($ended && $mission->isOpen())
                @can('close', $mission)
                    <section class="banner banner-brass">
                        <p class="font-medium">This quarter has ended. Close the Mission.</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            @foreach (['done' => 'Done', 'not_done' => 'Not done', 'carry' => 'Carry into next quarter'] as $k => $l)
                                <form method="POST" action="{{ route('org.missions.close', $mission) }}">@csrf<input type="hidden" name="result" value="{{ $k }}"><button class="btn btn-sm">{{ $l }}</button></form>
                            @endforeach
                        </div>
                    </section>
                @endcan
            @endif

            <section>
                <h2 class="heading mb-2">Milestones ({{ $mission->milestones->whereNotNull('done_at')->count() }} of {{ $mission->milestones->count() }})</h2>
                <div class="rows">
                    @foreach ($mission->milestones as $ms)
                        <div class="flex flex-wrap items-center gap-3 py-2">
                            @can('update', $mission)
                                <form method="POST" action="{{ route('org.missions.milestones.toggle', [$mission, $ms]) }}">@csrf<button class="flex items-center gap-2" aria-pressed="{{ $ms->done_at ? 'true' : 'false' }}"><span class="inline-flex h-4 w-4 items-center justify-center rounded-[4px] border border-bar {{ $ms->done_at ? 'bg-brass text-on-brass' : '' }}">@if ($ms->done_at)<x-icon name="check" :size="12" />@endif</span><span class="{{ $ms->done_at ? 'line-through text-ink-secondary' : '' }}">{{ $ms->title }}</span></button></form>
                            @else
                                <span class="{{ $ms->done_at ? 'line-through text-ink-secondary' : '' }}">{{ $ms->title }}</span>
                            @endcan
                            <span class="text-small text-ink-secondary">{{ $ms->due_date ? \App\Services\Calendar::formatDate($ms->due_date) : '' }}</span>
                            @can('update', $mission)
                                <x-confirm :action="route('org.missions.milestones.destroy', [$mission, $ms])" method="DELETE" :message="'Remove milestone '.$ms->title.'?'" label="Remove" btn="btn btn-sm btn-ghost" />
                            @endcan
                        </div>
                    @endforeach
                </div>
                @can('update', $mission)
                    @if ($mission->milestones->count() < 10)
                        <form method="POST" action="{{ route('org.missions.milestones.store', $mission) }}" class="mt-3 grid gap-3 sm:grid-cols-[1fr_180px_auto] sm:items-end" novalidate>
                            @csrf
                            <x-field name="title" label="New milestone" maxlength="160" />
                            <x-field name="due_date" label="Due" type="date" :min="$mission->quarter_start->toDateString()" :max="$end->toDateString()" />
                            <button class="btn">Add milestone</button>
                        </form>
                    @endif
                @endcan
            </section>

            <section>
                <h2 class="heading mb-2">Tasks</h2>
                @foreach ($tasks as $t)
                    <div class="flex min-h-10 items-center justify-between border-b border-line text-small"><a class="link" href="{{ route('org.tasks.show', $t) }}">{{ $t->title }}</a><span class="text-ink-secondary">{{ $t->responsible->name }}, {{ ucfirst($t->status) }}</span></div>
                @endforeach
                @if ($mission->isOpen())
                    <x-quick-task source-type="mission" :source-id="$mission->id" :team="$mission->team_seat_id" heading="Create task" class="mt-4" />
                    @if ($mission->milestones->whereNull('done_at')->isNotEmpty())
                        <p class="help mt-2">To link a task to a milestone, create it from that milestone below.</p>
                        @foreach ($mission->milestones->whereNull('done_at') as $ms)
                            <details class="mt-2"><summary class="cursor-pointer text-small">Create task for "{{ $ms->title }}"</summary><x-quick-task source-type="milestone" :source-id="$ms->id" :team="$mission->team_seat_id" heading="Task for this milestone" class="mt-2" /></details>
                        @endforeach
                    @endif
                @endif
            </section>

            @can('update', $mission)
                <section>
                    <h2 class="heading mb-3">Edit</h2>
                    <form method="POST" action="{{ route('org.missions.update', $mission) }}" class="max-w-xl space-y-4" novalidate>
                        @csrf @method('PUT')
                        <x-field name="title" label="Title" :value="$mission->title" maxlength="160" />
                        <x-field name="done_definition" label="Definition of done" type="textarea" rows="3" :value="$mission->done_definition" maxlength="500" />
                        <button class="btn">Save changes</button>
                    </form>
                </section>
            @endcan
        </div>
        <aside class="box order-first lg:order-none lg:sticky lg:top-10 lg:self-start text-small">
            <dl class="rows">
                <div class="flex justify-between py-2"><dt class="text-ink-secondary">Level</dt><dd>{{ ucfirst($mission->level) }}</dd></div>
                <div class="flex justify-between py-2"><dt class="text-ink-secondary">Owner</dt><dd>{{ $mission->owner->name }}</dd></div>
                @if ($mission->team_seat_id)<div class="flex justify-between py-2"><dt class="text-ink-secondary">Team</dt><dd>{{ $teams[$mission->team_seat_id] ?? '' }}</dd></div>@endif
                <div class="flex justify-between py-2"><dt class="text-ink-secondary">Quarter</dt><dd>{{ $cal->quarterLabel($mission->quarter_start) }}</dd></div>
                <div class="flex justify-between py-2"><dt class="text-ink-secondary">Quarter ends</dt><dd>{{ \App\Services\Calendar::formatDate($end) }}</dd></div>
                @if ($mission->parent)<div class="py-2"><dt class="text-ink-secondary">Supports</dt><dd><a class="link" href="{{ route('org.missions.show', $mission->parent) }}">{{ $mission->parent->title }}</a></dd></div>@endif
                @if ($carried)<div class="py-2"><dt class="text-ink-secondary">Carried from</dt><dd><a class="link" href="{{ route('org.missions.show', $carried) }}">{{ $cal->quarterLabel($carried->quarter_start) }}</a></dd></div>@endif
            </dl>
            @if ($mission->children->isNotEmpty())
                <h3 class="subheading mt-6 mb-2">Linked Missions</h3>
                @foreach ($mission->children as $c)<p class="py-1"><a class="link" href="{{ route('org.missions.show', $c) }}">{{ $c->title }}</a>, {{ $c->owner->name }}</p>@endforeach
            @endif
            @can('delete', $mission)<x-confirm :action="route('org.missions.destroy', $mission)" method="DELETE" :message="'Delete '.$mission->title.'?'" label="Delete Mission" class="mt-6 block" />@endcan
        </aside>
    </div>
</x-app>
