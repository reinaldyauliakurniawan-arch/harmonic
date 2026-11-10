<x-app title="Activity">
    <x-page-header title="Activity" description="Who changed people, seats, and settings, newest first." />
    <form method="GET" class="filters mb-6" x-data="autoSubmit" @change="submit">
        <x-filter name="person" label="Person" :options="['' => 'Everyone'] + $people->all()" :value="$q['person'] ?? ''" />
        <x-filter name="action" label="Action" :options="['' => 'All actions'] + array_combine(\App\Http\Controllers\Org\ActivityController::ACTIONS, \App\Http\Controllers\Org\ActivityController::ACTIONS)" :value="$q['action'] ?? ''" />
        <button class="btn btn-sm btn-ghost" data-apply>Apply</button>
    </form>
    @if ($logs->isEmpty())
        <x-empty heading="Nothing recorded yet" text="Invitations, seat changes, and settings changes show up here." />
    @else
        <div class="relative overflow-x-auto"><table class="tbl">
            <thead><tr><th>When</th><th>Who</th><th>Action</th><th>Details</th></tr></thead>
            <tbody>
            @foreach ($logs as $log)
                @php $cal = \App\Services\Calendar::for(app(\App\Services\Tenant::class)->organization); $d = $log->data ?? []; @endphp
                <tr>
                    <td class="whitespace-nowrap">{{ $cal->local($log->created_at)?->format('D, j M Y H:i') }}</td>
                    <td>{{ $log->actor?->name ?? 'System' }}</td>
                    <td>{{ $log->action }}</td>
                    <td class="text-ink-secondary">
                        @if (isset($d['member'])){{ $names[$d['member']] ?? '' }} @endif
                        @if (isset($d['email'])){{ $d['email'] }} @endif
                        @if (isset($d['title'])){{ $d['title'] }} @endif
                        @if (isset($d['counts']))@foreach ($d['counts'] as $k => $c){{ $c }} {{ $k }} @endforeach @endif
                        @if (isset($d['fields'])){{ implode(', ', $d['fields']) }}@endif
                        @if (! empty($d['seat_not_assigned']))Seat was already filled, so it wasn't assigned.@endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
        {{ $logs->links() }}
    @endif
</x-app>
