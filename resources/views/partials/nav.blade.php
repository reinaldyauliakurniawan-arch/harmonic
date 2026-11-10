<nav class="px-3">
    <ul class="space-y-0.5">
        @foreach ($daily as [$label, $route, $icon])
            <li><a href="{{ route($route) }}" class="nav-top" @if ($isActive($route)) aria-current="page" @endif @if ($rail) title="{{ $label }}" @endif data-label="{{ $label }}"><x-icon :name="$icon" /><span class="nav-label">{{ $label }}</span></a></li>
        @endforeach
    </ul>
    @foreach ($nav as [$group, $items])
        @php $holdsCurrent = collect($items)->contains(fn ($i) => $isActive($i[1])); @endphp
        <details class="nav-group mt-3" data-nav-group="{{ $group }}" @toggle="group" @if ($rail || $holdsCurrent || in_array($group, $opened, true)) open @endif>
            <summary class="nav-group-label">{{ $group }}<x-icon name="chevron-down" :size="14" /></summary>
            <ul class="space-y-0.5 pb-1">
                @foreach ($items as [$label, $route, $icon])
                    <li><a href="{{ route($route) }}" class="nav-item" @if ($isActive($route)) aria-current="page" @endif @if ($rail) title="{{ $label }}" @endif data-label="{{ $label }}"><x-icon :name="$icon" /><span class="nav-label">{{ $label }}</span></a></li>
                @endforeach
            </ul>
        </details>
    @endforeach
</nav>
