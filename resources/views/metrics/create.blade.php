<x-app width="reading" title="Add a metric">
    <x-page-header title="Add a metric" :description="'For '.$f->seat($team)?->teamLabel().'.'" />
    @if ($activeCount >= 15)<p class="help mb-4">Most teams track 5–15 numbers.</p>@endif
    @include('metrics.form', ['metric' => null])
</x-app>
