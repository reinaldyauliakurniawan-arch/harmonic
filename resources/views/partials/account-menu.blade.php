<details class="relative">
    <summary class="flex min-h-11 cursor-pointer list-none items-center gap-2 rounded-[8px] px-2 hover:bg-hover" aria-label="Account menu">
        <x-avatar :member="$me" />
        <span class="rail-hide hidden min-w-0 flex-1 truncate text-small md:inline">{{ auth()->user()->name }}</span>
        <x-icon name="chevron-down" :size="14" class="rail-hide hidden text-ink-muted md:inline" />
    </summary>
    <div class="panel float absolute z-30 w-56 p-1 {{ $up ? 'bottom-full left-0 mb-1' : 'right-0 top-full mt-1' }}">
        <a href="{{ route('profile.edit') }}" class="menu-item"><x-icon name="user" />Profile</a>
        @if (auth()->user()->is_super_admin)<a href="{{ route('admin.index') }}" class="menu-item"><x-icon name="settings" />Super Admin</a>@endif
        <form method="POST" action="{{ route('logout') }}">@csrf<button class="menu-item w-full text-left"><x-icon name="log-out" />Sign out</button></form>
    </div>
</details>
