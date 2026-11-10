<x-plain title="Maintenance">
    <x-page-header title="Maintenance" description="Run migrations and clear caches after an upload. The server has no shell." />
    <dl class="mb-8 grid max-w-xl grid-cols-2 gap-y-2">
        <dt class="text-ink-secondary">PHP version</dt><dd>{{ $php }}</dd>
        <dt class="text-ink-secondary">Pending migrations</dt><dd>{{ $pending->count() }}</dd>
    </dl>
    @if ($pending->isNotEmpty())
        <ul class="mb-4 list-disc pl-5 text-small">@foreach ($pending as $p)<li>{{ $p }}</li>@endforeach</ul>
    @endif
    <div class="flex flex-wrap gap-2">
        <form method="POST" action="{{ route('admin.migrate') }}">@csrf<button class="btn btn-primary" @disabled($pending->isEmpty())>Run migrations</button></form>
        <form method="POST" action="{{ route('admin.clear') }}">@csrf<button class="btn">Clear caches</button></form>
    </div>
    @if (session('output'))<pre class="panel mt-4 overflow-x-auto p-4 text-small">{{ session('output') }}</pre>@endif

    <h2 class="heading mb-3 mt-12">Scheduler</h2>
    @if ($runs->isEmpty())
        <p class="text-ink-secondary">No job has run yet. Jobs run when members use the app, at most every 10 minutes.</p>
    @else
        <div class="relative overflow-x-auto"><table class="tbl max-w-3xl">
            <thead><tr><th>Job</th><th>Organization</th><th>Last run (UTC)</th></tr></thead>
            <tbody>@foreach ($runs as $r)<tr><td>{{ $r->job }}</td><td>{{ $r->organization_id ? ($orgNames[$r->organization_id] ?? '–') : 'Platform' }}</td><td>{{ $r->last_run_at->format('D, j M Y H:i') }}</td></tr>@endforeach</tbody>
        </table></div>
    @endif
</x-plain>
