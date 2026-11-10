<x-app :title="$p->title">
    <x-page-header :title="$p->title" :description="$p->purpose">
        <x-slot:actions>
            @can('update', $p)<a class="btn" href="{{ route('org.procedures.edit', $p) }}">Edit draft</a>@endcan
            @can('archive', $p)<x-confirm :action="route('org.procedures.archive', $p)" :message="($p->archived_at ? 'Restore ' : 'Archive ').$p->title.'?'" :label="$p->archived_at ? 'Restore' : 'Archive'" btn="btn" />@endcan
        </x-slot:actions>
    </x-page-header>
    <x-errors />
    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_300px] lg:items-start">
        <div class="box min-w-0">
            @if ($inAudience && $p->version && ! $acked)
                <form method="POST" action="{{ route('org.procedures.acknowledge', $p) }}" class="banner banner-brass mb-6 flex flex-wrap items-center justify-between gap-3">@csrf<span>Read v{{ $p->version }} below.</span><button class="btn btn-primary">I've read this and will follow it</button></form>
            @endif
            @if ($shown)
                <p class="mb-3 text-small text-ink-secondary">Version {{ $shown->version }}, published {{ \App\Services\Calendar::formatDate($shown->published_at) }} by {{ $shown->publisher?->name }}@if ($shown->version !== $p->version), an older version @endif</p>
                <article class="prose-c">{!! \App\Services\Procedures::render($shown->body) !!}</article>
            @else
                <x-empty heading="Not published yet" text="The owner writes the steps, then publishes the first version." />
            @endif

            @if (session('audit_followup'))
                <div class="banner banner-off mt-8 flex flex-wrap items-center gap-3">
                    <span class="flex-1">The audit found the procedure wasn't fully followed.</span>
                    <a class="btn btn-sm" href="{{ route('org.problems.create', ['team' => $p->team_seat_id, 'title' => $p->title.' is not followed', 'source_type' => 'audit', 'source_id' => session('audit_followup')]) }}">Raise problem</a>
                    <a class="btn btn-sm" href="#audit-task">Create task</a>
                </div>
                <div id="audit-task" class="mt-4"><x-quick-task source-type="procedure" :source-id="$p->id" :team="$p->team_seat_id" heading="Create task" /></div>
            @endif

            @can('audit', $p)
                <section class="mt-10 max-w-xl">
                    <h2 class="heading mb-3">Record an audit</h2>
                    <form method="POST" action="{{ route('org.procedures.audit', $p) }}" class="space-y-4" novalidate>
                        @csrf
                        <x-field name="audited_on" label="Audited on" type="date" :value="$today->toDateString()" :max="$today->toDateString()" />
                        <x-field name="result" label="Result" type="select" :options="['followed' => 'Followed', 'partly' => 'Partly followed', 'not_followed' => 'Not followed']" />
                        <x-field name="findings" label="Findings" type="textarea" rows="3" help="Required unless it was followed." maxlength="2000" />
                        <button class="btn">Record audit</button>
                    </form>
                </section>
            @endcan
            @if ($audits->isNotEmpty())
                <section class="mt-10">
                    <h2 class="heading mb-2">Audits</h2>
                    @foreach ($audits as $a)<div class="border-b border-line py-2"><p class="text-small">{{ \App\Services\Calendar::formatDate($a->audited_on) }}, v{{ $a->procedure_version }}, {{ str_replace('_', ' ', ucfirst($a->result)) }}, {{ $a->auditor?->name }}</p>@if ($a->findings)<p class="text-small text-ink-secondary">{{ $a->findings }}</p>@endif</div>@endforeach
                </section>
            @endif
        </div>
        <aside class="box order-first lg:order-none lg:sticky lg:top-10 lg:self-start space-y-6 text-small">
            <div>
                <x-status :value="$status['in_sync'] ? 'in_sync' : 'out_of_sync'" />
                @foreach ($status['reasons'] as $r)<p class="text-ink-secondary">{{ $r }}</p>@endforeach
            </div>
            <dl class="rows">
                <div class="flex justify-between py-2"><dt class="text-ink-secondary">Team</dt><dd>{{ $teams[$p->team_seat_id] ?? '' }}</dd></div>
                <div class="flex justify-between py-2"><dt class="text-ink-secondary">Owner</dt><dd>{{ $p->ownerSeat?->title }}, {{ $p->ownerSeat?->holder?->name ?? 'Vacant' }}</dd></div>
                <div class="flex justify-between py-2"><dt class="text-ink-secondary">Audience</dt><dd>{{ $p->audience === 'organization' ? 'Whole organization' : 'Team and below' }}</dd></div>
                <div class="flex justify-between py-2"><dt class="text-ink-secondary">Acknowledged</dt><dd>{{ $status['acked'] }} of {{ $status['total'] }}</dd></div>
                <div class="flex justify-between py-2"><dt class="text-ink-secondary">Review due</dt><dd>{{ $status['review_due'] ? \App\Services\Calendar::formatDate($status['review_due']) : '–' }}</dd></div>
            </dl>
            @can('update', $p)
                @if ($p->version)<form method="POST" action="{{ route('org.procedures.review', $p) }}">@csrf<button class="btn btn-sm">Still current</button></form>@endif
            @endcan
            @if ($versions->count() > 1)
                <div><h2 class="subheading mb-1">Versions</h2>@foreach ($versions as $v)<p><a class="link" href="{{ route('org.procedures.show', ['procedure' => $p, 'v' => $v->version]) }}">v{{ $v->version }}</a>, {{ \App\Services\Calendar::formatDate($v->published_at) }}</p>@endforeach</div>
            @endif
        </aside>
    </div>
</x-app>
