@php $short = ['plus' => '+', 'mixed' => '±', 'minus' => '–']; @endphp
<x-app title="Personnel Evaluator" wide>
    <x-page-header title="Personnel Evaluator" description="Rate the people in seats below yours against the Belief System and the Seat Fit Check. Only they, people above them, and Org Admins see the results.">
        <x-slot:actions><a class="btn" href="{{ route('org.evaluator.person', ['membership' => $me]) }}">My evaluations</a></x-slot:actions>
    </x-page-header>
    <p class="mb-4 text-small">{{ $bar }}</p>
    @if ($rows->isEmpty() && ! $team)
        <x-empty heading="No one reports to you" text="Leaders see the people in seats below theirs here. You can still read your own evaluations." />
    @else
        <form method="GET" class="filters mb-4" x-data="autoSubmit" @change="submit"><x-filter name="team" label="Team" :options="['' => 'All my people'] + $teams" :value="$team" /><button class="btn btn-sm btn-ghost" data-apply>Apply</button></form>
        <div class="relative overflow-x-auto"><table class="tbl">
            <thead><tr>
                <th>Person and seat</th>
                @foreach ($beliefs as $b)<th class="text-center" title="{{ $b->description }}">{{ $b->name }}</th>@endforeach
                <th class="text-center">Understands</th><th class="text-center">Wants</th><th class="text-center">Can do</th><th>Result</th><th>Evaluated</th><th><span class="sr-only">Actions</span></th>
            </tr></thead>
            <tbody>
            @foreach ($rows as $seat)
                @php $e = $latest[$seat->holder_id.':'.$seat->id] ?? null; $scores = $e ? $e->scores->keyBy('belief_id') : collect(); @endphp
                <tr>
                    <td><a class="font-medium hover:underline" href="{{ route('org.evaluator.person', ['membership' => $seat->holder_id]) }}">{{ $seat->holder->name }}</a><div class="text-caption text-ink-secondary">{{ $seat->title }}</div></td>
                    @foreach ($beliefs as $b)
                        @php $sc = $scores[$b->id]->score ?? null; @endphp
                        <td class="text-center {{ $sc === 'minus' ? 'cell-off' : '' }}">@if ($sc)<span aria-label="{{ \App\Http\Controllers\Org\EvaluatorController::LABELS[$sc] }}" title="{{ \App\Http\Controllers\Org\EvaluatorController::LABELS[$sc] }}">{{ $short[$sc] }}</span>@else – @endif</td>
                    @endforeach
                    @foreach (['understands', 'wants', 'capacity'] as $k)<td class="text-center">{{ $e ? ($e->$k ? 'Yes' : 'No') : '–' }}</td>@endforeach
                    <td>@if ($e)@include('evaluator.result')@else<span class="text-ink-muted">Not evaluated</span>@endif</td>
                    <td class="whitespace-nowrap">{{ $e ? \App\Services\Calendar::formatDate($e->evaluated_on) : '–' }}</td>
                    <td><a class="btn btn-sm" href="{{ route('org.evaluator.create', ['seat' => $seat->id]) }}">Evaluate</a></td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
        <p class="help mt-2">+ Lives it, ± Sometimes, – Rarely</p>
    @endif
</x-app>
