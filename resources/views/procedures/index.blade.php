<x-app title="Standardization">
    <x-page-header title="Standardization" description="Your core procedures, documented at the 20% of steps that produce 80% of the result.">
        <x-slot:actions>
            <a href="{{ route('org.sync.index') }}" class="btn">Sync board</a>
            @if ($creatable)
                <form method="GET" action="{{ route('org.procedures.create') }}" class="flex items-center gap-2">
                    <label class="sr-only" for="new-team">Team</label>
                    <select id="new-team" name="team" class="input w-44">@foreach ($creatable as $id => $l)<option value="{{ $id }}">{{ $l }}</option>@endforeach</select>
                    <button class="btn btn-primary">New procedure</button>
                </form>
            @endif
        </x-slot:actions>
    </x-page-header>
    @if ($count > 10)<p class="help mb-4">Most organizations run on 5–10 core procedures.</p>@endif
    @if ($groups->isEmpty())
        <x-empty heading="Document your first procedure" text="Team leaders write procedures in three steps: identify, document and simplify, then package and roll out." />
    @else
        @foreach ($groups as $team => $list)
            <section class="mb-8">
                <h2 class="heading mb-2">{{ $teams[$team] ?? 'Removed team' }}</h2>
                <div class="relative overflow-x-auto"><table class="tbl">
                    <thead><tr><th>Procedure</th><th>Owner seat</th><th>Version</th><th>Sync</th><th>Next review</th></tr></thead>
                    <tbody>
                    @foreach ($list as $p)
                        @php $s = $status[$p->id]; @endphp
                        <tr>
                            <td><a class="font-medium hover:underline" href="{{ route('org.procedures.show', $p) }}">{{ $p->title }}</a></td>
                            <td>{{ $p->ownerSeat?->title }}</td>
                            <td>{{ $p->version ? 'v'.$p->version : 'Draft' }}</td>
                            <td><x-status :value="$s['in_sync'] ? 'in_sync' : 'out_of_sync'" /></td>
                            <td class="{{ $s['review_due'] && $s['review_due']->isPast() ? 'cell-off' : '' }}">{{ $s['review_due'] ? \App\Services\Calendar::formatDate($s['review_due']) : '–' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
            </section>
        @endforeach
    @endif
    @if ($archived->isNotEmpty())
        <details class="mt-6"><summary class="cursor-pointer label">Archived ({{ $archived->count() }})</summary>@foreach ($archived as $p)<p class="py-1"><a class="link" href="{{ route('org.procedures.show', $p) }}">{{ $p->title }}</a></p>@endforeach</details>
    @endif
</x-app>
