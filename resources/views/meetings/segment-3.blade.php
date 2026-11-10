@if ($participant)
    <form method="POST" action="{{ route('org.meetings.headline', $meeting) }}" class="mb-6 grid gap-3 sm:grid-cols-[160px_1fr_auto] sm:items-end" novalidate>
        @csrf
        <x-field name="kind" label="About" type="select" :options="['customer' => 'Customers', 'employee' => 'People']" />
        <x-field name="text" label="Headline" maxlength="280" />
        <button class="btn">Add headline</button>
    </form>
@endif
@foreach ($shoutouts as $s)
    <div class="border-b border-line py-3"><span class="badge">Belief Shoutout</span> <span class="whitespace-pre-line">{{ \Illuminate\Support\Str::limit($s->notes, 280) }}</span></div>
@endforeach
@forelse ($headlines as $h)
    <div class="flex flex-wrap items-center gap-3 border-b border-line py-3">
        <span class="badge">{{ $h->kind === 'customer' ? 'Customers' : 'People' }}</span>
        <span class="flex-1">{{ $h->text }}</span>
        <span class="text-small text-ink-secondary">{{ $h->member->name }}</span>
        @if ($participant)<form method="POST" action="{{ route('org.meetings.drop', $meeting) }}">@csrf<input type="hidden" name="type" value="headline"><input type="hidden" name="id" value="{{ $h->id }}"><button class="btn btn-sm">Make it a problem</button></form>@endif
    </div>
@empty
    @if ($shoutouts->isEmpty())<p class="text-ink-secondary">No headlines yet.</p>@endif
@endforelse
