@php $have = $assignments->map(fn ($a) => $a->subject_id.':'.$a->type_id)->all(); @endphp
<x-app :title="$cycle->name" wide>
    <x-page-header :title="$cycle->name" :description="ucfirst($cycle->status).', '.\App\Services\Calendar::formatDate($cycle->opens_on).' – '.\App\Services\Calendar::formatDate($cycle->closes_on)">
        <x-slot:actions>
            @if ($canLead)
                @if ($cycle->status === 'draft')<form method="POST" action="{{ route('org.assessment.open', $cycle) }}">@csrf<button class="btn btn-primary">Open now</button></form>@endif
                @can('manage', app(\App\Services\Tenant::class)->organization)<x-confirm :action="route('org.assessment.destroy', $cycle)" method="DELETE" :message="'Delete '.$cycle->name.'?'" label="Delete cycle" btn="btn btn-danger" />@endcan
            @endif
        </x-slot:actions>
    </x-page-header>
    <x-errors />
    @foreach ($errors->get('cycle') + $errors->get('assessor') as $m)<p class="error mb-2">{{ $m }}</p>@endforeach

    @if ($canLead && $cycle->status !== 'closed')
        <section class="mb-10">
            <h2 class="heading mb-2">Who is assessed on what</h2>
            @if ($types->isEmpty())
                <p class="text-ink-secondary">Add leadership types first in <a class="link" href="{{ route('org.assessment.setup') }}">Types and items</a>.</p>
            @else
                <form method="POST" action="{{ route('org.assessment.matrix', $cycle) }}" x-data="tickMatrix">
                    @csrf
                    <div class="relative overflow-x-auto"><table class="tbl">
                        <thead><tr><th>Person</th>@foreach ($types as $t)<th class="text-center"><button type="button" class="link" data-col="{{ $t->id }}" @click="col">{{ $t->name }}</button></th>@endforeach</tr></thead>
                        <tbody>
                        @foreach ($members as $m)
                            <tr>
                                <td><button type="button" class="link" data-row="{{ $m->id }}" @click="row">{{ $m->name }}</button></td>
                                @foreach ($types as $t)
                                    <td class="text-center"><input type="checkbox" class="h-5 w-5 accent-brass" name="cells[{{ $m->id }}][]" value="{{ $t->id }}" data-r="{{ $m->id }}" data-c="{{ $t->id }}" aria-label="{{ $m->name }}: {{ $t->name }}" @checked(in_array($m->id.':'.$t->id, $have, true))></td>
                                @endforeach
                            </tr>
                        @endforeach
                        </tbody>
                    </table></div>
                    <p class="help mt-2">Click a name or a type to tick its whole row or column.</p>
                    <button class="btn btn-primary mt-3">Save assignments</button>
                </form>
            @endif
        </section>
    @endif

    <section>
        <h2 class="heading mb-2">Assignments</h2>
        @forelse ($assignments as $a)
            @php $others = $a->assessors->where('is_self', false); @endphp
            <div class="border-b border-line py-3">
                <p class="font-medium">{{ $a->subject->name }}, {{ $a->type->name }}
                    @if ($cycle->status === 'closed' && $seesPerson($a->subject_id))<a class="link ml-2 text-small" href="{{ route('org.assessment.person', ['membership' => $a->subject_id]) }}">Results</a>@endif
                </p>
                @if ($others->count() < 3)<p class="text-small cell-off">Fewer than 3 assessors besides the person, so answers may be identifiable.</p>@endif
                @if ($canLead)
                    <ul class="mt-1 flex flex-wrap gap-2 text-small">
                        @foreach ($a->assessors as $x)
                            <li class="badge">{{ $x->is_self ? 'Self' : $x->assessor->name }}@if ($x->completed_at), done @elseif ($cycle->status !== 'closed')
                                <form method="POST" action="{{ route('org.assessment.assessors.destroy', [$cycle, $x]) }}" class="inline">@csrf @method('DELETE')<button class="ml-1 text-off-track" aria-label="Remove {{ $x->assessor->name }}">×</button></form>@endif</li>
                        @endforeach
                    </ul>
                    @if ($cycle->status !== 'closed')
                        <form method="POST" action="{{ route('org.assessment.assessors.store', [$cycle, $a]) }}" class="mt-2 flex items-center gap-2">@csrf
                            <label class="sr-only" for="add-{{ $a->id }}">Add assessor</label>
                            <select id="add-{{ $a->id }}" name="assessor_id" class="input min-h-8 w-52 py-1 text-small">@foreach ($memberOptions as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select>
                            <button class="btn btn-sm">Add assessor</button>
                        </form>
                    @endif
                @else
                    <p class="text-small text-ink-secondary">{{ $a->assessors->whereNotNull('completed_at')->count() }} of {{ $a->assessors->count() }} answered</p>
                @endif
            </div>
        @empty
            <p class="text-ink-secondary">No one is assigned yet.</p>
        @endforelse
    </section>
</x-app>
