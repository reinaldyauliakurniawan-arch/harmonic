<x-plain title="Organizations">
    <x-page-header title="Organizations" description="Approve new organizations and manage their status. You can't open a tenant's data from here." />
    <x-link-once :links="session('link') ? [session('link_for') => session('link')] : []" :kind="str_contains((string) session('link'), 'reset-password') ? 'reset' : 'invite'" />
    <x-errors />
    <div class="mb-6 flex flex-wrap gap-1 border-b border-line" role="tablist">
        @foreach (['' => 'All', 'pending' => 'Pending', 'active' => 'Active', 'suspended' => 'Suspended', 'deleted' => 'Deleted'] as $k => $l)
            <a href="{{ route('admin.index', $k ? ['status' => $k] : []) }}" class="tab" @if ((string) $status === $k) aria-current="page" @endif>{{ $l }}</a>
        @endforeach
    </div>
    @if ($orgs->isEmpty())
        <x-empty heading="No organizations here" text="New self-signups appear under Pending." />
    @else
        <div class="relative overflow-x-auto">
            <table class="tbl">
                <thead><tr><th>Name</th><th>Status</th><th class="num">Members</th><th>Created</th><th>Last activity</th><th><span class="sr-only">Actions</span></th></tr></thead>
                <tbody>
                    @foreach ($orgs as $o)
                        <tr>
                            <td class="font-medium">{{ $o->name }}<div class="text-caption text-ink-muted">{{ $o->signup_source === 'self' ? 'Self-signup' : 'Created by admin' }}</div></td>
                            <td>{{ ucfirst($o->status) }}@if ($o->purge_after)<div class="text-caption text-ink-muted">Purged after {{ \App\Services\Calendar::formatDate($o->purge_after) }}</div>@endif</td>
                            <td class="num">{{ $o->members_count }}</td>
                            <td>{{ \App\Services\Calendar::formatDate($o->created_at) }}</td>
                            <td>{{ $o->last_activity ? \App\Services\Calendar::formatDate(\Carbon\Carbon::parse($o->last_activity)) : '–' }}</td>
                            <td class="whitespace-nowrap">
                                @foreach (['pending' => ['approve' => 'Approve', 'reject' => 'Reject'], 'active' => ['suspend' => 'Suspend', 'delete' => 'Delete'], 'suspended' => ['reactivate' => 'Reactivate', 'delete' => 'Delete'], 'deleted' => ['restore' => 'Restore']][$o->status] as $action => $label)
                                    @if (in_array($action, ['reject', 'delete', 'suspend'], true))
                                        <x-confirm :action="route('admin.transition', [$o, $action])" :message="$label.' '.$o->name.'?'" :label="$label" />
                                    @else
                                        <form method="POST" action="{{ route('admin.transition', [$o, $action]) }}" class="inline">@csrf<button class="btn btn-sm">{{ $label }}</button></form>
                                    @endif
                                @endforeach
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $orgs->links() }}
    @endif

    <div class="mt-12 grid gap-10 md:grid-cols-2">
        <section>
            <h2 class="heading mb-1">Create an organization</h2>
            <p class="mb-4 text-small text-ink-secondary">It opens at once. You get an invitation link for its first admin.</p>
            <form method="POST" action="{{ route('admin.store') }}" class="space-y-4" novalidate>
                @csrf
                <x-field name="name" label="Organization name" required />
                <x-field name="email" label="First admin's email" type="email" required />
                <button class="btn btn-primary">Create organization</button>
            </form>
        </section>
        <section>
            <h2 class="heading mb-1">Password reset link</h2>
            <p class="mb-4 text-small text-ink-secondary">For someone with no Org Admin to ask. The link works for 60 minutes.</p>
            <form method="POST" action="{{ route('admin.reset-link') }}" class="space-y-4" novalidate>
                @csrf
                <x-field name="email" label="Account email" type="email" required />
                <button class="btn">Create reset link</button>
            </form>
        </section>
    </div>
</x-plain>
