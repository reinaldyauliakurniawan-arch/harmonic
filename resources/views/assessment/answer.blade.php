<x-app title="Assessment">
    <x-page-header :title="($assessor->is_self ? 'Yourself' : $a->subject->name).', '.$a->type->name" description="Rate each item from 1.00 to 5.00. Pick a level, or type a value in steps of 0.25. Answers are final once sent." />
    <x-errors />
    <dl class="mb-6 grid max-w-3xl gap-1 text-small sm:grid-cols-5">@foreach ($levels as $l)<div><dt class="font-medium">{{ $l->score }}. {{ $l->label }}</dt><dd class="text-ink-secondary">{{ $l->description }}</dd></div>@endforeach</dl>
    <form method="POST" action="{{ route('org.assessment.answer.store', $assessor) }}" class="max-w-3xl space-y-4" novalidate>
        @csrf
        @foreach ($items as $i)
            <div class="grid gap-2 border-b border-line py-3 sm:grid-cols-[1fr_140px] sm:items-start">
                <div>
                    <label for="s-{{ $i->id }}">{{ $i->text }}</label>
                    @if ($i->levels)
                        <ol class="mt-2 space-y-1 text-small text-ink-secondary" aria-label="What each level looks like">
                            @foreach ($i->levels as $n => $text)<li class="grid grid-cols-[16px_1fr] gap-2"><span class="font-medium text-ink">{{ $n + 1 }}</span><span>{{ $text }}</span></li>@endforeach
                        </ol>
                    @endif
                </div>
                <div>
                    <input id="s-{{ $i->id }}" name="scores[{{ $i->id }}]" type="number" min="1" max="5" step="0.25" class="input" list="levels" value="{{ old('scores.'.$i->id) }}" @error('scores.'.$i->id) aria-invalid="true" @enderror>
                    @error('scores.'.$i->id)<p class="error">{{ $message }}</p>@enderror
                </div>
            </div>
        @endforeach
        <datalist id="levels">@foreach ($levels as $l)<option value="{{ $l->score }}">{{ $l->label }}</option>@endforeach</datalist>
        <button class="btn btn-primary">Send answers</button>
    </form>
</x-app>
