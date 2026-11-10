<x-guest title="Sign in">
    <h1 class="title">Sign in</h1>
    <p class="mt-1 text-ink-secondary">Use the email your Org Admin invited, or the one you signed up with.</p>
    <form method="POST" action="{{ url('/login') }}" class="mt-6 space-y-4" novalidate>
        @csrf
        <x-field name="email" label="Email" type="email" autocomplete="email" required autofocus />
        <x-field name="password" label="Password" type="password" autocomplete="current-password" required />
        <div class="flex flex-wrap items-center justify-between gap-x-4">
            <label class="flex min-h-11 items-center gap-2 text-small"><input type="checkbox" name="remember" value="1" class="h-4 w-4 accent-brass"> Remember me</label>
            <a class="link inline-flex min-h-11 items-center text-small" href="{{ route('password.request') }}">Forgot your password?</a>
        </div>
        <button class="btn btn-primary w-full">Sign in</button>
    </form>
    <p class="mt-6 border-t border-line pt-5 text-small text-ink-secondary">New here? <a class="link" href="{{ route('signup') }}">Create an organization</a></p>
</x-guest>
