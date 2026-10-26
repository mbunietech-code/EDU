{{-- The signed-in person's button in the top bar: Profile & settings (name, email, password) and Sign out. --}}
@php($me = auth()->user())
<div class="relative" x-data="{ open: false }" @keydown.escape.window="open = false" @click.outside="open = false">
    <button type="button" @click="open = !open" :aria-expanded="open.toString()" aria-haspopup="menu"
        class="inline-flex items-center gap-2 rounded-lg py-1 pl-1 pr-2 text-sm text-gray-700 hover:bg-gray-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-500">
        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-indigo-600 text-xs font-semibold text-white" aria-hidden="true">
            {{ strtoupper(substr($me->name, 0, 1)) }}
        </span>
        <span class="hidden max-w-[10rem] truncate md:inline">{{ $me->name }}</span>
        <svg class="h-4 w-4 text-gray-400 transition-transform" :class="open && 'rotate-180'" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
        </svg>
        <span class="sr-only">Account menu</span>
    </button>

    <div x-show="open" x-transition.origin.top.right x-cloak role="menu" aria-label="Account"
        class="absolute right-0 z-40 mt-2 w-60 overflow-hidden rounded-xl bg-white py-1 text-sm shadow-lg ring-1 ring-gray-200">
        <div class="border-b border-gray-100 px-4 py-3">
            <p class="truncate font-semibold text-gray-900">{{ $me->name }}</p>
            <p class="truncate text-xs text-gray-500">{{ $me->email }}</p>
        </div>
        <a href="{{ route('user.profile.edit') }}" role="menuitem"
            class="flex items-center gap-2.5 px-4 py-2 text-gray-700 hover:bg-gray-50 hover:text-gray-900 {{ request()->routeIs('user.profile.*') ? 'bg-gray-50 font-medium text-gray-900' : '' }}">
            <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
            </svg>
            <span class="flex-1">Profile &amp; settings</span>
            <span class="text-xs text-gray-400">Email, password</span>
        </a>
        @if ($me->is_admin && ! request()->routeIs('admin.*'))
            <a href="{{ route('admin.dashboard') }}" role="menuitem" class="flex items-center gap-2.5 px-4 py-2 text-gray-700 hover:bg-gray-50 hover:text-gray-900">
                <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25a2.25 2.25 0 01-2.25-2.25v-2.25z" />
                </svg>
                Admin panel
            </a>
        @endif
        <div class="my-1 border-t border-gray-100"></div>
        <form method="POST" action="{{ route('logout') }}" class="m-0">
            @csrf
            <button type="submit" role="menuitem" class="flex w-full items-center gap-2.5 px-4 py-2 text-left text-gray-700 hover:bg-gray-50 hover:text-red-600">
                <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6A2.25 2.25 0 005.25 5.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
                </svg>
                Sign out
            </button>
        </form>
    </div>
</div>
