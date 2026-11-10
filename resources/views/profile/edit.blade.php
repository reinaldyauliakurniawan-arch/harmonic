<x-plain title="Profile">
    <x-page-header title="Profile" description="Your name, sign-in details, and display preference." />
    <x-errors />
    <div class="grid max-w-xl gap-10">
        <section>
            <h2 class="heading mb-4">Account</h2>
            <form method="POST" action="{{ route('profile.update') }}" class="space-y-4" novalidate>
                @csrf @method('PUT')
                <x-field name="name" label="Name" :value="$user->name" required />
                <x-field name="email" label="Email" type="email" :value="$user->email" required />
                <x-field name="current_password" label="Current password" type="password" help="Needed only when you change your email." autocomplete="current-password" />
                <button class="btn btn-primary">Save changes</button>
            </form>
        </section>
        <section>
            <h2 class="heading mb-4">Password</h2>
            <form method="POST" action="{{ route('profile.password') }}" class="space-y-4" novalidate>
                @csrf @method('PUT')
                <x-field name="current_password" label="Current password" type="password" autocomplete="current-password" required />
                <x-field name="password" label="New password" type="password" autocomplete="new-password" help="At least 10 characters, with letters and numbers. Other devices are signed out." required />
                <x-field name="password_confirmation" label="Repeat new password" type="password" autocomplete="new-password" required />
                <button class="btn">Change password</button>
            </form>
        </section>
        <section>
            <h2 class="heading mb-4">Theme</h2>
            <form method="POST" action="{{ route('profile.theme') }}" class="flex flex-wrap items-end gap-3">
                @csrf @method('PUT')
                <x-field name="theme" label="Appearance" type="select" :options="['system' => 'System', 'light' => 'Light', 'dark' => 'Dark']" :value="request()->cookie('theme', 'system')" class="w-48" />
                <button class="btn">Save theme</button>
            </form>
        </section>
        <section>
            <h2 class="heading mb-2">Leave or delete account</h2>
            @if ($lastAdminSomewhere)
                <p class="text-ink-secondary">You're the last Org Admin of an organization. Make someone else an admin first.</p>
            @elseif ($memberships->isEmpty())
                <p class="text-ink-secondary">You belong to no organization. Your account has no work to hand over.</p>
            @else
                <p class="mb-3 text-ink-secondary">Leave each organization below. Your open work goes through Handover first. When you leave the last one, your account is anonymized: your name becomes "Former member" and your records stay.</p>
                <div class="rows">
                    @foreach ($memberships as $m)
                        <div class="flex min-h-12 items-center justify-between gap-3 px-2">
                            <span>{{ $m->organization->name }}</span>
                            @if ($m->organization->status === 'active')
                                <a class="btn btn-sm btn-danger" href="{{ route('org.handover.show', ['organization' => $m->organization->slug, 'type' => 'account', 'member' => $m->id, 'back' => route('profile.edit')]) }}">Leave {{ $m->organization->name }}</a>
                            @else
                                <span class="text-small text-ink-secondary">Not active</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
</x-plain>
