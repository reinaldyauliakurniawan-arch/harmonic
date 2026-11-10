@props(['title' => null])
<!doctype html>
<html lang="en" @if (in_array(request()->cookie('theme'), ['light', 'dark'], true)) data-theme="{{ request()->cookie('theme') }}" @endif>
<head><x-head :title="$title" /></head>
<body class="min-h-screen">
    <header class="flex min-h-14 flex-wrap items-center justify-between gap-x-3 border-b border-line px-4 py-2 md:px-8">
        <a href="{{ url('/home') }}" class="label">The Harmonic System</a>
        <nav class="-mx-3 flex flex-wrap items-center gap-1 md:mx-0" aria-label="Account">
            @if (auth()->user()?->is_super_admin)
                <a href="{{ route('admin.index') }}" class="btn btn-ghost btn-sm">Organizations</a>
                <a href="{{ route('admin.maintenance') }}" class="btn btn-ghost btn-sm">Maintenance</a>
            @endif
            <a href="{{ route('profile.edit') }}" class="btn btn-ghost btn-sm">Profile</a>
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn btn-ghost btn-sm">Sign out</button></form>
        </nav>
    </header>
    <main id="main" class="max-w-[1200px] px-4 py-6 md:px-8 md:py-8">{{ $slot }}</main>
    <x-toast />
</body>
</html>
