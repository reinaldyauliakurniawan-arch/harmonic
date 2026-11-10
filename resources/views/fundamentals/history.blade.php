<x-app title="Fundamentals history">
    <x-page-header title="Fundamentals history" description="Every published version, read-only. Compare two to see what changed." />
    @if ($versions->isEmpty())
        <x-empty heading="Nothing published yet" />
    @else
        <form method="GET" class="mb-6 flex flex-wrap items-end gap-3">
            <x-field name="a" label="Compare" type="select" :options="$versions->pluck('version', 'version')->map(fn ($v) => 'Version '.$v)->all()" :value="$a?->version ?? $versions->skip(1)->first()?->version" />
            <x-field name="b" label="With" type="select" :options="$versions->pluck('version', 'version')->map(fn ($v) => 'Version '.$v)->all()" :value="$b?->version ?? $versions->first()->version" />
            <button class="btn">Compare</button>
        </form>
        @if ($a && $b)
            <div class="mb-12 grid gap-8">
                @foreach (\App\Services\Fundamentals::PARTS as $k => $label)
                    @php $changed = json_encode(collect($a->content)->only($k === 'why_way' ? ['why', 'way'] : [$k])) !== json_encode(collect($b->content)->only($k === 'why_way' ? ['why', 'way'] : [$k])); @endphp
                    <section>
                        <h2 class="heading mb-2">{{ $label }} @if ($changed)<span class="badge">Changed</span>@endif</h2>
                        <div class="grid gap-6 md:grid-cols-2">
                            <div class="panel p-4"><p class="label mb-2 text-ink-secondary">Version {{ $a->version }}</p>@include('fundamentals.part', ['content' => $a->content, 'part' => $k])</div>
                            <div class="panel p-4"><p class="label mb-2 text-ink-secondary">Version {{ $b->version }}</p>@include('fundamentals.part', ['content' => $b->content, 'part' => $k])</div>
                        </div>
                    </section>
                @endforeach
            </div>
        @endif
        <div class="rows">
            @foreach ($versions as $v)
                <a href="{{ route('org.fundamentals.show', ['version' => $v->version]) }}" class="flex min-h-11 items-center justify-between hover:bg-hover"><span class="font-medium">Version {{ $v->version }}</span><span class="text-small text-ink-secondary">{{ $v->publisher?->name }}, {{ \App\Services\Calendar::formatDate($v->published_at) }}</span></a>
            @endforeach
        </div>
    @endif
</x-app>
