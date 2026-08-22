@props(['title' => config('app.name', 'MbunieEduHub'), 'header' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>[x-cloak]{display:none!important;}</style>
    <title>{{ $title ? $title . ' | ' . config('app.name', 'MbunieEduHub') : config('app.name', 'MbunieEduHub') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-gray-100">
    <div class="min-h-full" x-data="{ sidebarOpen: false, consultationOpen: false, consultationSub: null }">
        <aside class="fixed inset-y-0 left-0 z-40 w-64 bg-gray-900 transform transition-transform lg:translate-x-0"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'">
            <div class="flex h-16 items-center justify-between gap-2 border-b border-gray-800 px-6">
                <div class="flex items-center gap-2">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-600 font-bold text-white">M</span>
                    <span class="text-base font-bold tracking-tight text-white">MbunieEduHub</span>
                </div>
                <button type="button" class="lg:hidden text-gray-400 hover:text-white" @click="sidebarOpen = false">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <nav class="mt-4 space-y-1 px-3">
                <x-user.sidebar-link :route="route('dashboard')" :active="request()->routeIs('dashboard')" label="Dashboard">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
                </x-user.sidebar-link>

                <x-user.sidebar-link :route="route('public.products.index')" :active="request()->routeIs('public.products.*')" label="Products">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 2.25l9 5.25-3.75 2.25L12 15.75l-5.25-3L3 7.5l9-5.25z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 2.25v13.5" />
                </x-user.sidebar-link>

                <x-user.sidebar-link :route="route('user.tools.index')" :active="request()->routeIs('user.tools.*')" label="Research Tools">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 11-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 004.486-6.336l-3.276 3.277a3.004 3.004 0 01-2.25-2.25l3.276-3.276a4.5 4.5 0 00-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26" />
                </x-user.sidebar-link>

                <x-user.sidebar-link :route="route('public.scholarships.index')" :active="request()->routeIs('public.scholarships.*')" label="Scholarships">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443" />
                </x-user.sidebar-link>

                @php($consultation = [
                    ['id' => 'thesis', 'name' => 'Thesis Writing', 'items' => ['Chapter writing', 'Editing', 'Formatting', 'Referencing']],
                    ['id' => 'data', 'name' => 'Data analysis', 'items' => ['SPSS', 'Stata', 'R', 'Python']],
                    ['id' => 'pub', 'name' => 'Publication', 'items' => ['Journal selection', 'Submission', 'Proofreading', 'Indexing']],
                ])

                <div>
                    <button type="button" @click="consultationOpen = !consultationOpen"
                        class="group flex w-full items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-medium text-gray-300 hover:bg-gray-800 hover:text-white">
                        <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18 6v6a6 6 0 01-12 0V6a6 6 0 1112 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 12v6m-3-3h6" />
                        </svg>
                        <span class="flex-1">Consultation</span>
                        <svg class="h-4 w-4 shrink-0 transition-transform"
                            :class="consultationOpen ? 'transform rotate-180' : ''" fill="none"
                            viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <div x-show="consultationOpen" x-cloak x-transition class="mt-1 ml-6 space-y-1">
                        @foreach ($consultation as $group)
                            <div>
                                <button type="button"
                                    @click="consultationSub = consultationSub === '{{ $group['id'] }}' ? null : '{{ $group['id'] }}'"
                                    class="flex w-full items-center gap-x-3 rounded-lg px-3 py-2 text-left text-sm font-medium text-gray-300 hover:bg-gray-800 hover:text-white">
                                    <span class="flex-1 text-left">{{ $group['name'] }}</span>
                                    <svg class="h-4 w-4 shrink-0 transition-transform"
                                        :class="consultationSub === '{{ $group['id'] }}' ? 'transform rotate-180' : ''"
                                        fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>

                                <div x-show="consultationSub === '{{ $group['id'] }}'" x-cloak x-transition
                                    class="mt-1 ml-6 space-y-1">
                                    @foreach ($group['items'] as $item)
                                        <a href="{{ route('public.contact') }}"
                                            class="flex items-center gap-x-3 rounded-lg px-3 py-2 text-left text-sm font-medium text-gray-300 hover:bg-gray-800 hover:text-white">
                                            <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-gray-400"></span>
                                            <span class="text-left">{{ $item }}</span>
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <x-user.sidebar-link :route="route('user.subscriptions.index')" :active="request()->routeIs('user.subscriptions.*')" label="My Subscriptions">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </x-user.sidebar-link>

                <x-user.sidebar-link :route="route('user.orders.index')" :active="request()->routeIs('user.orders.*')" label="My Orders">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007z" />
                </x-user.sidebar-link>

                <x-user.sidebar-link :route="route('user.payments.index')" :active="request()->routeIs('user.payments.*')" label="Payments">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5m-19.5 5.25h19.5m-16.5 5.25h13.5" />
                </x-user.sidebar-link>

                <x-user.sidebar-link :route="route('user.chat.index')" :active="request()->routeIs('user.chat.*')" label="Messages" :badge="auth()->user()->unreadChatMessagesCount()">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 9.75a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z" />
                </x-user.sidebar-link>

                <x-user.sidebar-link :route="route('user.notifications.index')" :active="request()->routeIs('user.notifications.*')" label="Notifications"
                    :badge="auth()->user()->unreadNotifications()->count()">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                </x-user.sidebar-link>

                <x-user.sidebar-link :route="route('user.profile.edit')" :active="request()->routeIs('user.profile.*')" label="Profile">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                </x-user.sidebar-link>
            </nav>

            <div class="absolute bottom-0 inset-x-0 border-t border-gray-800 p-4">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-mbui.button variant="ghost" type="submit" class="w-full justify-start !text-gray-300 hover:!bg-gray-800 hover:!text-white">
                        Sign out
                    </x-mbui.button>
                </form>
            </div>
        </aside>

        <div x-show="sidebarOpen" x-transition.opacity @click="sidebarOpen = false" class="fixed inset-0 z-30 bg-gray-900/50 lg:hidden"></div>

        <div class="lg:pl-64">
            <header class="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-gray-200 bg-white px-4 sm:px-6">
                <div class="flex items-center gap-3">
                    <button type="button" class="lg:hidden p-2 rounded-lg text-gray-600 hover:bg-gray-100" @click="sidebarOpen = true">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                        </svg>
                    </button>
                    <div class="flex">
                        <h1 class="text-lg font-semibold text-gray-900">{{ $header ?? $title }}</h1>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <livewire:shared.notification-bell />
                    <a href="{{ route('public.home') }}" class="hidden sm:inline-flex items-center gap-2 text-sm text-gray-600 hover:text-gray-900">
                        <span class="h-8 w-8 rounded-full bg-indigo-600 flex items-center justify-center text-xs font-semibold text-white">
                            {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                        </span>
                        <span class="hidden md:inline">{{ auth()->user()->name }}</span>
                    </a>
                </div>
            </header>

            <main class="p-4 sm:p-6 lg:p-8">
                <x-partials.flash />
                {{ $slot }}
            </main>
        </div>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            document.addEventListener('click', (e) => {
                if (e.target.closest('body')?.classList.contains('sidebar-open') && !e.target.closest('#sidebar') && window.innerWidth < 1024) {
                    document.querySelector('body').classList.remove('sidebar-open');
                }
            });
        });
    </script>

    @livewireScripts
    @stack('scripts')
</body>
</html>
