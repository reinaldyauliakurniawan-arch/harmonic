@props(['member'])
<span {{ $attributes->merge(['class' => 'inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-hover text-caption font-medium text-ink']) }} title="{{ $member?->name }}" aria-hidden="true">{{ $member?->initials() ?? '–' }}</span>
