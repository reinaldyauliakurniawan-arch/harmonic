<x-app title="Company Diagnostic">
    <x-page-header title="Company Diagnostic" description="20 anonymous statements, each rated from 1 (weak) to 5 (strong). The total is the organization's score out of 100. Results appear after a cycle closes." />
    <x-errors />
    @if ($open)
        @if (! $answered)
            <section class="mb-10 max-w-3xl">
                <h2 class="heading mb-1">Your answers</h2>
                <p class="help mb-4">Open until {{ \App\Services\Calendar::formatDate($cal->local($open->closes_at)) }}. All required, final once sent. Your name is never stored with your answers.</p>
                <form method="POST" action="{{ route('org.diagnostic.answer', $open) }}" class="space-y-8" novalidate>
                    @csrf
                    @foreach ($cfg['sections'] as $sk => $sl)
                        <section>
                            <h3 class="subheading mb-2">{{ $sl }}</h3>
                            @foreach ($cfg['statements'] as $k => [$sec, $text])
                                @continue($sec !== $sk)
                                <fieldset class="border-b border-line py-3">
                                    <legend>{{ $text }}</legend>
                                    <div class="mt-2 flex flex-wrap gap-x-4">
                                        @foreach ($cfg['scale'] as $v => $l)<label class="flex min-h-11 items-center gap-2 text-small"><input type="radio" name="answers[{{ $k }}]" value="{{ $v }}" class="accent-brass" @checked(old("answers.$k") == $v)> {{ $l }}</label>@endforeach
                                    </div>
                                    @error("answers.$k")<p class="error">{{ $message }}</p>@enderror
                                </fieldset>
                            @endforeach
                        </section>
                    @endforeach
                    <button class="btn btn-primary">Send answers</button>
                </form>
            </section>
        @else
            <p class="banner mb-8">You've answered. {{ $openCount }} {{ $openCount === 1 ? 'person has' : 'people have' }} answered so far. Results appear when the cycle closes on {{ \App\Services\Calendar::formatDate($cal->local($open->closes_at)) }}.</p>
        @endif
    @elseif ($canLead)
        <form method="POST" action="{{ route('org.diagnostic.open') }}" class="mb-8 flex flex-wrap items-end gap-3" novalidate>
            @csrf
            <x-field name="days" label="Open for (days)" type="number" min="7" max="30" value="14" class="w-40" />
            <button class="btn btn-primary">Open a cycle</button>
            <p class="help w-full">Most organizations run one each quarter.</p>
        </form>
    @endif

    @if ($shown)
        <section class="mb-10 max-w-3xl">
            <h2 class="heading mb-1">Results, closed {{ \App\Services\Calendar::formatDate($shown->closed_at) }}</h2>
            @if ($closed->count() > 1)
                <form method="GET" class="filters my-3" x-data="autoSubmit" @change="submit"><x-filter name="cycle" label="Cycle" :options="$closed->mapWithKeys(fn ($c) => [$c->id => 'Closed '.$c->closed_at->format('j M Y')])->all()" :value="$shown->id" /><button class="btn btn-sm btn-ghost" data-apply>Apply</button></form>
            @endif
            <p class="text-ink-secondary">{{ $results['responses'] }} {{ $results['responses'] === 1 ? 'response' : 'responses' }}. Overall score <span class="font-medium text-ink">{{ $results['overall'] ?? '–' }}</span> of 100.</p>
            @if ($results['responses'] > 0 && $results['responses'] < 3)<p class="banner banner-off mt-3">Fewer than 3 people answered, so answers may be identifiable.</p>@endif
            <div class="mt-4 space-y-3">
                @foreach ($cfg['sections'] as $k => $l)
                    @php $v = $results['sections'][$k]; @endphp
                    <div class="grid grid-cols-[120px_1fr_40px] items-center gap-3">
                        <span class="text-small">{{ $l }}</span>
                        <div class="h-3 rounded-[4px] bg-hover" role="img" aria-label="{{ $l }}: {{ $v ?? 'no data' }} of 100"><div class="h-3 rounded-[4px] bg-ink {{ ['w-0', 'w-1/12', 'w-2/12', 'w-3/12', 'w-4/12', 'w-5/12', 'w-6/12', 'w-7/12', 'w-8/12', 'w-9/12', 'w-10/12', 'w-11/12', 'w-full'][(int) round(($v ?? 0) / 100 * 12)] }}"></div></div>
                        <span class="text-right text-small font-medium">{{ $v ?? '–' }}</span>
                    </div>
                @endforeach
            </div>
            @if ($results['gaps']->isNotEmpty())
                <h3 class="subheading mb-2 mt-6">Biggest gaps</h3>
                @foreach ($results['gaps'] as $g)
                    <div class="flex flex-wrap items-center gap-3 border-b border-line py-2">
                        <span class="flex-1">{{ $g['text'] }}</span><span class="text-small font-medium">{{ $g['score'] }}</span>
                        @if ($isLeadership)<form method="POST" action="{{ route('org.diagnostic.problem', $shown) }}">@csrf<input type="hidden" name="key" value="{{ $g['key'] }}"><button class="btn btn-sm">Raise as problem</button></form>@endif
                    </div>
                @endforeach
            @endif
            @if ($isLeadership)
                <form method="POST" action="{{ route('org.diagnostic.problem', $shown) }}" class="mt-4 flex items-end gap-2">@csrf<x-field name="key" label="Raise a section as a problem" type="select" :options="$cfg['sections']" /><button class="btn btn-sm">Raise</button></form>
            @endif
        </section>
        @if ($trend->count() > 1)
            <section>
                <h2 class="heading mb-2">Trend</h2>
                <x-line-chart :labels="$trend->pluck('label')->all()" :floor="0" :ceiling="100" summary="Overall and section scores across closed Diagnostic cycles."
                    :series="array_merge([['name' => 'Overall', 'values' => $trend->map(fn ($t) => $t['r']['overall'])->all()]], collect($cfg['sections'])->map(fn ($l, $k) => ['name' => $l, 'values' => $trend->map(fn ($t) => $t['r']['sections'][$k])->all()])->values()->all())" />
            </section>
        @endif
    @elseif (! $open)
        <x-empty heading="No results yet" text="Leadership or an Org Admin opens a cycle. Everyone answers anonymously; results show after it closes." />
    @endif
</x-app>
