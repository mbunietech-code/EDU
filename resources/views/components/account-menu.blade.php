{{-- The signed-in person's button in the top bar: Profile, Settings (email and password) and Sign out. --}}
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
            Profile
        </a>
        <a href="{{ route('user.settings') }}" role="menuitem"
            class="flex items-center gap-2.5 px-4 py-2 text-gray-700 hover:bg-gray-50 hover:text-gray-900 {{ request()->routeIs('user.settings*') ? 'bg-gray-50 font-medium text-gray-900' : '' }}">
            <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            Settings
            <span class="ml-auto text-xs text-gray-400">Email &amp; password</span>
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
