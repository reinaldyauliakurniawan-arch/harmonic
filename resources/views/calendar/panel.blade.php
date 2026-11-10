@php $p = $panel; $canEdit = auth()->user()->can('update', $p); $canNotes = auth()->user()->can('notes', $p); @endphp
<div class="sidepanel fade-in" role="dialog" aria-modal="true" aria-labelledby="panel-title">
    <div class="mb-2 flex items-start justify-between gap-3">
        <div>
            <p class="label text-ink-secondary">{{ $typeNames[$p->type] ?? 'Event' }}</p>
            <h2 id="panel-title" class="heading">{{ $p->title }}</h2>
        </div>
        <a href="{{ route('org.calendar.index', $base + ['date' => $date->toDateString()]) }}" class="btn btn-ghost btn-sm" aria-label="Close"><x-icon name="x" /></a>
    </div>
    <p class="text-small text-ink-secondary">
        {{ \App\Services\Calendar::formatDate($cal->local($p->starts_at)) }}@unless ($p->all_day), {{ $cal->local($p->starts_at)->format('H:i') }}–{{ $cal->local($p->ends_at)->format('H:i') }}@endunless
    </p>
    <p class="mt-1 text-small text-ink-secondary">
        {{ ['org' => 'Organization', 'team' => $teams[$p->team_seat_id] ?? 'Team', 'pair' => 'Just the two of you', 'private' => 'Only you'][$p->scope] }}
       , Created by {{ $p->creator?->name }}@if ($p->partner), With {{ $p->partner->name }}@endif
    </p>
    @if ($p->type === 'weekly_meeting' && Route::has('org.meetings.run'))
        <a href="{{ route('org.meetings.run', $p) }}" class="btn btn-primary mt-4">Open meeting</a>
    @endif

    @if ($guide)
        <section class="mt-6">
            <h3 class="subheading">Guide, about {{ $guide['minutes'] }} minutes</h3>
            <ol class="mt-2 space-y-3">
                @foreach ($guide['steps'] as $i => [$title, $text])
                    <li class="grid grid-cols-[24px_1fr] gap-2"><span class="text-small font-medium text-ink-secondary">{{ $i + 1 }}.</span><div><p class="font-medium">{{ $title }}</p><p class="text-small text-ink-secondary">{{ $text }}</p></div></li>
                @endforeach
            </ol>
        </section>
    @endif

    <section class="mt-6">
        <label for="event-notes" class="subheading">Notes</label>
        @if ($canNotes)
            <div x-data="autosave" data-url="{{ route('org.calendar.notes', $p) }}" data-field="notes">
                <textarea id="event-notes" class="input mt-2" rows="6" maxlength="10000" @blur="save">{{ $p->notes }}</textarea>
                <p class="help" aria-live="polite"><span class="saved" x-show="saved" x-cloak><x-icon name="check" :size="14" />Saved</span><span x-show="failed" x-cloak class="error">Couldn't save. Check your connection and try again.</span></p>
            </div>
        @else
            <p class="mt-2 whitespace-pre-line text-small">{{ $p->notes ?: 'No notes yet.' }}</p>
        @endif
    </section>

    <section class="mt-6">
        @if ($panelTasks->isNotEmpty())
            <h3 class="subheading mb-2">Tasks from this event</h3>
            <ul class="rows mb-4">@foreach ($panelTasks as $t)<li class="flex min-h-10 items-center justify-between text-small"><a class="link" href="{{ route('org.tasks.show', $t) }}">{{ $t->title }}</a><span class="text-ink-secondary">{{ ucfirst($t->status) }}</span></li>@endforeach</ul>
        @endif
        <x-quick-task source-type="event" :source-id="$p->id" :team="$p->team_seat_id" heading="Create task" :note="in_array($p->scope, ['private', 'pair']) ? 'Tasks are visible to everyone in your organization.' : null" class="!grid-cols-1" />
    </section>

    @if ($canEdit)
        <section class="mt-8 border-t border-line pt-6">
            <h3 class="subheading mb-3">Edit event</h3>
            <form method="POST" action="{{ route('org.calendar.update', $p) }}" class="space-y-4" novalidate>
                @csrf @method('PUT')
                <x-field name="title" label="Title" :value="$p->title" required />
                <x-field name="date" label="Date" type="date" :value="$cal->local($p->starts_at)->toDateString()" />
                <div class="grid grid-cols-2 gap-3">
                    <x-field name="start_time" label="Starts" type="time" :value="$cal->local($p->starts_at)->format('H:i')" />
                    <x-field name="end_time" label="Ends" type="time" :value="$cal->local($p->ends_at)->format('H:i')" />
                </div>
                <label class="flex min-h-11 items-center gap-2"><input type="hidden" name="all_day" value="0"><input type="checkbox" name="all_day" value="1" class="h-4 w-4 accent-brass" @checked($p->all_day)> All day</label>
                @if ($p->series_id)
                    <fieldset><legend class="field-label">Apply to</legend>
                        <label class="flex min-h-10 items-center gap-2"><input type="radio" name="apply" value="this" checked class="accent-brass"> This event</label>
                        <label class="flex min-h-10 items-center gap-2"><input type="radio" name="apply" value="following" class="accent-brass"> This and following events</label>
                    </fieldset>
                @endif
                <button class="btn btn-primary">Save changes</button>
            </form>
            <form method="POST" action="{{ route('org.calendar.destroy', $p) }}" class="mt-4 flex flex-wrap items-center gap-3" x-data="confirmForm" data-message="Delete {{ $p->title }}?" @submit="ask">
                @csrf @method('DELETE')
                @if ($p->series_id)
                    <label class="flex items-center gap-2 text-small"><input type="radio" name="apply" value="this" checked class="accent-brass"> This event</label>
                    <label class="flex items-center gap-2 text-small"><input type="radio" name="apply" value="following" class="accent-brass"> This and following</label>
                @endif
                <button class="btn btn-sm btn-danger">Delete event</button>
            </form>
        </section>
    @endif
</div>
