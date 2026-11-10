<x-app :title="$seat ? $seat->title : 'Add a seat'">
    <p class="mb-2 text-small"><a class="text-ink-secondary hover:underline" href="{{ route('org.formation.index') }}">Winning Formation</a></p>
    <x-page-header :title="$seat ? $seat->title : 'Add a seat'" :description="$seat ? ($seat->parent_id ? 'Reports to '.$f->seat($seat->parent_id)?->title.'.' : 'The root seat of your organization.') : 'A position in Winning Formation. Teams form from a seat and the seats directly under it.'" />
    <x-errors />

    <div class="grid gap-12 lg:grid-cols-[minmax(0,1fr)_320px]">
        <section>
            <h2 class="section-title mb-4">Details</h2>
            <form method="POST" action="{{ $seat ? route('org.formation.update', $seat) : route('org.formation.store') }}" class="max-w-xl space-y-5" novalidate>
                @csrf
                @if ($seat) @method('PUT') @endif
                <x-field name="title" label="Title" :value="$seat?->title" required maxlength="120" />
                @unless ($seat)
                    @php $parentOptions = $f->ordered()->mapWithKeys(fn ($s) => [$s->id => str_repeat("\u{00A0}\u{00A0}\u{00A0}", $f->depth($s->id)).$s->title])->all(); @endphp
                    <x-field name="parent_id" label="Reports to" type="select" :options="$parentOptions" :value="$parent" />
                @endunless
                @php $count = count($seat?->responsibilities ?? []); @endphp
                <x-field name="responsibilities" label="Responsibilities" type="textarea" rows="6" :value="implode(PHP_EOL, $seat?->responsibilities ?? [])"
                    :help="'One per line, up to 7.'.($seat && $count < 3 ? ' Most seats have 3–7 responsibilities.' : '')" />
                @if ($seat && $f->isTeam($seat->id))
                    <x-field name="team_name" label="Team name" :value="$seat->team_name" :placeholder="$seat->title.' team'" maxlength="60" />
                    <label class="flex min-h-11 items-center gap-2">
                        <input type="hidden" name="is_leadership_team" value="0">
                        <input type="checkbox" name="is_leadership_team" value="1" class="h-4 w-4 accent-brass" @checked($seat->is_leadership_team)>
                        This seat's team is the Leadership Team
                    </label>
                @endif
                <div class="flex gap-2">
                    <button class="btn btn-primary">{{ $seat ? 'Save changes' : 'Add seat' }}</button>
                    <a href="{{ route('org.formation.index') }}" class="btn btn-ghost">Cancel</a>
                </div>
            </form>
        </section>

        @if ($seat)
            <aside class="space-y-10">
                <section>
                    <h2 class="section-title mb-3">Holder</h2>
                    <form method="POST" action="{{ route('org.formation.holder', $seat) }}" class="space-y-3">
                        @csrf
                        <x-field name="holder_id" label="Who holds this seat" type="select" :options="['' => 'Vacant'] + $members" :value="$seat->holder_id" help="Replacing or vacating the current holder opens Handover first." />
                        <button class="btn">Change holder</button>
                    </form>
                </section>

                @if ($seat->parent_id)
                    <section>
                        <h2 class="section-title mb-3">Position</h2>
                        <form method="POST" action="{{ route('org.formation.move', $seat) }}" class="space-y-3">
                            @csrf
                            <x-field name="parent_id" label="Reports to" type="select"
                                :options="$f->ordered()->reject(fn ($p) => $p->id === $seat->id || in_array($p->id, $f->descendantIds($seat->id), true))->mapWithKeys(fn ($p) => [$p->id => $p->title])->all()"
                                :value="$seat->parent_id" help="Seats under this one move with it." />
                            <button class="btn">Move seat</button>
                        </form>
                    </section>
                @endif

                @if ($f->childIds($seat->id))
                    <section>
                        <h2 class="section-title mb-1">Order of seats under it</h2>
                        <p class="help mb-2">Drag by the handle, or use the arrows.</p>
                        <ol class="rows" x-data="sortable" data-url="{{ route('org.formation.order', $seat) }}">
                            @foreach ($f->childIds($seat->id) as $cid)
                                <li class="flex min-h-11 items-center gap-2 bg-paper" data-sort-id="{{ $cid }}" @dragover.prevent="over" @drop.prevent="drop">
                                    <span class="cursor-grab text-ink-muted" draggable="true" @dragstart="start" @dragend="end" aria-hidden="true"><x-icon name="grip-vertical" /></span>
                                    <span class="flex-1">{{ $f->seat($cid)->title }}</span>
                                    <button type="button" class="icon-btn" @click="up" aria-label="Move {{ $f->seat($cid)->title }} up"><x-icon name="chevron-up" /></button>
                                    <button type="button" class="icon-btn" @click="down" aria-label="Move {{ $f->seat($cid)->title }} down"><x-icon name="chevron-down" /></button>
                                </li>
                            @endforeach
                        </ol>
                        <p class="help" role="status" data-sort-status></p>
                    </section>
                @endif

                <section>
                    <h2 class="section-title mb-3">Add a seat under it</h2>
                    <a class="btn" href="{{ route('org.formation.create', ['parent' => $seat->id]) }}"><x-icon name="plus" />Add seat under {{ $seat->title }}</a>
                </section>

                @if ($seat->parent_id)
                    <section>
                        <h2 class="section-title mb-3">Delete</h2>
                        <p class="help mb-3">Only a seat with no seats under it can be deleted. Its holder's work goes through Handover.</p>
                        <form method="POST" action="{{ route('org.formation.destroy', $seat) }}">@csrf @method('DELETE')<button class="btn btn-danger" @disabled($f->childIds($seat->id))>Delete {{ $seat->title }}</button></form>
                    </section>
                @endif
            </aside>
        @endif
    </div>
</x-app>
