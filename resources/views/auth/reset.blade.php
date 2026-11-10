<x-guest title="Choose a new password">
    <h1 class="title">Choose a new password</h1>
    <form method="POST" action="{{ route('password.update') }}" class="mt-6 space-y-4" novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-field name="email" label="Email" type="email" :value="$email" readonly />
        <x-field name="password" label="New password" type="password" autocomplete="new-password" help="At least 10 characters, with letters and numbers." required />
        <x-field name="password_confirmation" label="Repeat password" type="password" autocomplete="new-password" required />
        <button class="btn btn-primary w-full">Change password</button>
    </form>
</x-guest>
