<x-app title="People">
    <x-page-header title="People" description="Invite people, manage Org Admins, and hand over work when someone leaves." />
    <x-link-once :links="session('links', [])" :kind="session('link_kind', 'invite')" />
    @foreach ($seatSkipped as $log)
        <p class="banner banner-brass mb-3">{{ $log->actor?->name ?? 'Someone' }} joined on {{ \App\Services\Calendar::formatDate($log->created_at) }}. Seat was already filled, so it wasn't assigned. Give them a seat in <a class="link" href="{{ route('org.formation.index') }}">Winning Formation</a>.</p>
    @endforeach
    @if (session('already'))<p class="banner mb-4">Already a member: {{ implode(', ', session('already')) }}</p>@endif
    <x-errors />

    <details class="mb-10" @if ($errors->any()) open @endif>
        <summary class="btn btn-primary list-none"><x-icon name="plus" />Invite people</summary>
        <form method="POST" action="{{ route('org.people.invite') }}" class="panel mt-3 grid max-w-3xl gap-4 p-4 md:grid-cols-[1fr_220px]" novalidate>
            @csrf
            <x-field name="emails" label="Emails" type="textarea" rows="2" placeholder="one@example.com, two@example.com" help="Up to 20, separated by commas or new lines." class="md:row-span-2" />
            <x-field name="seat_id" label="Seat (optional)" type="select" :options="['' => 'No seat'] + $vacant->all()" help="Vacant seats only." />
            <div class="md:col-start-2">
                <label class="flex min-h-11 items-center gap-2"><input type="hidden" name="make_org_admin" value="0"><input type="checkbox" name="make_org_admin" value="1" class="h-4 w-4 accent-brass"> Make Org Admin</label>
            </div>
            <div><button class="btn btn-primary">Create invitation links</button></div>
        </form>
    </details>

    @if ($invitations->isNotEmpty())
        <section class="mb-10">
            <h2 class="heading mb-3">Open invitations</h2>
            <div class="relative overflow-x-auto"><table class="tbl">
                <thead><tr><th>Email</th><th>Seat</th><th>Expires</th><th><span class="sr-only">Actions</span></th></tr></thead>
                <tbody>
                @foreach ($invitations as $inv)
                    <tr>
                        <td>{{ $inv->email }} @if ($inv->make_org_admin)<span class="badge">Org Admin</span>@endif</td>
                        <td>{{ $inv->seat?->title ?? '–' }}</td>
                        <td>{{ $inv->expires_at->isPast() ? 'Expired' : \App\Services\Calendar::formatDate($inv->expires_at) }}</td>
                        <td class="whitespace-nowrap">
                            <form method="POST" action="{{ route('org.people.resend', $inv) }}" class="inline">@csrf<button class="btn btn-sm">New link</button></form>
                            <x-confirm :action="route('org.people.revoke', $inv)" method="DELETE" :message="'Revoke the invitation for '.$inv->email.'?'" label="Revoke" />
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        </section>
    @endif

    <section>
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <h2 class="heading">Members</h2>
            <div class="border-b border-line">
                <a class="tab" href="{{ route('org.people.index') }}" @if ($status === 'active') aria-current="page" @endif>Active</a>
                <a class="tab" href="{{ route('org.people.index', ['status' => 'departed']) }}" @if ($status === 'departed') aria-current="page" @endif>Departed</a>
            </div>
        </div>
        @if ($members->isEmpty())
            <x-empty :heading="'No '.$status.' members'" :text="$status === 'departed' ? 'People you deactivate move here. Their past work keeps their name.' : 'Invite people above to add them.'" />
        @else
            <div class="relative overflow-x-auto"><table class="tbl">
                <thead><tr><th>Name</th><th>Email</th><th>Seats</th><th>Joined</th><th><span class="sr-only">Actions</span></th></tr></thead>
                <tbody>
                @foreach ($members as $m)
                    <tr>
                        <td class="font-medium">{{ $m->name }} @if ($m->is_org_admin)<span class="badge">Org Admin</span>@endif</td>
                        <td>{{ $m->user->email }}</td>
                        <td>{{ $m->seats->pluck('title')->join(', ') ?: '–' }}</td>
                        <td>{{ \App\Services\Calendar::formatDate($m->joined_at) }}</td>
                        <td class="whitespace-nowrap">
                            @if ($m->status === 'active')
                                <details class="inline-block text-right">
                                    <summary class="icon-btn list-none" aria-label="Actions for {{ $m->name }}"><x-icon name="ellipsis" /></summary>
                                    <div class="panel mt-1 w-52 p-1 text-left">
                                        <form method="POST" action="{{ route('org.people.admin', $m) }}">@csrf<button class="menu-item w-full">{{ $m->is_org_admin ? 'Remove Org Admin' : 'Make Org Admin' }}</button></form>
                                        <form method="POST" action="{{ route('org.people.reset-link', $m) }}">@csrf<button class="menu-item w-full">Create reset link</button></form>
                                        <a class="menu-item text-off-track" href="{{ route('org.handover.show', ['type' => 'deactivate', 'member' => $m->id, 'back' => route('org.people.index')]) }}">Deactivate</a>
                                    </div>
                                </details>
                            @elseif (! $m->user->anonymized_at)
                                <form method="POST" action="{{ route('org.people.reactivate', $m) }}" class="inline">@csrf<button class="btn btn-sm">Reactivate</button></form>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            {{ $members->links() }}
        @endif
    </section>
</x-app>
