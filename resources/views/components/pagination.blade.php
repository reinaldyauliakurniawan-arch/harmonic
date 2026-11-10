@if ($paginator->hasPages())
    <nav class="mt-4 flex items-center justify-between text-small" aria-label="Pagination">
        <span class="text-ink-secondary">Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}</span>
        <span class="flex gap-2">
            @if ($paginator->onFirstPage())<span class="btn btn-sm" aria-disabled="true">Previous</span>@else<a class="btn btn-sm" href="{{ $paginator->previousPageUrl() }}" rel="prev">Previous</a>@endif
            @if ($paginator->hasMorePages())<a class="btn btn-sm" href="{{ $paginator->nextPageUrl() }}" rel="next">Next</a>@else<span class="btn btn-sm" aria-disabled="true">Next</span>@endif
        </span>
    </nav>
@endif
