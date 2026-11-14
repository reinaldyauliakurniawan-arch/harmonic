<x-plain title="Your organizations">
    @if ($active->isNotEmpty())
        <x-page-header title="Your organizations" description="Choose where to work. You can switch from the top bar at any time." />
        <div class="rows max-w-xl">
            @foreach ($active as $m)
                <a href="{{ route('org.home', ['organization' => $m->organization->slug]) }}" class="flex min-h-12 items-center justify-between px-2 hover:bg-hover">
                    <span class="font-medium">{{ $m->organization->name }}</span>
                    @if ($m->is_org_admin)<span class="badge">Org Admin</span>@endif
                </a>
            @endforeach
        </div>
    @elseif ($pending->isNotEmpty())
        @foreach ($pending as $m)
            <x-page-header :title="$m->organization->name" />
            @if ($m->organization->status === 'pending')
                <p class="text-ink-secondary">Your organization is waiting for approval. Check back here once a platform admin approves it.</p>
            @else
                <p class="text-ink-secondary">This organization is suspended. Contact your administrator.</p>
            @endif
        @endforeach
    @else
        <x-empty heading="You're not part of an organization yet" text="Ask an admin for an invitation link, or create your own organization.">
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn btn-primary">Sign out to create one</button></form>
        </x-empty>
    @endif
</x-plain>
