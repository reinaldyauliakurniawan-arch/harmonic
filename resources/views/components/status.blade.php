@props(['value'])
@php
    $map = [
        'on' => ['On track', 'cell-on', 'check'], 'on_track' => ['On track', 'cell-on', 'check'],
        'off' => ['Off track', 'cell-off', 'triangle'], 'off_track' => ['Off track', 'cell-off', 'triangle'],
        'done' => ['Done', 'cell-on', 'check'], 'not_done' => ['Not done', 'cell-off', 'triangle'],
        'missing' => ['Not entered', 'cell-off', null], 'overdue' => ['Overdue', 'cell-off', 'triangle'],
        'in_sync' => ['In sync', 'cell-on', 'check'], 'out_of_sync' => ['Out of sync', 'cell-off', 'triangle'],
    ];
    [$label, $cls, $icon] = $map[$value] ?? [ucfirst(str_replace('_', ' ', $value)), 'text-ink-secondary', null];
@endphp
<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1 text-small font-medium $cls"]) }}>@if ($icon)<x-icon :name="$icon" :size="$icon === 'triangle' ? 10 : 14" />@endif{{ $label }}</span>
