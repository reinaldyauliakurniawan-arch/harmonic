<x-app title="360 types and items">
    <x-page-header title="Types and items" description="Leadership types, the statements rated under each, and the five rubric levels. Archiving hides something from new cycles only." />
    <x-errors />
    <section class="mb-10 max-w-3xl">
        <h2 class="heading mb-2">Rubric levels</h2>
        @foreach ($levels as $l)
            <form method="POST" action="{{ route('org.assessment.setup.save') }}" class="grid gap-2 border-b border-line py-2 sm:grid-cols-[40px_160px_1fr_auto] sm:items-end sm:[&:not(:first-of-type)_.field-label]:sr-only">
                @csrf <input type="hidden" name="action" value="level"><input type="hidden" name="id" value="{{ $l->id }}">
                <span class="font-semibold sm:flex sm:h-10 sm:items-center">{{ $l->score }}</span>
                <x-field name="label" label="Label" :value="$l->label" maxlength="40" :id="'ll'.$l->id" />
                <x-field name="description" label="Description" :value="$l->description" maxlength="255" :id="'ld'.$l->id" />
                <button class="btn btn-sm">Save</button>
            </form>
        @endforeach
    </section>
    <section class="max-w-3xl">
        <h2 class="heading mb-2">Leadership types</h2>
        <form method="POST" action="{{ route('org.assessment.setup.save') }}" class="mb-6 flex items-end gap-2" novalidate>@csrf<input type="hidden" name="action" value="type"><x-field name="name" label="New type" maxlength="80" class="flex-1" /><button class="btn">Add type</button></form>
        @if ($types->isEmpty())
            <p class="text-ink-secondary">No types yet. Add one, then its items.</p>
        @endif
        <div x-data="sortable" data-url="{{ route('org.assessment.order', ['kind' => 'types']) }}">
            @foreach ($types as $t)
                <div class="mb-6 border-t border-line bg-paper pt-4" data-sort-id="{{ $t->id }}" @dragover.prevent="over" @drop.prevent="drop">
                    <div class="flex items-center gap-2">
                        <span class="cursor-grab text-ink-muted" draggable="true" @dragstart="start" @dragend="end" aria-hidden="true"><x-icon name="grip-vertical" /></span>
                        <h3 class="subheading flex-1 {{ $t->archived_at ? 'text-ink-muted line-through' : '' }}">{{ $t->name }}</h3>
                        <button type="button" class="icon-btn" @click="up" aria-label="Move {{ $t->name }} up"><x-icon name="chevron-up" /></button>
                        <button type="button" class="icon-btn" @click="down" aria-label="Move {{ $t->name }} down"><x-icon name="chevron-down" /></button>
                        <form method="POST" action="{{ route('org.assessment.setup.save') }}">@csrf<input type="hidden" name="action" value="archive_type"><input type="hidden" name="id" value="{{ $t->id }}"><button class="btn btn-sm">{{ $t->archived_at ? 'Restore' : 'Archive' }}</button></form>
                    </div>
                    <ol class="mt-2 pl-6" x-data="sortable" data-url="{{ route('org.assessment.order', ['kind' => 'items', 'type' => $t->id]) }}">
                        @foreach ($t->items as $i)
                            <li class="flex min-h-10 items-center gap-2 border-b border-line bg-paper" data-sort-id="{{ $i->id }}" @dragover.prevent="over" @drop.prevent="drop">
                                <span class="cursor-grab text-ink-muted" draggable="true" @dragstart="start" @dragend="end" aria-hidden="true"><x-icon name="grip-vertical" :size="14" /></span>
                                <span class="flex-1 {{ $i->archived_at ? 'text-ink-muted line-through' : '' }}">{{ $i->text }}</span>
                                <span class="row-tools">
                                    <button type="button" class="icon-btn" @click="up" aria-label="Move item up"><x-icon name="chevron-up" :size="14" /></button>
                                    <button type="button" class="icon-btn" @click="down" aria-label="Move item down"><x-icon name="chevron-down" :size="14" /></button>
                                </span>
                                <form method="POST" action="{{ route('org.assessment.setup.save') }}">@csrf<input type="hidden" name="action" value="archive_item"><input type="hidden" name="id" value="{{ $i->id }}"><button class="btn btn-sm btn-ghost">{{ $i->archived_at ? 'Restore' : 'Archive' }}</button></form>
                            </li>
                        @endforeach
                    </ol>
                    <form method="POST" action="{{ route('org.assessment.setup.save') }}" class="mt-3 flex items-end gap-2 pl-6" novalidate>@csrf<input type="hidden" name="action" value="item"><input type="hidden" name="type_id" value="{{ $t->id }}"><x-field name="text" label="New item" maxlength="255" class="flex-1" :id="'it'.$t->id" /><button class="btn btn-sm">Add item</button></form>
                </div>
            @endforeach
        </div>
        <p class="help" role="status" data-sort-status></p>
    </section>
</x-app>
