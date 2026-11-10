@php $scores = $e ? $e->scores->pluck('score', 'belief_id') : collect(); @endphp
<x-app width="reading" :title="'Evaluate '.$subject->name">
    <x-page-header :title="'Evaluate '.$subject->name" :description="'As '.$seat->title.'. '.$bar" />
    <x-errors />
    <form method="POST" action="{{ $e ? route('org.evaluator.update', $e) : route('org.evaluator.store') }}" class="max-w-2xl space-y-8" novalidate
          x-data="evaluatorResult" data-mixed="{{ $org->evaluator_bar_max_mixed }}" data-minus="{{ $org->evaluator_bar_max_minus }}" @change="update">
        @csrf
        @if ($e) @method('PUT') @else <input type="hidden" name="seat_id" value="{{ $seat->id }}"> @endif
        <section>
            <h2 class="heading mb-3">Belief System</h2>
            @forelse ($beliefs as $b)
                <fieldset class="border-b border-line py-3">
                    <legend class="font-medium">{{ $b->name }}</legend>
                    @if ($b->description)<p class="text-small text-ink-secondary">{{ $b->description }}</p>@endif
                    <div class="mt-2 flex flex-wrap gap-4">
                        @foreach (\App\Http\Controllers\Org\EvaluatorController::LABELS as $v => $l)
                            <label class="flex min-h-11 items-center gap-2"><input type="radio" name="scores[{{ $b->id }}]" value="{{ $v }}" class="accent-brass" data-score @checked(old('scores.'.$b->id, $scores[$b->id] ?? null) === $v)> {{ $l }}</label>
                        @endforeach
                    </div>
                    @error('scores.'.$b->id)<p class="error">{{ $message }}</p>@enderror
                </fieldset>
            @empty
                <p class="text-ink-secondary">There are no beliefs yet. Leadership adds them in the Fundamentals.</p>
            @endforelse
        </section>
        <section>
            <h2 class="heading mb-3">Seat Fit Check</h2>
            @foreach (['understands' => 'Understands the seat', 'wants' => 'Wants the seat', 'capacity' => 'Can do the seat'] as $k => $l)
                <fieldset class="border-b border-line py-3">
                    <legend class="font-medium">{{ $l }}</legend>
                    <div class="mt-1 flex gap-4">
                        <label class="flex min-h-11 items-center gap-2"><input type="radio" name="{{ $k }}" value="1" class="accent-brass" data-fit @checked((string) old($k, $e ? (int) $e->$k : null) === '1')> Yes</label>
                        <label class="flex min-h-11 items-center gap-2"><input type="radio" name="{{ $k }}" value="0" class="accent-brass" data-fit @checked((string) old($k, $e ? (int) $e->$k : null) === '0')> No</label>
                    </div>
                    @error($k)<p class="error">{{ $message }}</p>@enderror
                </fieldset>
            @endforeach
        </section>
        <x-field name="notes" label="Notes (optional)" type="textarea" rows="3" :value="$e?->notes" maxlength="2000" />
        <p class="subheading" aria-live="polite" x-text="label"></p>
        <button class="btn btn-primary">{{ $e ? 'Save changes' : 'Save evaluation' }}</button>
    </form>
</x-app>
