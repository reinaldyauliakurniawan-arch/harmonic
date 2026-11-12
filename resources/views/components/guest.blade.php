@props(['title' => null])
@php
    // The six sections and their taglines, exactly as PRD section 3 names them.
    $sections = [
        ['Direction', 'The score everyone plays from'],
        ['Measurements', 'The tempo keeping you in rhythm'],
        ['Team', 'The musicians in their right seats'],
        ['Resolution', 'Detecting and resolving dissonance'],
        ['Procedures', 'How all the parts arrange together'],
        ['Discipline', 'The actual performance'],
    ];
@endphp
<!doctype html>
<html lang="en" @if (in_array(request()->cookie('theme'), ['light', 'dark'], true)) data-theme="{{ request()->cookie('theme') }}" @endif>
<head><x-head :title="$title" /></head>
<body class="min-h-screen lg:grid lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)]">
    <header class="sidebar flex flex-col px-6 py-6 lg:border-r lg:sticky lg:top-0 lg:h-screen lg:px-12 lg:py-12">
        <p class="wordmark text-[28px] leading-8 lg:text-[36px] lg:leading-10">Harmonic</p>
        <p class="mt-2 max-w-[34ch] text-small text-ink-secondary lg:mt-4 lg:text-body">Run your organization on six sections, one week at a time.</p>
        <ol class="mt-auto hidden lg:block" aria-label="The six sections">
            @foreach ($sections as [$name, $tagline])
                <li class="flex items-baseline justify-between gap-6 border-t border-line py-3.5 last:border-b">
                    <span class="font-medium">{{ $name }}</span>
                    <span class="text-right text-small text-ink-muted">{{ $tagline }}</span>
                </li>
            @endforeach
        </ol>
    </header>
    <main id="main" class="flex justify-center px-4 py-8 lg:min-h-screen lg:items-center lg:px-10">
        <div class="box w-full max-w-[420px] md:p-8">{{ $slot }}</div>
    </main>
    <x-toast />
</body>
</html>
