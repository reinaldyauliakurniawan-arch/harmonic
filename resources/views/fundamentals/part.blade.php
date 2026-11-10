@php $c = $content; @endphp
@switch($part)
    @case('beliefs')
        <dl class="space-y-3">@foreach ($c['beliefs'] ?? [] as $b)<div><dt class="font-medium">{{ $b['name'] }}</dt><dd class="text-ink-secondary">{{ $b['description'] }}</dd></div>@endforeach</dl>
        @break
    @case('why_way')
        <dl class="space-y-3"><div><dt class="label">Why we exist</dt><dd>{{ $c['why'] ?? '' }}</dd></div><div><dt class="label">What we do better than anyone</dt><dd>{{ $c['way'] ?? '' }}</dd></div></dl>
        @break
    @case('objective')
        <p>{{ $c['objective']['statement'] ?? '' }}</p><p class="text-small text-ink-secondary">By {{ $c['objective']['target_year'] ?? '' }}</p>
        @break
    @case('main_stage')
        @php $ms = $c['main_stage'] ?? []; @endphp
        <dl class="space-y-3">
            <div><dt class="label">Target market</dt><dd class="whitespace-pre-line">{{ $ms['target_market'] ?? '' }}</dd></div>
            <div><dt class="label">Three uniques</dt><dd><ol class="list-decimal pl-5">@foreach ($ms['uniques'] ?? [] as $u)<li>{{ $u }}</li>@endforeach</ol></dd></div>
            <div><dt class="label">Proven process</dt><dd><ol class="list-decimal pl-5">@foreach ($ms['proven_process'] ?? [] as $s)<li><span class="font-medium">{{ $s['title'] }}</span>@if ($s['description']): {{ $s['description'] }}@endif</li>@endforeach</ol></dd></div>
            @if (! empty($ms['guarantee']))<div><dt class="label">Guarantee</dt><dd>{{ $ms['guarantee'] }}</dd></div>@endif
        </dl>
        @break
    @case('snapshot_3y')
    @case('annual')
        @php $s = $c[$part] ?? []; @endphp
        <dl class="grid grid-cols-2 gap-x-6 gap-y-1 text-small sm:grid-cols-3">
            <div><dt class="text-ink-secondary">Target date</dt><dd>{{ $s['target_date'] ? \App\Services\Calendar::formatDate(\Carbon\Carbon::parse($s['target_date'])) : '–' }}</dd></div>
            <div><dt class="text-ink-secondary">Revenue</dt><dd>{{ $s['revenue'] ?: '–' }}</dd></div>
            <div><dt class="text-ink-secondary">Profit</dt><dd>{{ $s['profit'] ?: '–' }}</dd></div>
            @foreach ($s['measurables'] ?? [] as $m)<div><dt class="text-ink-secondary">{{ $m['label'] }}</dt><dd>{{ $m['value'] }}</dd></div>@endforeach
        </dl>
        <ul class="mt-3 list-disc pl-5">@foreach ($s[$part === 'annual' ? 'goals' : 'picture'] ?? [] as $l)<li>{{ $l }}</li>@endforeach</ul>
        @break
@endswitch
