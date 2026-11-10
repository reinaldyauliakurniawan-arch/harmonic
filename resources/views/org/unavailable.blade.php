<x-guest title="{{ $organization->name }}">
    <h1 class="title">{{ $organization->name }}</h1>
    @if ($organization->status === 'suspended')
        <p class="mt-2 text-ink-secondary">This organization is suspended. Contact your administrator.</p>
    @else
        <p class="mt-2 text-ink-secondary">Your organization is waiting for approval. Check back here once a platform admin approves it.</p>
    @endif
    <form method="POST" action="{{ route('logout') }}" class="mt-6">@csrf<button class="btn">Sign out</button></form>
</x-guest>
