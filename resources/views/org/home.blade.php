<x-app title="Home">
    <x-page-header title="Home" description="What needs you this week." />
    @php
        extract($blocks);
        $hasTime = $meetings || $todayEvents->isNotEmpty();
        $figures = array_filter([
            [$tasks->count(), \Illuminate\Support\Str::plural('task', $tasks->count()).' overdue or due this week', route('org.tasks.index')],
            [$numbers->count(), 'of your numbers to enter', route('org.numbers.index')],
            isset($leader['metrics']) ? [count($leader['metrics']), 'team metrics off track or missing', route('org.metrics.index')] : null,
            [count($meetings ?: []), \Illuminate\Support\Str::plural('meeting', count($meetings ?: [])).' coming up', route('org.meetings.index')],
        ]);
    @endphp
    <ul class="mb-10 grid gap-px overflow-hidden rounded-[12px] bg-line shadow-[var(--shadow-group)] {{ count($figures) === 4 ? 'grid-cols-2 md:grid-cols-4' : 'grid-cols-1 sm:grid-cols-3' }}" aria-label="This week at a glance">
        @foreach ($figures as [$n, $label, $href])
            <li class="bg-surface"><a href="{{ $href }}" class="flex h-full flex-col gap-1 px-4 py-4 transition-colors hover:bg-hover">
                <span class="figure {{ $n ? '' : 'text-ink-muted' }}">{{ $n }}</span>
                <span class="text-small text-ink-secondary">{{ $label }}</span>
            </a></li>
        @endforeach
    </ul>
    <div class="grid gap-x-12 {{ $hasTime ? 'lg:grid-cols-[minmax(0,1fr)_320px]' : '' }}">
    <div class="min-w-0">
    @if ($checklist)
        <section class="card mb-6">
            @php $setupDone = collect($checklist)->filter(fn ($i) => $i[1])->count(); @endphp
            <h2 class="heading flex items-center justify-between gap-3">Set up your organization <span class="text-small font-normal text-ink-secondary">{{ $setupDone }} of {{ count($checklist) }} done</span></h2>
            <div class="flush h-1 bg-hover" role="progressbar" aria-label="Setup progress" aria-valuemin="0" aria-valuemax="{{ count($checklist) }}" aria-valuenow="{{ $setupDone }}"><svg class="block h-1 w-full" aria-hidden="true"><rect class="fill-ink" x="0" y="0" height="4" width="{{ round($setupDone / count($checklist) * 100) }}%" /></svg></div>
            <ol class="rows">
                @foreach ($checklist as [$label, $done, $route])
                    <li class="flex min-h-12 items-center gap-3">
                        <span class="{{ $done ? 'cell-on' : 'text-ink-muted' }}">@if ($done)<x-icon name="check" label="Done" />@else<span class="inline-block h-4 w-4 rounded-full border border-bar" aria-label="Not done" role="img"></span>@endif</span>
                        @if (Route::has($route))<a href="{{ route($route) }}" class="{{ $done ? 'text-ink-secondary' : 'font-medium' }} hover:underline">{{ $label }}</a>@else<span>{{ $label }}</span>@endif
                    </li>
                @endforeach
            </ol>
        </section>
    @endif
    @include('org.home-blocks', ['part' => 'main'])
    </div>
    @if ($hasTime)<aside class="order-first min-w-0 lg:order-none" aria-label="Your time">@include('org.home-blocks', ['part' => 'time'])</aside>@endif
    </div>
@if (! $checklist && ! $banners && $numbers->isEmpty() && $tasks->isEmpty() && $stale->isEmpty() && ! $meetings && ! $leader && $todayEvents->isEmpty())
    <x-empty heading="Nothing needs you right now" text="Your tasks, numbers, and meetings show up here when they need attention." />
@endif
</x-app>
