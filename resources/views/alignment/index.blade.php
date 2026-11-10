@php $parts = \App\Services\Fundamentals::PARTS; @endphp
<x-app width="reading" title="Alignment Check">
    <x-page-header title="Alignment Check" description="Does everyone find each part of the Fundamentals clear, and commit to the direction? Answers are anonymous." />
    <x-errors />
    @if ($canLead && (! $round || ! $round->isOpen()))
        <form method="POST" action="{{ route('org.alignment.open') }}" class="mb-8 flex flex-wrap items-end gap-3" novalidate>
            @csrf
            <x-field name="days" label="Open for (days)" type="number" min="3" max="30" value="14" class="w-40" />
            <button class="btn btn-primary" @disabled(! $hasPublished)>Open a round</button>
            @unless ($hasPublished)<p class="help">Publish the Fundamentals first.</p>@endunless
        </form>
    @endif

    @if (! $round)
        <x-empty heading="No rounds yet" text="Leadership opens a round after publishing the Fundamentals. Every member answers once." />
    @else
        @if ($rounds->count() > 1)
            <form method="GET" class="filters mb-6" x-data="autoSubmit" @change="submit"><x-filter name="round" label="Round" :options="$rounds->mapWithKeys(fn ($r) => [$r->id => 'Opened '.$r->opened_at->format('j M Y').($r->isOpen() ? ' (open)' : '')])->all()" :value="$round->id" /><button class="btn btn-sm btn-ghost" data-apply>Apply</button></form>
        @endif

        @if ($round->isOpen() && ! $answered)
            <section class="mb-10 max-w-3xl">
                <h2 class="heading mb-1">Your answer</h2>
                <p class="help mb-4">Open until {{ \App\Services\Calendar::formatDate($cal->local($round->closes_at)) }}. One answer per person, final once sent. Your name is never stored with your answers.</p>
                <form method="POST" action="{{ route('org.alignment.answer', $round) }}" class="space-y-6" novalidate>
                    @csrf
                    @foreach ($parts as $k => $label)
                        <fieldset class="border-b border-line pb-4">
                            <legend class="subheading">{{ $label }}</legend>
                            <p class="mt-1 text-small">This is clear to me.</p>
                            <div class="mt-1 flex gap-4">
                                <label class="flex min-h-11 items-center gap-2"><input type="radio" name="clear[{{ $k }}]" value="1" class="accent-brass" @checked(old("clear.$k") === '1')> Yes</label>
                                <label class="flex min-h-11 items-center gap-2"><input type="radio" name="clear[{{ $k }}]" value="0" class="accent-brass" @checked(old("clear.$k") === '0')> No</label>
                            </div>
                            @error("clear.$k")<p class="error">{{ $message }}</p>@enderror
                            <x-field :name="'comment['.$k.']'" label="Comment (optional)" type="textarea" rows="2" maxlength="1000" />
                        </fieldset>
                    @endforeach
                    <fieldset>
                        <legend class="subheading">I'm committed to this direction.</legend>
                        <div class="mt-1 flex gap-4">
                            <label class="flex min-h-11 items-center gap-2"><input type="radio" name="committed" value="1" class="accent-brass"> Yes</label>
                            <label class="flex min-h-11 items-center gap-2"><input type="radio" name="committed" value="0" class="accent-brass"> No</label>
                        </div>
                        @error('committed')<p class="error">{{ $message }}</p>@enderror
                    </fieldset>
                    <button class="btn btn-primary">Send answer</button>
                </form>
            </section>
        @elseif ($round->isOpen())
            <p class="banner mb-8">You've answered this round. Results update as people answer.</p>
        @endif

        <section class="mb-10">
            <h2 class="heading mb-1">Results</h2>
            <p class="mb-3 text-ink-secondary">{{ $results['responses'] }} of {{ $results['members'] }} answered{{ $round->isOpen() ? ' so far' : '' }}.</p>
            @if ($results['responses'] > 0 && $results['responses'] < 3)<p class="banner banner-off mb-3">Fewer than 3 people answered, so answers may be identifiable.</p>@endif
            @if ($results['responses'])
                <table class="tbl max-w-xl">
                    <thead><tr><th>Part</th><th class="num">Clear</th></tr></thead>
                    <tbody>
                        @foreach ($parts as $k => $label)<tr><td>{{ $label }}</td><td class="num">{{ $results['clear'][$k] }}%</td></tr>@endforeach
                        <tr><td class="font-medium">Committed to the direction</td><td class="num font-medium">{{ $results['committed'] }}%</td></tr>
                    </tbody>
                </table>
            @endif
        </section>

        @if ($canLead)
            @if ($results['comments']->isNotEmpty())
                <section class="mb-10 max-w-3xl">
                    <h2 class="heading mb-2">Comments</h2>
                    <p class="help mb-3">Shown without names, in random order. Only leadership sees them.</p>
                    @foreach ($results['comments'] as $cm)
                        <div class="flex flex-wrap items-start gap-3 border-b border-line py-3">
                            <span class="badge">{{ $parts[$cm['part']] }}</span><p class="flex-1">{{ $cm['text'] }}</p>
                            <form method="POST" action="{{ route('org.alignment.problem', $round) }}">@csrf<input type="hidden" name="answer" value="{{ $cm['id'] }}"><button class="btn btn-sm">Make it a problem</button></form>
                        </div>
                    @endforeach
                </section>
            @endif
            @if ($completion)
                <section class="mb-10 grid max-w-3xl gap-6 sm:grid-cols-2">
                    <div><h2 class="subheading mb-2">Answered ({{ $completion['done']->count() }})</h2><p class="text-small text-ink-secondary">{{ $completion['done']->pluck('name')->join(', ') ?: '–' }}</p></div>
                    <div><h2 class="subheading mb-2">Not yet ({{ $completion['waiting']->count() }})</h2><p class="text-small text-ink-secondary">{{ $completion['waiting']->pluck('name')->join(', ') ?: '–' }}</p></div>
                </section>
            @endif
            @if ($round->isOpen())
                <div class="flex flex-wrap gap-2">
                    @unless ($round->reminded_at)<form method="POST" action="{{ route('org.alignment.remind', $round) }}">@csrf<button class="btn">Send reminder</button></form>@endunless
                    <x-confirm :action="route('org.alignment.close', $round)" message="Close this round now?" label="Close round" btn="btn" />
                </div>
            @endif
        @endif

        @if ($trend->count() > 1)
            <section class="mt-10">
                <h2 class="heading mb-2">Trend</h2>
                <x-line-chart :labels="$trend->pluck('label')->all()" :series="[['name' => 'Clear (average of six parts)', 'values' => $trend->map(fn ($t) => $t['res']['avg_clear'])->all()], ['name' => 'Committed', 'values' => $trend->map(fn ($t) => $t['res']['committed'])->all()]]" :floor="0" :ceiling="100" summary="Percent clear and percent committed across Alignment rounds." />
            </section>
        @endif
    @endif
</x-app>
