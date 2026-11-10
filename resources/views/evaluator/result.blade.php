@php $r = \App\Http\Controllers\Org\EvaluatorController::result($e->scores->pluck('score')->all(), $e->understands, $e->wants, $e->capacity, $org); @endphp
@if ($r['person'] && $r['seat'])
    <span class="cell-on inline-flex items-center gap-1 font-medium"><x-icon name="check" :size="14" />{{ $r['label'] }}</span>
@else
    <span class="inline-flex items-center gap-1 font-medium"><x-icon name="triangle" :size="10" class="cell-off" label="Below the bar" />{{ $r['label'] }}</span>
@endif
