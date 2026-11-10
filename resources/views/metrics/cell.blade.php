@if ($v)
    <span data-display class="{{ $status === 'on' ? 'cell-on' : 'cell-off' }} inline-flex items-center justify-end gap-1">@if ($status === 'off')<x-icon name="triangle" :size="8" label="Off track" />@endif{{ \App\Services\Metrics::format($v->value, $metric->unit, (int) $metric->decimals, $currency) }}@if ($v->note)<span class="note-dot" title="{{ $v->note }}"><span class="sr-only">Note: {{ $v->note }}</span></span>@endif</span>
    @if ($editable ?? false)<button type="button" class="cell-note" data-note="{{ $v->note }}" aria-label="{{ $v->note ? 'Edit note' : 'Add note' }}"><x-icon name="message-square" :size="12" /></button>@endif
@elseif ($status === 'missing')
    <span data-display><span class="sr-only">Not entered</span>&nbsp;</span>
@else
    <span data-display>&nbsp;</span>
@endif
