<x-app width="reading" title="Handover">
    @php
        $labels = ['tasks' => 'Tasks', 'missions' => 'Missions', 'problems' => 'Problems', 'events' => 'Future events'];
        $trigger = match ($t['type']) {
            'deactivate' => 'Deactivate '.($plan[$t['member']]['member']->name ?? ''),
            'account' => 'Leave this organization',
            'vacate' => ($t['holder'] ? 'Replace the holder of ' : 'Vacate ').$f->seat($t['seat'])->title,
            'move' => 'Move '.$f->seat($t['seat'])->title.' under '.$f->seat($t['parent'])->title,
            'delete_seat' => 'Delete '.$f->seat($t['seat'])->title,
        };
        $total = collect($plan)->sum(fn ($p) => collect($p['items'])->sum->count());
        $options = $members->mapWithKeys(fn ($m) => [$m->id => $m->name]);
    @endphp
    <div class="panel max-w-[720px] p-6">
        <h1 class="heading">Handover: {{ $trigger }}</h1>
        <p class="mt-1 text-ink-secondary">This change happens only after the work below has a new owner. Cancel to change nothing.</p>
        <x-errors />
        @foreach ($errors->get('receiver') + $errors->get('member') + $errors->get('parent') + $errors->get('seat') as $e)<p class="error">{{ $e }}</p>@endforeach

        <form method="POST" action="{{ route('org.handover.store') }}" class="mt-6 space-y-6">
            @csrf
            @foreach ($t as $k => $v)<input type="hidden" name="trigger[{{ $k }}]" value="{{ $v }}">@endforeach
            <input type="hidden" name="back" value="{{ $cancel }}">

            @if ($t['type'] === 'delete_seat' && $owned)
                <x-field name="trigger[replacement]" label="Seat that takes over {{ collect($owned)->map(fn ($c, $k) => $c.' '.$k)->join(' and ') }}" type="select"
                    :options="['' => 'Choose a seat'] + $f->ordered()->reject(fn ($s) => $s->id === $t['seat'])->mapWithKeys(fn ($s) => [$s->id => $s->title])->all()" required />
            @endif
            @if ($dissolved && $dissolution)
                <div>
                    <p class="mb-2">{{ $f->seat($dissolved)->teamLabel() }} ends with this change. It has {{ collect($dissolution)->map(fn ($c, $k) => $c.' '.$k)->join(', ') }}.</p>
                    <x-field name="trigger[destination]" label="Team that takes them over" type="select"
                        :options="['' => 'Choose a team'] + collect($f->teams())->reject(fn ($s) => $s->id === $dissolved)->mapWithKeys(fn ($s) => [$s->id => $s->teamLabel()])->all()" required />
                </div>
            @endif

            @if ($total === 0)
                <p class="font-medium">Nothing to hand over.</p>
            @else
                @foreach ($plan as $pid => $p)
                    @continue(collect($p['items'])->sum->count() === 0)
                    <section class="border-t border-line pt-4">
                        <h2 class="subheading">{{ $p['member']->name }}</h2>
                        <p class="help">{{ $p['departing'] ? 'Every open item they own.' : 'Items in teams they will no longer belong to.' }} Consulted and Informed spots are removed when someone leaves.</p>
                        <x-field :name="'receivers['.$pid.'][all]'" label="Hand everything to" type="select" :options="$options->except($pid)->all()" :value="$p['default']" class="mt-3 max-w-sm" />
                        @foreach ($p['items'] as $type => $rows)
                            @continue($rows->isEmpty())
                            <details class="mt-4">
                                <summary class="cursor-pointer label">{{ $labels[$type] }} ({{ $rows->count() }})</summary>
                                <div class="rows mt-2">
                                    @foreach ($rows as $row)
                                        <div class="grid items-center gap-2 py-2 sm:grid-cols-[1fr_220px]">
                                            <span class="text-small">{{ $row->title }}</span>
                                            <label class="sr-only" for="r-{{ $pid }}-{{ $type }}-{{ $row->id }}">Receiver for {{ $row->title }}</label>
                                            <select id="r-{{ $pid }}-{{ $type }}-{{ $row->id }}" name="receivers[{{ $pid }}][items][{{ $type }}:{{ $row->id }}]" class="input text-small">
                                                <option value="">Same as everything</option>
                                                @foreach ($options->except($pid) as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                                            </select>
                                        </div>
                                    @endforeach
                                </div>
                            </details>
                        @endforeach
                    </section>
                @endforeach
            @endif

            <div class="flex justify-end gap-2 border-t border-line pt-4">
                <a href="{{ $cancel }}" class="btn">Cancel</a>
                <button class="btn btn-primary">Confirm</button>
            </div>
        </form>
    </div>
</x-app>
