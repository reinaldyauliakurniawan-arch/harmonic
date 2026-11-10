@php
    $ms = $c['main_stage'] ?? [];
    $s3 = $c['snapshot_3y'] ?? [];
    $an = $c['annual'] ?? [];
    $pairs = fn ($rows, $a, $b) => collect($rows)->map(fn ($r) => $r[$a].($r[$b] !== '' ? ': '.$r[$b] : ''))->join(PHP_EOL);
    $missing = fn ($k) => $problems[$k] ?? [];
@endphp
<x-app width="reading" title="Edit the Fundamentals">
    <x-page-header title="Edit the Fundamentals" :description="'Draft of version '.$draft->version.'. Fields save when you leave them; publishing makes the version permanent.'">
        <x-slot:actions><a href="{{ route('org.fundamentals.show') }}" class="btn">View published</a></x-slot:actions>
    </x-page-header>
    <x-errors />
    @error('publish')<p class="banner banner-off mb-6" role="alert">{{ $message }}</p>@enderror

    <section class="mb-10 max-w-3xl">
        <h2 class="heading mb-1">Belief System</h2>
        <p class="help mb-3">3 to 7 beliefs. They are the rating criteria in the Personnel Evaluator.</p>
        @foreach ($missing('beliefs') as $m)<p class="error">{{ $m }}</p>@endforeach
        <div class="rows">
            @foreach ($beliefs as $b)
                <div class="grid gap-2 py-3 sm:grid-cols-[1fr_auto] sm:items-end">
                    <form method="POST" action="{{ route('org.beliefs.update', $b) }}" class="grid gap-2 sm:grid-cols-[200px_1fr_auto] sm:items-end">
                        @csrf @method('PUT')
                        <x-field name="name" label="Belief" :value="$b->name" maxlength="80" :id="'bn'.$b->id" />
                        <x-field name="description" label="What it looks like" :value="$b->description" maxlength="400" :id="'bd'.$b->id" />
                        <button class="btn btn-sm">Save</button>
                    </form>
                    <div class="flex gap-1">
                        <form method="POST" action="{{ route('org.beliefs.update', $b) }}">@csrf @method('PUT')<input type="hidden" name="move" value="up"><button class="btn btn-sm btn-ghost" aria-label="Move {{ $b->name }} up">Up</button></form>
                        <form method="POST" action="{{ route('org.beliefs.update', $b) }}">@csrf @method('PUT')<input type="hidden" name="move" value="down"><button class="btn btn-sm btn-ghost" aria-label="Move {{ $b->name }} down">Down</button></form>
                        <x-confirm :action="route('org.beliefs.update', $b)" method="PUT" :message="'Archive '.$b->name.'? Past evaluation scores keep it.'" label="Archive"><input type="hidden" name="archive" value="1"></x-confirm>
                    </div>
                </div>
            @endforeach
        </div>
        <form method="POST" action="{{ route('org.beliefs.store') }}" class="mt-3 grid gap-2 sm:grid-cols-[200px_1fr_auto] sm:items-end" novalidate>
            @csrf
            <x-field name="name" label="New belief" maxlength="80" />
            <x-field name="description" label="What it looks like" maxlength="400" />
            <button class="btn">Add belief</button>
        </form>
        @if ($archived->isNotEmpty())
            <details class="mt-3 text-small"><summary class="cursor-pointer text-ink-secondary">Archived beliefs ({{ $archived->count() }})</summary>
                @foreach ($archived as $b)<form method="POST" action="{{ route('org.beliefs.update', $b) }}" class="flex items-center justify-between py-1">@csrf @method('PUT')<input type="hidden" name="archive" value="0"><span>{{ $b->name }}</span><button class="btn btn-sm">Restore</button></form>@endforeach
            </details>
        @endif
    </section>

    <form method="POST" action="{{ route('org.fundamentals.save') }}" class="grid max-w-3xl gap-10" novalidate x-data="autosaveForm" @focusout="save">
        @csrf
        <section class="grid gap-4">
            <h2 class="heading">The Why & Way</h2>
            @foreach ($missing('why_way') as $m)<p class="error">{{ $m }}</p>@endforeach
            <x-field name="why" label="Why the organization exists" type="textarea" rows="2" :value="$c['why'] ?? ''" maxlength="300" />
            <x-field name="way" label="What it does better than anyone" type="textarea" rows="2" :value="$c['way'] ?? ''" maxlength="300" />
        </section>
        <section class="grid gap-4 sm:grid-cols-[1fr_160px]">
            <h2 class="heading sm:col-span-2">Long-Term Objective</h2>
            @foreach ($missing('objective') as $m)<p class="error sm:col-span-2">{{ $m }}</p>@endforeach
            <x-field name="objective_statement" label="Objective" :value="$c['objective']['statement'] ?? ''" maxlength="200" />
            <x-field name="objective_year" label="Target year" type="number" :value="$c['objective']['target_year'] ?? ''" />
        </section>
        <section class="grid gap-4">
            <h2 class="heading">The Main Stage</h2>
            @foreach ($missing('main_stage') as $m)<p class="error">{{ $m }}</p>@endforeach
            <x-field name="target_market" label="Target market" type="textarea" rows="3" :value="$ms['target_market'] ?? ''" maxlength="1000" />
            <div class="grid gap-3 sm:grid-cols-3">
                @foreach ([0, 1, 2] as $i)<x-field :name="'uniques['.$i.']'" :label="'Unique '.($i + 1)" :value="$ms['uniques'][$i] ?? ''" maxlength="120" />@endforeach
            </div>
            <x-field name="proven_process" label="Proven process" type="textarea" rows="5" :value="$pairs($ms['proven_process'] ?? [], 'title', 'description')" help="3 to 7 steps, one per line, as Title: description." />
            <x-field name="guarantee" label="Guarantee (optional)" :value="$ms['guarantee'] ?? ''" maxlength="300" />
        </section>
        <section class="grid gap-4 sm:grid-cols-3">
            <h2 class="heading sm:col-span-3">The 3-Year Snapshot</h2>
            @foreach ($missing('snapshot_3y') as $m)<p class="error sm:col-span-3">{{ $m }}</p>@endforeach
            <x-field name="snapshot_date" label="Target date" type="date" :value="$s3['target_date'] ?? ''" />
            <x-field name="snapshot_revenue" label="Revenue" :value="$s3['revenue'] ?? ''" />
            <x-field name="snapshot_profit" label="Profit" :value="$s3['profit'] ?? ''" />
            <x-field name="snapshot_measurables" label="Measurables" type="textarea" rows="3" :value="$pairs($s3['measurables'] ?? [], 'label', 'value')" help="Up to 5, as Label: value." class="sm:col-span-3" />
            <x-field name="picture" label="What it looks like" type="textarea" rows="6" :value="implode(PHP_EOL, $s3['picture'] ?? [])" help="3 to 15 lines in the present tense, one per line." class="sm:col-span-3" />
        </section>
        <section class="grid gap-4 sm:grid-cols-3">
            <h2 class="heading sm:col-span-3">Annual Priority</h2>
            @foreach ($missing('annual') as $m)<p class="error sm:col-span-3">{{ $m }}</p>@endforeach
            <x-field name="annual_date" label="Target date" type="date" :value="$an['target_date'] ?? ''" />
            <x-field name="annual_revenue" label="Revenue" :value="$an['revenue'] ?? ''" />
            <x-field name="annual_profit" label="Profit" :value="$an['profit'] ?? ''" />
            <x-field name="annual_measurables" label="Measurables" type="textarea" rows="3" :value="$pairs($an['measurables'] ?? [], 'label', 'value')" help="Up to 5, as Label: value." class="sm:col-span-3" />
            <x-field name="goals" label="Goals" type="textarea" rows="5" :value="implode(PHP_EOL, $an['goals'] ?? [])" help="3 to 7 goals, one per line." class="sm:col-span-3" />
        </section>
        <div class="flex flex-wrap items-center gap-3 border-t border-line pt-6">
            <button class="btn">Save draft</button>
            <button class="btn btn-primary" name="publish" value="1">Publish version {{ $draft->version }}</button>
            <span class="saved text-small" aria-live="polite" x-show="saved" x-cloak><x-icon name="check" :size="14" />Saved</span>
            <span class="error" aria-live="polite" x-show="failed" x-cloak>Couldn't save. Check your connection and try again.</span>
        </div>
    </form>
</x-app>
