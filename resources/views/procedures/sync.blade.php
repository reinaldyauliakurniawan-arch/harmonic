<x-app title="Synchronization Cycle">
    <x-page-header title="Synchronization Cycle" description="Is every procedure acknowledged, audited, and reviewed on time?" />
    @if ($rows->isEmpty())
        <x-empty heading="No procedures yet" text="Procedures appear here once a team documents them." />
    @else
        <div class="relative overflow-x-auto"><table class="tbl">
            <thead><tr><th>Procedure</th><th>Team</th><th class="num">Acknowledged</th><th>Last audit</th><th>Review due</th><th><a class="hover:underline" href="{{ route('org.sync.index', ['sort' => $sort === 'status' ? null : 'status']) }}">Status</a></th></tr></thead>
            <tbody>
            @foreach ($rows as $r)
                @php $p = $r['p']; $s = $r['s']; @endphp
                <tr>
                    <td><a class="font-medium hover:underline" href="{{ route('org.procedures.show', $p) }}">{{ $p->title }}</a></td>
                    <td>{{ $teams[$p->team_seat_id] ?? '' }}</td>
                    <td class="num">{{ $s['acked'] }} of {{ $s['total'] }}</td>
                    <td>{{ $s['audit'] ? \App\Services\Calendar::formatDate($s['audit']->audited_on).', '.str_replace('_', ' ', $s['audit']->result) : '–' }}</td>
                    <td>{{ $s['review_due'] ? \App\Services\Calendar::formatDate($s['review_due']) : '–' }}</td>
                    <td><x-status :value="$s['in_sync'] ? 'in_sync' : 'out_of_sync'" />@unless ($s['in_sync'])<div class="text-caption text-ink-secondary">{{ implode('; ', $s['reasons']) }}</div>@endunless</td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    @endif
</x-app>
