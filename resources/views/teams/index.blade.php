<x-app title="Teams">
    <x-page-header title="Teams" description="Every team comes from Winning Formation: a seat and the seats directly under it." />
    @if (! $teams)
        <x-empty heading="No teams yet" text="Add seats under another seat in Winning Formation to form a team.">
            <a href="{{ route('org.formation.index') }}" class="btn">Open Winning Formation</a>
        </x-empty>
    @else
        <div class="relative overflow-x-auto"><table class="tbl">
            <thead><tr><th>Team</th><th>Leader</th><th>Members</th><th class="num">Off-track metrics</th><th class="num">Open problems</th><th class="num">Active Missions</th></tr></thead>
            <tbody>
            @foreach ($teams as $t)
                <tr>
                    <td><a class="font-medium hover:underline" href="{{ route('org.teams.show', $t) }}">{{ $t->teamLabel() }}</a>@if (in_array($t->id, $f->leadershipSeatIds(), true) && $f->leadershipSeatId() === $t->id) <span class="badge">Leadership</span>@endif</td>
                    <td>{{ $t->holder?->name ?? 'Vacant' }}</td>
                    <td>{{ collect($f->teamMemberIds($t->id))->map(fn ($id) => $members[$id]->name ?? '')->join(', ') }}</td>
                    <td class="num"><a class="hover:underline {{ ($off[$t->id] ?? 0) ? 'cell-off' : '' }}" href="{{ route('org.metrics.index', ['team' => $t->id]) }}">{{ $off[$t->id] ?? 0 }}</a></td>
                    <td class="num"><a class="hover:underline" href="{{ route('org.problems.index', ['team' => $t->id]) }}">{{ $problems[$t->id] ?? 0 }}</a></td>
                    <td class="num"><a class="hover:underline" href="{{ route('org.missions.index', ['team' => $t->id]) }}">{{ $missions[$t->id] ?? 0 }}</a></td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    @endif
</x-app>
