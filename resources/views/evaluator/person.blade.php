<x-app :title="$subject->name">
    <x-page-header :title="'Evaluations of '.$subject->name" :description="$bar" />
    @if ($evals->isEmpty())
        <x-empty heading="No evaluations yet" text="A leader above this person's seat records evaluations." />
    @else
        @foreach ($evals as $e)
            <section class="mb-6 border-b border-line pb-6">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>@include('evaluator.result')<p class="text-small text-ink-secondary">{{ \App\Services\Calendar::formatDate($e->evaluated_on) }}, {{ $e->seat?->title ?? 'Removed seat' }}, by {{ $e->evaluator->name }}</p></div>
                    @can('update', $e)<a class="btn btn-sm" href="{{ route('org.evaluator.edit', $e) }}">Edit today</a>@endcan
                </div>
                <dl class="mt-3 grid gap-x-6 gap-y-1 text-small sm:grid-cols-2">
                    @foreach ($e->scores as $s)
                        @php $b = $beliefs[$s->belief_id] ?? null; @endphp
                        <div class="flex justify-between gap-3"><dt>{{ $b?->name }}@if ($b?->archived_at) <span class="badge">Archived belief</span>@endif</dt><dd class="{{ $s->score === 'minus' ? 'cell-off' : '' }}">{{ \App\Http\Controllers\Org\EvaluatorController::LABELS[$s->score] }}</dd></div>
                    @endforeach
                    <div class="flex justify-between"><dt>Understands the seat</dt><dd>{{ $e->understands ? 'Yes' : 'No' }}</dd></div>
                    <div class="flex justify-between"><dt>Wants the seat</dt><dd>{{ $e->wants ? 'Yes' : 'No' }}</dd></div>
                    <div class="flex justify-between"><dt>Can do the seat</dt><dd>{{ $e->capacity ? 'Yes' : 'No' }}</dd></div>
                </dl>
                @if ($e->notes)<p class="mt-2 text-small text-ink-secondary">{{ $e->notes }}</p>@endif
                @php $r = \App\Http\Controllers\Org\EvaluatorController::result($e->scores->pluck('score')->all(), $e->understands, $e->wants, $e->capacity, $org); @endphp
                @if ((! $r['person'] || ! $r['seat']) && $e->evaluator_id === $me && $loop->first)
                    <div class="mt-4 flex flex-wrap items-start gap-2">
                        <a class="btn btn-sm" href="{{ route('org.calendar.index', ['new' => 1, 'type' => 'guide_checkin', 'partner_id' => $subject->id, 'title' => 'Quarterly Check-in with '.$subject->name]) }}">Schedule a Quarterly Check-in</a>
                        <details class="w-full">
                            <summary class="btn btn-sm list-none">Create task</summary>
                            <div class="panel mt-2 p-4"><x-quick-task :title="'Follow up on '.$subject->name.'\'s evaluation'" :responsible="$me" heading="Follow-up task" /></div>
                        </details>
                    </div>
                @endif
            </section>
        @endforeach
    @endif
</x-app>
