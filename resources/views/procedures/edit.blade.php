@php
    $seats = collect($f->teamSeatIds($team))->mapWithKeys(fn ($id) => [$id => $f->seat($id)->title])->all();
@endphp
<x-app :title="$p ? 'Edit '.$p->title : 'New procedure'">
    <x-page-header :title="$p ? $p->title : 'New procedure'" :description="'Owned by '.($f->seat($team)?->teamLabel() ?? 'a team').($p ? ($p->version ? '. Published v'.$p->version.'; changes here are a draft until you publish.' : '. Not published yet.') : '.')" />
    <x-errors />
    <form method="POST" action="{{ $p ? route('org.procedures.update', $p) : route('org.procedures.store') }}" class="grid max-w-4xl gap-8" novalidate>
        @csrf
        @if ($p) @method('PUT') @else <input type="hidden" name="team_seat_id" value="{{ $team }}"> @endif
        <section class="grid gap-4 sm:grid-cols-2">
            <h2 class="heading sm:col-span-2">1. Identify</h2>
            <x-field name="title" label="Title" :value="$p?->title" maxlength="160" class="sm:col-span-2" />
            <x-field name="purpose" label="Purpose" type="textarea" rows="2" :value="$p?->purpose" maxlength="500" class="sm:col-span-2" />
            <x-field name="owner_seat_id" label="Owner seat" type="select" :options="$seats" :value="$p?->owner_seat_id" />
            <x-field name="audience" label="Who follows it" type="select" :options="['team' => 'This team and every team beneath it', 'organization' => 'The whole organization']" :value="$p?->audience ?? 'team'" />
            <x-field name="review_every_months" label="Review every" type="select" :options="[3 => '3 months', 6 => '6 months', 12 => '12 months']" :value="$p?->review_every_months ?? 12" />
        </section>
        @if ($p)
            <section class="grid gap-4 lg:grid-cols-2" x-data="mdPreview" data-url="{{ route('org.procedures.preview', $p) }}">
                <h2 class="heading lg:col-span-2">2. Document and simplify</h2>
                <div>
                    <label for="body" class="field-label">Steps (Markdown: headings, lists, links)</label>
                    <textarea id="body" name="body" rows="18" class="input font-[inherit]" @input.debounce.600ms="render" maxlength="60000">{{ old('body', $p->body) }}</textarea>
                    <p class="help"><span x-text="words"></span> words</p>
                    <p class="help" x-show="long" x-cloak>Capture the 20% of steps that produce 80% of the result.</p>
                </div>
                <div>
                    <p class="field-label">3. Package and roll out: preview as members will see it</p>
                    <div class="prose-c panel min-h-40 p-4" x-ref="out">{!! \App\Services\Procedures::render($p->body) !!}</div>
                </div>
            </section>
        @endif
        <div class="flex flex-wrap gap-2 border-t border-line pt-6">
            <button class="btn">{{ $p ? 'Save draft' : 'Save and continue' }}</button>
            @if ($p)<button class="btn btn-primary" name="publish" value="1">Publish v{{ $p->version + 1 }}</button>@endif
            @if ($p)<a class="btn btn-ghost" href="{{ route('org.procedures.show', $p) }}">Cancel</a>@endif
        </div>
    </form>
</x-app>
