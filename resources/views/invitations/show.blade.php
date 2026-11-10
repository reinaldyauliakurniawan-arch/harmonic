<x-guest title="Join {{ $inv->org->name }}">
    <h1 class="title">Join {{ $inv->org->name }}</h1>
    <p class="mt-1 text-ink-secondary">This invitation is for {{ $inv->email }}.</p>
    @if ($user)
        @if (strtolower($user->email) === $inv->email)
            <form method="POST" action="{{ route('invitations.accept', $token) }}" class="mt-6">@csrf<button class="btn btn-primary">Join {{ $inv->org->name }}</button></form>
        @else
            <p class="banner banner-off mt-6">You're signed in as {{ $user->email }}. Sign out, then open this link again.</p>
            <form method="POST" action="{{ route('logout') }}" class="mt-4">@csrf<button class="btn">Sign out</button></form>
        @endif
    @elseif ($exists)
        <p class="mt-6">You already have an account. Sign in, and you'll come back here to accept.</p>
        <a href="{{ route('login') }}" class="btn btn-primary mt-4">Sign in</a>
    @else
        <form method="POST" action="{{ route('invitations.accept', $token) }}" class="mt-6 space-y-4" novalidate>
            @csrf
            <x-field name="email_locked" label="Email" :value="$inv->email" readonly />
            <x-field name="name" label="Your name" autocomplete="name" required />
            <x-field name="password" label="Password" type="password" autocomplete="new-password" help="At least 10 characters, with letters and numbers." required />
            <x-field name="password_confirmation" label="Repeat password" type="password" autocomplete="new-password" required />
            <button class="btn btn-primary w-full">Create account and join</button>
        </form>
    @endif
</x-guest>
