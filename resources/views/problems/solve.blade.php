@php
    $rec = $p->solve;
    $steps = \App\Http\Controllers\Org\SolveController::STEPS;
    $hidden = $meetingId ? '<input type="hidden" name="meeting_id" value="'.$meetingId.'">' : '';
    $current = (int) request()->query('step', $rec?->step ?? 1);
@endphp
<section>
    <h2 class="heading mb-3">I-S.O.L.V.E</h2>
    @if ($p->status === 'open')
        @if ($canSolve)
            <p class="mb-3 text-ink-secondary">Quick mode is for problems the team can settle in minutes. Full mode is for anything with a real cause to find.</p>
            <div class="flex gap-2">
                @foreach (['quick' => 'Solve quickly', 'full' => 'Solve in full'] as $mode => $label)
                    <form method="POST" action="{{ route('org.solve.start', $p) }}">@csrf {!! $hidden !!}<input type="hidden" name="mode" value="{{ $mode }}"><button class="btn {{ $mode === 'quick' ? 'btn-primary' : '' }}">{{ $label }}</button></form>
                @endforeach
            </div>
        @else
            <p class="text-ink-secondary">Members of this team solve it.</p>
        @endif
    @elseif ($rec && $rec->mode === 'quick' && $p->status === 'solving')
        <form method="POST" action="{{ route('org.solve.quick', $p) }}" class="space-y-4" novalidate>
            @csrf {!! $hidden !!}
            <x-field name="statement" label="State" type="textarea" rows="2" :value="$rec->statement" help="Say the problem in one sentence. Not the symptom, not the fix." maxlength="280" />
            <x-field name="solution" label="Solution" type="textarea" rows="3" :value="$rec->solution" maxlength="2000" />
            <div class="flex flex-wrap gap-2">
                <button class="btn" name="solved" value="0">Save</button>
                <button class="btn btn-primary" name="solved" value="1">Mark solved</button>
            </div>
        </form>
        <form method="POST" action="{{ route('org.solve.upgrade', $p) }}" class="mt-3">@csrf {!! $hidden !!}<button class="btn btn-sm btn-ghost">Switch to full mode</button></form>
        <div class="mt-6">@include('problems.solve-tasks', ['strict' => false])</div>
    @elseif ($rec && $rec->mode === 'full')
        <div class="grid gap-6 md:grid-cols-[180px_1fr]">
            <ol class="space-y-1">
                @foreach ($steps as $n => [$name])
                    @php $done = $n < $rec->step || ($p->status === 'solved' && $n <= 5); $reach = $n <= $rec->step; @endphp
                    <li>
                        @if ($reach)<a href="{{ request()->fullUrlWithQuery(['step' => $n]) }}" class="flex min-h-10 items-center gap-2 {{ $n === $current ? 'font-semibold text-brass' : '' }}" @if ($n === $current) aria-current="step" @endif>@else<span class="flex min-h-10 items-center gap-2 text-ink-muted">@endif
                            <span class="w-5">{{ $n }}.</span>{{ $name }}@if ($done)<x-icon name="check" :size="14" class="cell-on" label="Done" />@endif
                        @if ($reach)</a>@else</span>@endif
                    </li>
                @endforeach
            </ol>
            <div>
                @php [$name, $field, $prompt] = $steps[min(max($current, 1), 6)]; @endphp
                <h3 class="subheading">{{ $current }}. {{ $name }}</h3>
                <p class="mb-3 text-ink-secondary">{{ $prompt }}</p>
                @if ($current === 6)
                    @if ($p->status !== 'solved')
                        <p class="text-ink-secondary">Finish step 5 first.</p>
                    @else
                        <form method="POST" action="{{ route('org.solve.step', [$p, 6]) }}" class="space-y-4" novalidate>
                            @csrf {!! $hidden !!}
                            <x-field name="evaluate_on" label="Check on" type="date" :value="$rec->evaluate_on?->toDateString()" />
                            <x-field name="evaluation_result" label="Result" type="select" :options="['' => 'Not checked yet', 'worked' => 'Worked', 'partly' => 'Partly', 'failed' => 'Failed']" :value="$rec->evaluation_result" />
                            <x-field name="evaluation" label="What happened" type="textarea" rows="3" :value="$rec->evaluation" maxlength="2000" />
                            <button class="btn btn-primary">Save</button>
                        </form>
                    @endif
                @elseif ($p->status === 'solved')
                    <p class="whitespace-pre-line">{{ $rec->{$field} ?: '–' }}</p>
                @else
                    <form method="POST" action="{{ route('org.solve.step', [$p, $current]) }}" class="space-y-4" novalidate>
                        @csrf {!! $hidden !!}
                        <x-field :name="$field" :label="$name" type="textarea" rows="4" :value="$rec->{$field}" :maxlength="[1 => 500, 2 => 280][$current] ?? 2000" />
                        @if ($current === 4 && $procedures->isNotEmpty())
                            <p class="help">Procedures you can point to: @foreach ($procedures as $pid => $pt)<a class="link" href="{{ route('org.procedures.show', $pid) }}">{{ $pt }}</a>@if (! $loop->last), @endif @endforeach</p>
                        @endif
                        <button class="btn btn-primary">{{ $current === 5 ? 'Finish and mark solved' : 'Next' }}</button>
                    </form>
                    @if ($current === 5)<div class="mt-6">@include('problems.solve-tasks', ['strict' => true])</div>@endif
                @endif
            </div>
        </div>
    @endif

    @if ($p->status === 'solved' && $rec)
        @if ($rec->mode === 'quick')
            <dl class="mt-2 space-y-2"><dt class="label">Statement</dt><dd>{{ $rec->statement }}</dd><dt class="label">Solution</dt><dd class="whitespace-pre-line">{{ $rec->solution ?: '–' }}</dd></dl>
            @if ($canSolve && ! $rec->evaluation_result)
                <form method="POST" action="{{ route('org.solve.evaluate', $p) }}" class="mt-6 max-w-xl space-y-4" novalidate>
                    @csrf {!! $hidden !!}
                    <h3 class="subheading">Did it work?</h3>
                    <x-field name="evaluation_result" label="Result" type="select" :options="['worked' => 'Worked', 'partly' => 'Partly', 'failed' => 'Failed']" />
                    <x-field name="evaluation" label="What happened" type="textarea" rows="2" maxlength="2000" />
                    <button class="btn">Save evaluation</button>
                </form>
            @endif
        @endif
        @if ($rec->evaluation_result)
            <p class="mt-4"><span class="label">Evaluation:</span> {{ ucfirst($rec->evaluation_result) }}. {{ $rec->evaluation }}</p>
            @if ($rec->evaluation_result === 'failed' && $canSolve)
                <form method="POST" action="{{ route('org.solve.again', $p) }}" class="mt-2">@csrf<button class="btn">Raise it again</button></form>
            @endif
        @endif
    @endif

    @if ($tasks->isNotEmpty())
        <h3 class="subheading mb-2 mt-8">Tasks</h3>
        @foreach ($tasks as $t)
            <div class="flex min-h-10 items-center justify-between border-b border-line text-small"><a class="link" href="{{ route('org.tasks.show', $t) }}">{{ $t->title }}</a><span class="text-ink-secondary">{{ $t->responsible->name }}, {{ ucfirst($t->status) }}</span></div>
        @endforeach
    @endif
</section>
