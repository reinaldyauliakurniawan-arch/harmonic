<x-app title="Leadership Assessment 360">
    <x-page-header title="Leadership Assessment 360" description="Leaders are rated by themselves and by people around them. Results are averages; no one sees who gave which score.">
        <x-slot:actions>@if ($canLead)<a class="btn" href="{{ route('org.assessment.setup') }}">Types and items</a>@endif</x-slot:actions>
    </x-page-header>
    <x-errors />
    @if ($todo->isNotEmpty())
        <section class="mb-10">
            <h2 class="heading mb-2">Assessments to complete</h2>
            @foreach ($todo as $x)
                <a class="flex min-h-11 items-center justify-between border-b border-line hover:bg-hover" href="{{ route('org.assessment.answer', $x) }}"><span>{{ $x->is_self ? 'Yourself' : $x->assignment->subject->name }}, {{ $x->assignment->type->name }}</span><span class="text-small text-ink-secondary">Closes {{ \App\Services\Calendar::formatDate($x->assignment->cycle->closes_on) }}</span></a>
            @endforeach
        </section>
    @endif
    @if ($canLead)
        <section class="mb-10">
            <h2 class="heading mb-2">New cycle</h2>
            <form method="POST" action="{{ route('org.assessment.cycles.store') }}" class="grid max-w-3xl gap-3 sm:grid-cols-[1fr_160px_160px_auto] sm:items-end" novalidate>
                @csrf
                <x-field name="name" label="Name" maxlength="120" />
                <x-field name="opens_on" label="Opens on" type="date" />
                <x-field name="closes_on" label="Closes on" type="date" />
                <button class="btn btn-primary">Create cycle</button>
            </form>
        </section>
    @endif
    <section class="mb-10">
        <h2 class="heading mb-2">Cycles</h2>
        @forelse ($cycles as $c)
            <a class="flex min-h-11 items-center justify-between border-b border-line hover:bg-hover" href="{{ route('org.assessment.cycle', $c) }}"><span>{{ $c->name }}</span><span class="text-small text-ink-secondary">{{ ucfirst($c->status) }}, {{ \App\Services\Calendar::formatDate($c->opens_on) }} – {{ \App\Services\Calendar::formatDate($c->closes_on) }}, {{ $c->assignments_count }} assignments</span></a>
        @empty
            <p class="text-ink-secondary">No cycles yet.</p>
        @endforelse
    </section>
    @if ($people->isNotEmpty())
        <section>
            <h2 class="heading mb-2">Results you can see</h2>
            <div class="flex flex-wrap gap-2">@foreach ($people as $m)<a class="btn btn-sm" href="{{ route('org.assessment.person', ['membership' => $m->id]) }}">{{ $m->name }}</a>@endforeach</div>
        </section>
    @endif
</x-app>
