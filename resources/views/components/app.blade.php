@props(['title' => null, 'wide' => false, 'width' => 'standard'])
@php
    $org = app(\App\Services\Tenant::class)->organization;
    $me = app(\App\Services\Tenant::class)->membership;
    $access = app(\App\Services\Access::class);
    // Each section is drawn as one staff: its instruments sit on a shared hairline (DESIGN.md identity).
    $nav = [
        ['Direction', [['Fundamentals', 'org.fundamentals.show', 'compass'], ['Alignment', 'org.alignment.index', 'check-check']]],
        ['Measurements', [['Dashboard', 'org.metrics.index', 'gauge'], ['My Numbers', 'org.numbers.index', 'hash']]],
        ['Team', [['Formation', 'org.formation.index', 'network'], ['Evaluator', 'org.evaluator.index', 'user-check'], ['Teams', 'org.teams.index', 'users']]],
        ['Resolution', [['Problems', 'org.problems.index', 'circle-alert'], ['Solve', 'org.solve.index', 'puzzle']]],
        ['Procedures', [['Procedures', 'org.procedures.index', 'file-text'], ['Sync', 'org.sync.index', 'repeat']]],
        ['Discipline', [['Missions', 'org.missions.index', 'flag'], ['Meetings', 'org.meetings.index', 'messages-square']]],
        ['Assessments', [['Diagnostic', 'org.diagnostic.index', 'activity'], ['360', 'org.assessment.index', 'radar']]],
    ];
    if ($access->orgAdmin()) {
        $nav[] = ['Admin', [['People', 'org.people.index', 'users-round'], ['Settings', 'org.settings.edit', 'settings'], ['Activity', 'org.activity.index', 'clock']]];
    }
    $current = request()->route()?->getName() ?? '';
    $isActive = fn ($route) => $current === $route || (substr_count($route, '.') >= 2 && str_starts_with($current, \Illuminate\Support\Str::beforeLast($route, '.').'.'));
    // Daily places, in the phone bar's order (DESIGN.md navigation).
    $daily = [['Home', 'org.home', 'house'], ['Tasks', 'org.tasks.index', 'list-checks'], ['Calendar', 'org.calendar.index', 'calendar']];
    $measure = ['reading' => 'max-w-[720px]', 'standard' => 'max-w-[1040px]', 'wide' => ''][$wide ? 'wide' : $width];
    $rail = request()->cookie('nav_rail') === '1';
    $opened = array_intersect(explode(',', (string) request()->cookie('nav_open')), array_column($nav, 0));
    $others = auth()->user()->activeMemberships()->where('organization_id', '!=', $org->id);
@endphp
<!doctype html>
<html lang="en" @if (in_array(request()->cookie('theme'), ['light', 'dark'], true)) data-theme="{{ request()->cookie('theme') }}" @endif>
<head><x-head :title="$title" /></head>
<body class="min-h-screen {{ $rail ? 'nav-rail' : '' }}" x-data="shell" @keydown.escape.window="closeMenu">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-2 focus:top-2 focus:z-50 focus:bg-surface focus:p-2">Skip to content</a>

    <aside class="sidebar fixed inset-y-0 left-0 hidden w-60 flex-col border-r md:flex" aria-label="Main">
        <div class="rail-head px-5 pb-4 pt-6">
            <div class="flex items-center justify-between gap-2">
                <a href="{{ route('org.home') }}" class="wordmark rail-hide block">Harmonic</a>
                <button type="button" class="icon-btn" @click="toggleRail" :aria-expanded="expanded" aria-controls="sidebar-nav" aria-label="Collapse sidebar" x-ref="railButton">
                    <span class="rail-hide"><x-icon name="panel-left-close" /></span><span class="rail-show"><x-icon name="panel-left-open" /></span>
                </button>
            </div>
            <div class="rail-hide">@include('partials.org-switcher')</div>
        </div>
        <div id="sidebar-nav" class="nav-scroll min-h-0 flex-1 overflow-y-auto">@include('partials.nav')</div>
        <div class="border-t border-line p-2">@include('partials.account-menu', ['up' => true])</div>
    </aside>

    <header class="flex h-13 items-center justify-between gap-3 border-b border-line px-4 md:hidden">
        <div class="min-w-0">
            <a href="{{ route('org.home') }}" class="wordmark text-[20px]">Harmonic</a>
            <span class="ml-2 truncate text-caption text-ink-secondary">{{ $org->name }}</span>
        </div>
        @include('partials.account-menu', ['up' => false])
    </header>

    <main id="main" class="md:ml-60">
        <div class="{{ $measure }} mx-auto px-4 pb-28 pt-6 md:px-10 md:pb-16 md:pt-10">
            {{ $slot }}
        </div>
    </main>

    <nav class="fixed inset-x-0 bottom-0 z-20 grid grid-cols-5 border-t border-line bg-paper md:hidden" aria-label="Quick">
        @foreach ([['Home', 'org.home', 'house'], ['Tasks', 'org.tasks.index', 'list-checks'], ['Calendar', 'org.calendar.index', 'calendar'], ['My Numbers', 'org.numbers.index', 'hash']] as [$label, $route, $icon])
            <a href="{{ route($route) }}" class="flex min-h-14 flex-col items-center justify-center gap-0.5 text-caption {{ $isActive($route) ? 'text-brass' : 'text-ink-secondary' }}" @if ($isActive($route)) aria-current="page" @endif><x-icon :name="$icon" :size="20" />{{ $label }}</a>
        @endforeach
        <button type="button" class="flex min-h-14 flex-col items-center justify-center gap-0.5 text-caption text-ink-secondary" @click="openMenu" aria-controls="mobile-menu" :aria-expanded="menu" x-ref="menuButton"><x-icon name="menu" :size="20" />Menu</button>
    </nav>
    <div id="mobile-menu" class="sidebar fixed inset-0 z-40 overflow-y-auto md:hidden" x-show="menu" x-cloak role="dialog" aria-modal="true" aria-label="Menu">
        <div class="flex items-center justify-between px-5 pt-4">
            <span class="wordmark">Harmonic</span>
            <button type="button" class="btn btn-ghost" @click="closeMenu" aria-label="Close menu" x-ref="menuClose"><x-icon name="x" :size="20" /></button>
        </div>
        <div class="px-5">@include('partials.org-switcher')</div>
        @include('partials.nav')
    </div>
    <x-toast />
</body>
</html>
