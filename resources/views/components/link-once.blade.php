@props(['links', 'kind' => 'invite'])
@if ($links)
    <div class="banner banner-brass mb-6" role="status">
        <p class="font-medium">{{ $kind === 'reset' ? 'Password reset link' : 'Invitation links' }}: copy {{ count($links) === 1 ? 'it' : 'them' }} now. They won't be shown again.</p>
        <p class="help">Send each link to its person yourself. {{ $kind === 'reset' ? 'The link works for 60 minutes.' : 'Links expire after 7 days.' }}</p>
        <ul class="mt-3 space-y-2">
            @foreach ($links as $email => $url)
                <li><span class="label">{{ $email }}</span><input class="input mt-1 text-small" readonly value="{{ $url }}" aria-label="Link for {{ $email }}"></li>
            @endforeach
        </ul>
    </div>
@endif
