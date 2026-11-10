<x-guest title="Sign in">
    <h1 class="title">Sign in</h1>
    <form method="POST" action="{{ url('/login') }}" class="mt-6 space-y-4" novalidate>
        @csrf
        <x-field name="email" label="Email" type="email" autocomplete="email" required autofocus />
        <x-field name="password" label="Password" type="password" autocomplete="current-password" required />
        <label class="flex min-h-11 items-center gap-2"><input type="checkbox" name="remember" value="1" class="h-4 w-4 accent-brass"> Remember me</label>
        <button class="btn btn-primary w-full">Sign in</button>
    </form>
    <p class="mt-6 text-small text-ink-secondary"><a class="link" href="{{ route('password.request') }}">Forgot your password?</a></p>
    <p class="mt-2 text-small text-ink-secondary">New here? <a class="link" href="{{ route('signup') }}">Create an organization</a></p>
</x-guest>
