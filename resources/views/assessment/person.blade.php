<x-app :title="'360 results: '.$subject->name">
    <x-page-header :title="'360 results: '.$subject->name" description="Scores from others are averages of every assessor except the person. Self scores are shown separately." />
    @if ($cycles->isEmpty())
        <x-empty heading="No closed cycles yet" :text="$open ? 'A cycle is running. Results appear after it closes.' : 'Results appear after a cycle that includes this person closes.'" />
    @else
        @foreach ($cycles->reverse() as $c)
            @php $r = $results[$c->id]; @endphp
            <section class="mb-10">
                <h2 class="heading">{{ $c->name }}</h2>
                <p class="mb-3 text-ink-secondary">Overall {{ $r['overall'] !== null ? number_format($r['overall'], 2) : '–' }}, Self {{ $r['self'] !== null ? number_format($r['self'], 2) : '–' }}</p>
                @foreach ($r['types'] as $t)
                    <h3 class="subheading mt-4">{{ $t['name'] }}, {{ $t['others'] !== null ? number_format($t['others'], 2) : '–' }}</h3>
                    <p class="text-small text-ink-secondary">{{ $t['assessors'] }} {{ $t['assessors'] === 1 ? 'assessor' : 'assessors' }} besides the person.</p>
                    @if ($t['assessors'] < 3)<p class="text-small cell-off">Fewer than 3 people answered, so answers may be identifiable.</p>@endif
                    <div class="relative overflow-x-auto"><table class="tbl mt-2">
                        <thead><tr><th>Item</th><th class="num">Others</th><th class="num">Self</th><th class="num">Gap</th></tr></thead>
                        <tbody>
                        @foreach ($t['items'] as $i)
                            @php $gap = $i['others'] !== null && $i['self'] !== null ? round($i['self'] - $i['others'], 2) : null; @endphp
                            <tr><td>{{ $i['text'] }}</td><td class="num">{{ $i['others'] !== null ? number_format($i['others'], 2) : '–' }}</td><td class="num">{{ $i['self'] !== null ? number_format($i['self'], 2) : '–' }}</td>
                                <td class="num {{ $gap !== null && abs($gap) >= 1 ? 'cell-off font-medium' : '' }}">{{ $gap !== null ? ($gap > 0 ? '+' : '').number_format($gap, 2) : '–' }}@if ($gap !== null && abs($gap) >= 1)<span class="sr-only"> large gap</span>@endif</td></tr>
                        @endforeach
                        </tbody>
                    </table></div>
                @endforeach
            </section>
        @endforeach
        @if ($cycles->count() > 1)
            <section>
                <h2 class="heading mb-2">Trend</h2>
                <x-line-chart :labels="$cycles->pluck('name')->all()" :floor="1" :ceiling="5" :summary="'Overall and per-type 360 scores for '.$subject->name.' across cycles.'"
                    :series="array_merge([['name' => 'Overall', 'values' => $cycles->map(fn ($c) => $results[$c->id]['overall'])->all()]], $typeNames->map(fn ($n) => ['name' => $n, 'values' => $cycles->map(fn ($c) => collect($results[$c->id]['types'])->firstWhere('name', $n)['others'] ?? null)->all()])->all())" />
            </section>
        @endif
    @endif
</x-app>
