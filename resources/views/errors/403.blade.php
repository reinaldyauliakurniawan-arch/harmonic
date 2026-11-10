<x-guest title="No access">
    <h1 class="title">No access</h1>
    <p class="mt-2 text-ink-secondary">{{ $message ?? "You don't have access to this." }}</p>
    <a href="{{ url('/home') }}" class="btn mt-6">Go to Home</a>
</x-guest>
