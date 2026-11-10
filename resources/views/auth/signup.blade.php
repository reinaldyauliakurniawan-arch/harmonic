<x-guest title="Create your organization">
    <h1 class="title">Create your organization</h1>
    <p class="mt-1 text-ink-secondary">A platform admin approves new organizations before they open.</p>
    <form method="POST" action="{{ url('/signup') }}" class="mt-6 space-y-4" novalidate>
        @csrf
        <x-field name="name" label="Your name" autocomplete="name" required />
        <x-field name="email" label="Email" type="email" autocomplete="email" required />
        <x-field name="password" label="Password" type="password" autocomplete="new-password" help="At least 10 characters, with letters and numbers." required />
        <x-field name="password_confirmation" label="Repeat password" type="password" autocomplete="new-password" required />
        <x-field name="organization" label="Organization name" required />
        <button class="btn btn-primary w-full">Create organization</button>
    </form>
    <p class="mt-6 text-small text-ink-secondary">Have an account? <a class="link" href="{{ route('login') }}">Sign in</a></p>
</x-guest>
