@php $cur = $week->toDateString(); $prev = $week->subWeek()->toDateString(); $currency = app(\App\Services\Tenant::class)->organization->currency; @endphp
@if ($metrics->isEmpty())
    <p class="text-ink-secondary">This team tracks no metrics yet.</p>
@else
    <div class="relative overflow-x-auto"><table class="tbl">
        <thead><tr><th>Metric</th><th>Goal</th><th class="num">Last week</th><th class="num">This week</th><th><span class="sr-only">Actions</span></th></tr></thead>
        <tbody>
        @foreach ($metrics as $m)
            @php $vals = $metricValues[$m->id] ?? collect(); $lw = $vals[$prev] ?? null; $tw = $vals[$cur] ?? null; $st = $metricsSvc->cellStatus($lw, $week->subWeek()); @endphp
            <tr class="{{ in_array($st, ['off', 'missing']) ? 'bg-off-track-subtle' : '' }}">
                <td class="font-medium">{{ $m->name }}</td>
                <td class="text-ink-secondary">{{ \App\Services\Metrics::goalLabel($m->operator, $m->goal_value, $m->goal_value_max, $m->unit, (int) $m->decimals, $currency) }}</td>
                <td class="num">@if ($lw)<span class="{{ $lw->status === 'on' ? 'cell-on' : 'cell-off' }}">{{ \App\Services\Metrics::format($lw->value, $m->unit, (int) $m->decimals, $currency) }}</span>@else<x-status value="missing" />@endif</td>
                <td class="num">@if ($tw)<span class="{{ $tw->status === 'on' ? 'cell-on' : 'cell-off' }}">{{ \App\Services\Metrics::format($tw->value, $m->unit, (int) $m->decimals, $currency) }}</span>@else – @endif</td>
                <td>@if ($participant)<form method="POST" action="{{ route('org.meetings.drop', $meeting) }}">@csrf<input type="hidden" name="type" value="metric"><input type="hidden" name="id" value="{{ $m->id }}"><button class="btn btn-sm">Drop to problems</button></form>@endif</td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
@endif
