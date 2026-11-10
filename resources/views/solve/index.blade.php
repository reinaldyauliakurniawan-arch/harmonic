<x-app title="I-S.O.L.V.E">
    <x-page-header title="I-S.O.L.V.E" description="Problems being solved now, and solved ones due for a check that the fix worked." />
    <section class="mb-10">
        <h2 class="heading mb-2">Solving</h2>
        @forelse ($solving as $p)
            <div class="flex flex-wrap items-center gap-3 border-b border-line py-3"><a class="flex-1 font-medium hover:underline" href="{{ route('org.problems.show', $p) }}">{{ $p->title }}</a><span class="text-small text-ink-secondary">{{ $teams[$p->team_seat_id] ?? '' }}, {{ $p->solve?->mode === 'full' ? 'Full, step '.$p->solve->step : 'Quick' }}</span></div>
        @empty
            <p class="text-ink-secondary">Nothing is being solved. Start from a problem in the Problem Queue.</p>
        @endforelse
    </section>
    <section>
        <h2 class="heading mb-2">To evaluate</h2>
        @forelse ($evaluate as $p)
            <div class="flex flex-wrap items-center gap-3 border-b border-line py-3"><a class="flex-1 font-medium hover:underline" href="{{ route('org.problems.show', $p) }}">{{ $p->title }}</a><span class="text-small text-ink-secondary">Check due {{ \App\Services\Calendar::formatDate($p->solve->evaluate_on) }}</span></div>
        @empty
            <p class="text-ink-secondary">No checks due.</p>
        @endforelse
    </section>
</x-app>
