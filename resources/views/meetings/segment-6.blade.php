<section class="mb-8">
    <h3 class="subheading mb-2">Tasks created today</h3>
    @forelse ($created as $t)
        <div class="flex min-h-10 items-center justify-between border-b border-line text-small"><a class="link" href="{{ route('org.tasks.show', $t) }}">{{ $t->title }}</a><span class="text-ink-secondary">{{ $t->responsible->name }}, due {{ $t->due_date->format('j M') }}</span></div>
    @empty
        <p class="text-ink-secondary">No tasks created in this meeting.</p>
    @endforelse
</section>
<section class="mb-8 max-w-xl">
    <form method="POST" action="{{ route('org.meetings.notes', $meeting) }}" class="space-y-3" novalidate>
        @csrf @method('PUT')
        <x-field name="cascade_message" label="Message to cascade" type="textarea" rows="3" :value="$meeting->cascade_message" help="What every team member passes on to their own people." maxlength="2000" />
        @if ($participant)<button class="btn">Save message</button>@endif
    </form>
</section>
<section class="max-w-xl">
    <h3 class="subheading mb-2">Rate this meeting</h3>
    @if ($participant && ($attendance[$me] ?? false))
        <form method="POST" action="{{ route('org.meetings.rate', $meeting) }}" class="flex flex-wrap gap-1">
            @csrf
            @foreach (range(1, 10) as $s)<button name="score" value="{{ $s }}" class="btn btn-sm {{ ($ratings[$me] ?? null) == $s ? 'btn-primary' : '' }}" aria-pressed="{{ ($ratings[$me] ?? null) == $s ? 'true' : 'false' }}">{{ $s }}</button>@endforeach
        </form>
    @endif
    <p class="mt-2 text-small text-ink-secondary">{{ count($ratings) }} of {{ collect($attendance)->filter()->count() }} present have rated.@if (count($ratings)) Average {{ number_format(collect($ratings)->avg(), 1) }}.@endif</p>
</section>
