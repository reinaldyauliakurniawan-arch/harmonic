@php $dashes = [1 => '6 3', 2 => '2 3', 3 => '8 2 2 2', 4 => '1 2', 5 => '4 4', 6 => '10 3']; @endphp
<figure class="w-full">
    <svg viewBox="0 0 {{ $w }} {{ $h }}" class="h-auto w-full" role="img" aria-label="{{ $summary }}">
        @foreach ($ticks as $t)
            <line x1="{{ $padL }}" x2="{{ $w - 8 }}" y1="{{ $t['y'] }}" y2="{{ $t['y'] }}" stroke="var(--color-line)" stroke-width="1" />
            <text x="{{ $padL - 6 }}" y="{{ $t['y'] + 4 }}" text-anchor="end" font-size="11" fill="var(--color-ink-muted)">{{ $t['label'] }}</text>
        @endforeach
        @if ($goalBand)
            <rect x="{{ $padL }}" width="{{ $w - 8 - $padL }}" y="{{ $goalBand[0] }}" height="{{ max(1, $goalBand[1] - $goalBand[0]) }}" fill="var(--color-brass-subtle)" />
        @endif
        @if ($goalLine)
            <line x1="{{ $padL }}" x2="{{ $w - 8 }}" y1="{{ $goalLine[0] }}" y2="{{ $goalLine[0] }}" stroke="var(--color-line-strong)" stroke-width="1.5" stroke-dasharray="5 4" />
        @endif
        @foreach ($paths as $i => $p)
            <path d="{{ $p['d'] }}" fill="none" stroke="{{ $i === 0 ? 'var(--color-ink)' : 'var(--color-ink-secondary)' }}" stroke-width="1.5" @if ($i > 0) stroke-dasharray="{{ $dashes[$i] ?? '3 3' }}" @endif />
        @endforeach
        @foreach ($points as $pt)
            <circle cx="{{ $pt['x'] }}" cy="{{ $pt['y'] }}" r="3" fill="{{ $pt['status'] === 'off' ? 'var(--color-off-track)' : ($pt['status'] === 'on' ? 'var(--color-on-track)' : 'var(--color-ink)') }}" />
        @endforeach
        @php $step = max(1, (int) ceil(count($labels) / 8)); @endphp
        @foreach ($labels as $i => $l)
            @if ($i % $step === 0 || $i === count($labels) - 1)
                <text x="{{ $xs[$i] }}" y="{{ $h - 8 }}" text-anchor="middle" font-size="11" fill="var(--color-ink-muted)">{{ $l }}</text>
            @endif
        @endforeach
    </svg>
    @if (count($series) > 1)
        <figcaption class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-caption text-ink-secondary">
            @foreach ($series as $i => $s)
                <span class="inline-flex items-center gap-1"><svg width="20" height="6" aria-hidden="true"><line x1="0" x2="20" y1="3" y2="3" stroke="{{ $i === 0 ? 'var(--color-ink)' : 'var(--color-ink-secondary)' }}" stroke-width="1.5" @if ($i > 0) stroke-dasharray="{{ $dashes[$i] ?? '3 3' }}" @endif /></svg>{{ $s['name'] }}</span>
            @endforeach
        </figcaption>
    @endif
    <p class="sr-only">{{ $summary }}</p>
</figure>
