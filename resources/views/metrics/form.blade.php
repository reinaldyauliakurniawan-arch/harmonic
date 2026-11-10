@php
    $teamId = $metric?->team_seat_id ?? $team;
    $seats = collect($f->teamSeatIds($teamId))->mapWithKeys(fn ($id) => [$id => $f->seat($id)->title.', '.($f->seat($id)->holder?->name ?? 'Vacant')])->all();
@endphp
<x-errors />
<form method="POST" action="{{ $metric ? route('org.metrics.update', $metric) : route('org.metrics.store') }}" class="box grid gap-4 sm:grid-cols-2" novalidate x-data="goalPreview">
    @csrf
    @if ($metric) @method('PUT') @else <input type="hidden" name="team_seat_id" value="{{ $teamId }}"> @endif
    <x-field name="name" label="Name" :value="$metric?->name" required maxlength="120" class="sm:col-span-2" />
    <x-field name="description" label="Description (optional)" type="textarea" rows="2" :value="$metric?->description" maxlength="500" class="sm:col-span-2" />
    <x-field name="owner_seat_id" label="Owner seat" type="select" :options="$seats" :value="$metric?->owner_seat_id" />
    <x-field name="unit" label="Unit" type="select" :options="['number' => 'Number', 'percent' => 'Percent', 'currency' => 'Currency', 'hours' => 'Hours']" :value="$metric?->unit ?? 'number'" />
    <x-field name="decimals" label="Decimals" type="select" :options="[0 => '0', 1 => '1', 2 => '2']" :value="$metric?->decimals ?? 0" />
    <x-field name="operator" label="On track when the value is" type="select" :options="['gte' => 'At least (≥)', 'lte' => 'At most (≤)', 'eq' => 'Exactly (=)', 'gt' => 'More than (>)', 'lt' => 'Less than (<)', 'between' => 'Between']" :value="$metric?->operator ?? 'gte'" @change="update" />
    <x-field name="goal_value" label="Goal" type="number" step="any" :value="$metric?->goal_value" required @input="update" />
    <x-field name="goal_value_max" label="Goal max (for Between)" type="number" step="any" :value="$metric?->goal_value_max" @input="update" />
    <x-field name="quarter_rollup" label="Quarter roll-up" type="select" :options="['sum' => 'Sum', 'average' => 'Average', 'latest' => 'Latest']" :value="$metric?->quarter_rollup ?? 'sum'" />
    <x-field name="auto_problem_after" label="Raise a problem after" type="select" :options="['' => 'Off', 1 => '1 week off', 2 => '2 weeks off', 3 => '3 weeks off', 4 => '4 weeks off']" :value="$metric ? $metric->auto_problem_after : 2" />
    <p class="sm:col-span-2 text-small text-ink-secondary" aria-live="polite" x-text="preview"></p>
    @if ($metric)
        <label class="flex min-h-11 items-center gap-2 sm:col-span-2"><input type="hidden" name="reapply" value="0"><input type="checkbox" name="reapply" value="1" class="h-4 w-4 accent-brass"> Also apply to this quarter's saved weeks</label>
    @endif
    <div class="sm:col-span-2"><button class="btn btn-primary">{{ $metric ? 'Save changes' : 'Add metric' }}</button></div>
</form>
