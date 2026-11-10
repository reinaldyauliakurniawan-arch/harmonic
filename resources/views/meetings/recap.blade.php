<section class="max-w-2xl space-y-6">
    <p class="banner">This meeting ended {{ \App\Services\Calendar::formatDate($cal->local($meeting->ended_at)) }} at {{ $cal->local($meeting->ended_at)->format('H:i') }} after {{ $recap['minutes'] }} minutes.</p>
    <dl class="grid grid-cols-2 gap-y-2">
        <dt class="text-ink-secondary">Average rating</dt><dd>{{ $recap['rating'] !== null ? number_format($recap['rating'], 1) : '–' }}</dd>
        <dt class="text-ink-secondary">Present</dt><dd>{{ collect($recap['present'])->map(fn ($id) => $names[$id]->name ?? '')->join(', ') ?: '–' }}</dd>
        <dt class="text-ink-secondary">Absent</dt><dd>{{ collect($recap['absent'])->map(fn ($id) => $names[$id]->name ?? '')->join(', ') ?: '–' }}</dd>
    </dl>
    <div><h2 class="subheading mb-1">Tasks created</h2>@forelse ($recap['tasks'] as $t)<p><a class="link" href="{{ route('org.tasks.show', $t) }}">{{ $t->title }}</a>, {{ $t->responsible->name }}</p>@empty<p class="text-ink-secondary">None.</p>@endforelse</div>
    <div><h2 class="subheading mb-1">Problems solved</h2>@forelse ($recap['solved'] as $p)<p><a class="link" href="{{ route('org.problems.show', $p) }}">{{ $p->title }}</a></p>@empty<p class="text-ink-secondary">None.</p>@endforelse</div>
    <div><h2 class="subheading mb-1">Cascade message</h2><p class="whitespace-pre-line">{{ $meeting->cascade_message ?: '–' }}</p></div>
    @can('editNotes', $meeting)
        <form method="POST" action="{{ route('org.meetings.notes', $meeting) }}" class="space-y-3" novalidate>
            @csrf @method('PUT')
            <x-field name="notes" label="Meeting notes" type="textarea" rows="5" :value="$meeting->notes" help="The team leader can edit notes after the meeting." maxlength="10000" />
            <button class="btn">Save notes</button>
        </form>
    @else
        <div><h2 class="subheading mb-1">Meeting notes</h2><p class="whitespace-pre-line">{{ $meeting->notes ?: '–' }}</p></div>
    @endcan
</section>
