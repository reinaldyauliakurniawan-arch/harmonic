@php $parts = \App\Services\Fundamentals::PARTS; @endphp
<x-app title="The Fundamentals">
    <x-page-header title="The Fundamentals" :description="$f ? 'Version '.$f->version.', published '.\App\Services\Calendar::formatDate($f->published_at).($latest && $f->version !== $latest->version ? '. A newer version exists.' : '.') : 'The score everyone plays from.'">
        <x-slot:actions>
            @if ($f)
                <button type="button" class="btn no-print" x-data="printPage" @click="print">Print this page</button>
                <a href="{{ route('org.fundamentals.history') }}" class="btn">History</a>
            @endif
            @if ($canEdit && $f)<a href="{{ route('org.fundamentals.edit') }}" class="btn btn-primary">Edit the draft</a>@endif
        </x-slot:actions>
    </x-page-header>
    @if (! $f)
        <x-empty heading="Write the Fundamentals" text="Six parts that set your direction: beliefs, why and way, a long-term objective, the main stage, a 3-year snapshot, and this year's priority.">
            @if ($canEdit)<a href="{{ route('org.fundamentals.edit') }}" class="btn btn-primary">Start writing</a>@endif
        </x-empty>
    @else
        <div class="no-print mb-6 border-b border-line">
            <a class="tab" href="{{ route('org.fundamentals.show', ['version' => request('version'), 'page' => 'vision']) }}" @if ($page === 'vision') aria-current="page" @endif>Vision</a>
            <a class="tab" href="{{ route('org.fundamentals.show', ['version' => request('version'), 'page' => 'plan']) }}" @if ($page === 'plan') aria-current="page" @endif>Plan</a>
        </div>
        <div class="print-page grid max-w-4xl gap-8">
            @foreach ($page === 'vision' ? ['beliefs', 'why_way', 'objective', 'main_stage', 'snapshot_3y'] : ['annual'] as $part)
                <section><h2 class="heading mb-2">{{ $parts[$part] }}</h2>@include('fundamentals.part', ['content' => $f->content, 'part' => $part])</section>
            @endforeach
            @if ($page === 'plan')
                <section>
                    <h2 class="heading mb-2">Company Missions this quarter</h2>
                    @forelse ($missions as $m)<p class="flex justify-between border-b border-line py-2"><a class="link" href="{{ route('org.missions.show', $m) }}">{{ $m->title }}</a><span class="text-small text-ink-secondary">{{ $m->owner->name }}</span></p>@empty<p class="text-ink-secondary">None set.</p>@endforelse
                </section>
                <section>
                    <h2 class="heading mb-2">Long-term company problems</h2>
                    @forelse ($longProblems as $p)<p class="border-b border-line py-2"><a class="link" href="{{ route('org.problems.show', $p) }}">{{ $p->title }}</a></p>@empty<p class="text-ink-secondary">None open.</p>@endforelse
                </section>
            @endif
        </div>
    @endif
</x-app>
