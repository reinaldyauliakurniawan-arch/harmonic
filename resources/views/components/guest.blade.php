@props(['title' => null])
<!doctype html>
<html lang="en" @if (in_array(request()->cookie('theme'), ['light', 'dark'], true)) data-theme="{{ request()->cookie('theme') }}" @endif>
<head><x-head :title="$title" /></head>
<body class="min-h-screen">
    <main class="mx-auto w-full max-w-[440px] px-4 py-12">
        <p class="label mb-8 text-ink-secondary">The Harmonic System</p>
        {{ $slot }}
    </main>
    <x-toast />
</body>
</html>
