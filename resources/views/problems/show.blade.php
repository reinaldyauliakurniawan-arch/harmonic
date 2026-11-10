<x-app :title="$p->title">
    <x-page-header :title="$p->title" :description="$p->description" />
    <x-errors />
    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_300px] lg:items-start">
        <div class="box min-w-0">@include('problems.solve', ['meetingId' => null])</div>
        <aside class="box order-first lg:order-none lg:sticky lg:top-10 lg:self-start space-y-6 text-small">
            <dl class="rows">
                <div class="flex justify-between py-2"><dt class="text-ink-secondary">Team</dt><dd>{{ $teams[$p->team_seat_id] ?? '' }}</dd></div>
                <div class="flex justify-between py-2"><dt class="text-ink-secondary">Status</dt><dd>{{ ucfirst($p->status) }}</dd></div>
                <div class="flex justify-between py-2"><dt class="text-ink-secondary">Horizon</dt><dd>{{ $p->horizon === 'short' ? 'Short-term' : 'Long-term' }}</dd></div>
                <div class="flex justify-between py-2"><dt class="text-ink-secondary">Raised by</dt><dd>{{ $p->raiser?->name }}</dd></div>
                <div class="flex justify-between py-2"><dt class="text-ink-secondary">Opened</dt><dd>{{ $p->created_at->diffForHumans() }}</dd></div>
                @if ($source)<div class="py-2"><dt class="text-ink-secondary">From</dt><dd>{{ \App\Services\Problems::SOURCES[$p->source_type] ?? '' }}: {{ $source->title ?? $source->name }}</dd></div>@endif
                @if ($p->status === 'dropped')<div class="py-2"><dt class="text-ink-secondary">Dropped</dt><dd>@if ($dupOf)Duplicate of <a class="link" href="{{ route('org.problems.show', $dupOf) }}">{{ $dupOf->title }}</a>@else{{ $p->drop_reason }}@endif</dd></div>@endif
            </dl>
            @if ($canMaintain && $p->isOpen())
                <section class="space-y-4">
                    <h2 class="subheading">Maintain</h2>
                    <form method="POST" action="{{ route('org.problems.maintain', $p) }}" class="flex items-end gap-2">@csrf<input type="hidden" name="action" value="owner"><x-field name="owner_id" label="Owner" type="select" :options="['' => 'No owner'] + $members" :value="$p->owner_id" class="flex-1" /><button class="btn btn-sm">Set</button></form>
                    <form method="POST" action="{{ route('org.problems.maintain', $p) }}">@csrf<input type="hidden" name="action" value="horizon"><input type="hidden" name="horizon" value="{{ $p->horizon === 'short' ? 'long' : 'short' }}"><button class="btn btn-sm">Move to {{ $p->horizon === 'short' ? 'long-term' : 'short-term' }}</button></form>
                    <form method="POST" action="{{ route('org.problems.maintain', $p) }}" class="flex items-end gap-2">@csrf<input type="hidden" name="action" value="move"><x-field name="team_seat_id" label="Move to team" type="select" :options="$teams" :value="$p->team_seat_id" class="flex-1" /><button class="btn btn-sm">Move</button></form>
                    @if ($duplicates->isNotEmpty())
                        <form method="POST" action="{{ route('org.problems.maintain', $p) }}" class="flex items-end gap-2">@csrf<input type="hidden" name="action" value="duplicate"><x-field name="duplicate_of" label="Duplicate of" type="select" :options="$duplicates->all()" class="flex-1" /><button class="btn btn-sm">Mark</button></form>
                    @endif
                    <form method="POST" action="{{ route('org.problems.maintain', $p) }}" class="space-y-2" x-data="confirmForm" data-message="Drop {{ $p->title }}?" @submit="ask">@csrf<input type="hidden" name="action" value="drop"><x-field name="reason" label="Reason for dropping" maxlength="280" /><button class="btn btn-sm btn-danger">Drop problem</button></form>
                </section>
            @endif
        </aside>
    </div>
</x-app>
