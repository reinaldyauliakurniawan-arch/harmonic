@if ($open)
    @php
        $p = $open; $tasks = $openTasks; $canSolve = auth()->user()->can('solve', $p);
        $procedures = \App\Models\Procedure::whereNull('archived_at')->whereNotNull('published_at')->pluck('title', 'id');
    @endphp
    <a class="link mb-4 inline-block" href="{{ route('org.meetings.run', $event) }}">Back to the queue</a>
    <h3 class="heading mb-2">{{ $p->title }}</h3>
    @include('problems.solve', ['meetingId' => $meeting->id])
@else
    <h3 class="subheading mb-2">Short-term queue</h3>
    @forelse ($queue as $p)
        <div class="flex flex-wrap items-center gap-3 border-b border-line py-3">
            <span class="w-6 text-center font-semibold {{ $p->rank ? 'text-brass' : 'text-ink-muted' }}">{{ $p->rank ?? '–' }}</span>
            <span class="flex-1 font-medium">{{ $p->title }}</span>
            <span class="text-small text-ink-secondary">{{ $p->owner?->name ?? 'No owner' }}</span>
            <a class="btn btn-sm" href="{{ route('org.meetings.run', ['event' => $event, 'problem' => $p->id]) }}">Solve</a>
        </div>
    @empty
        <p class="text-ink-secondary">The short-term queue is empty.</p>
    @endforelse
    @if ($evaluate->isNotEmpty())
        <h3 class="subheading mb-2 mt-8">To evaluate</h3>
        @foreach ($evaluate as $p)
            <div class="flex items-center gap-3 border-b border-line py-3"><span class="flex-1">{{ $p->title }}</span><a class="btn btn-sm" href="{{ route('org.meetings.run', ['event' => $event, 'problem' => $p->id]) }}">Evaluate</a></div>
        @endforeach
    @endif
@endif
