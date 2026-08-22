@props(['title' => config('app.name', 'MbunieEduHub'), 'header' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title . ' | Admin | ' . config('app.name', 'MbunieEduHub') : 'Admin | ' . config('app.name', 'MbunieEduHub') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-gray-100" x-data="{ sidebarOpen: false }">
    <div class="min-h-full">
        <aside class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col bg-gray-900 transform transition-transform lg:translate-x-0"
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

            <nav class="mt-4 flex-1 space-y-1 overflow-y-auto px-3 pb-4">
                <x-admin.sidebar-link :route="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')" label="Dashboard">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
                </x-admin.sidebar-link>

                <div class="pt-2">
                    <p class="px-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Management</p>
                </div>

                <x-admin.sidebar-link :route="route('admin.users.index')" :active="request()->routeIs('admin.users.*')" label="Users">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                </x-admin.sidebar-link>

                <x-admin.sidebar-link :route="route('admin.products.index')" :active="request()->routeIs('admin.products.*')" label="Products">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                </x-admin.sidebar-link>

                <x-admin.sidebar-link :route="route('admin.plans.index')" :active="request()->routeIs('admin.plans.*')" label="Plans">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z" />
                </x-admin.sidebar-link>

                <x-admin.sidebar-link :route="route('admin.accounts.index')" :active="request()->routeIs('admin.accounts.*')" label="Accounts">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 18.75v-2.25m0 0a2.25 2.25 0 00-4.5 0m4.5 0a2.25 2.25 0 01-4.5 0m0-12V6m0 0H8.25m0 0c0-.621.504-1.125 1.125-1.125H18.75A1.125 1.125 0 0119.875 6v6.75m-9 0V10.5c0-.621.504-1.125 1.125-1.125h7.875m-4.125 0a1.125 1.125 0 01-2.25 0m2.25 0a1.5 1.5 0 103 0m-3 3v7.5c0 .621-.504 1.125-1.125 1.125h-9A1.125 1.125 0 014.5 19.125v-7.5c0-.621.504-1.125 1.125-1.125h7.125z" />
                </x-admin.sidebar-link>

                <x-admin.sidebar-link :route="route('admin.tools.index')" :active="request()->routeIs('admin.tools.*')" label="Research Tools">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 2.25l9 5.25-3.75 2.25L12 15.75l-5.25-3L3 7.5l9-5.25z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 2.25v13.5" />
                </x-admin.sidebar-link>

                <x-admin.sidebar-link :route="route('admin.scholarships.index')" :active="request()->routeIs('admin.scholarships.*')" label="Scholarships">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443" />
                </x-admin.sidebar-link>

                <div class="pt-2">
                    <p class="px-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Operations</p>
                </div>

                <x-admin.sidebar-link :route="route('admin.orders.index')" :active="request()->routeIs('admin.orders.*')" label="Orders">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007z" />
                </x-admin.sidebar-link>

                <x-admin.sidebar-link :route="route('admin.payments.index')" :active="request()->routeIs('admin.payments.*')" label="Payments"
                    :badge="\App\Models\Payment::where('status', 'pending')->count()">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5m-19.5 5.25h19.5m-16.5 5.25h13.5" />
                </x-admin.sidebar-link>

                <x-admin.sidebar-link :route="route('admin.chat.index')" :active="request()->routeIs('admin.chat.*')" label="Messages" :badge="auth()->user()->unreadChatMessagesCount()">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 9.75a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z" />
                </x-admin.sidebar-link>

                <x-admin.sidebar-link :route="route('admin.contact-messages.index')" :active="request()->routeIs('admin.contact-messages.*')" label="Contact Messages"
                    :badge="\App\Models\ContactMessage::where('is_read', false)->count()">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                </x-admin.sidebar-link>

                <x-admin.sidebar-link :route="route('admin.payment-methods.index')" :active="request()->routeIs('admin.payment-methods.*')" label="Payment Methods">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 15.546c-.523 0-1.046.151-1.5.454a2.704 2.704 0 01-3 0 2.704 2.704 0 00-3 0 2.704 2.704 0 01-3 0 2.704 2.704 0 00-3 0 2.704 2.704 0 01-3 0 2.701 2.701 0 00-1.5-.454m5.25 3.75a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm9 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" />
                </x-admin.sidebar-link>

                <x-admin.sidebar-link :route="route('admin.subscriptions.index')" :active="request()->routeIs('admin.subscriptions.*')" label="Subscriptions">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </x-admin.sidebar-link>

                <div class="pt-2">
                    <p class="px-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Insights</p>
                </div>

                <x-admin.sidebar-link :route="route('admin.reports.index')" :active="request()->routeIs('admin.reports.*')" label="Reports">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                </x-admin.sidebar-link>

                <x-admin.sidebar-link :route="route('admin.activity-logs.index')" :active="request()->routeIs('admin.activity-logs.*')" label="Activity Logs">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                </x-admin.sidebar-link>

                <x-admin.sidebar-link :route="route('admin.notifications.index')" :active="request()->routeIs('admin.notifications.*')" label="Notifications"
                    :badge="auth()->user()->unreadNotifications()->count()">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                </x-admin.sidebar-link>

                <x-admin.sidebar-link :route="route('admin.settings.index')" :active="request()->routeIs('admin.settings.*')" label="Settings">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.28z" />
                </x-admin.sidebar-link>
            </nav>

            <div class="shrink-0 border-t border-gray-800 p-1.5 space-y-0">
                <a href="{{ route('public.home') }}" class="flex w-full items-center gap-2 rounded-lg px-2 py-1 text-xs text-gray-300 hover:bg-gray-800 hover:text-white">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3" />
                    </svg>
                    View Public Site
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-mbui.button variant="ghost" type="submit" class="w-full justify-start !gap-2 !px-2 !py-1 !text-xs !text-gray-300 hover:!bg-gray-800 hover:!text-white">
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
                    <h1 class="text-lg font-semibold text-gray-900">{{ $header ?? $title }}</h1>
                </div>
                <div class="flex items-center gap-3">
                    <livewire:shared.notification-bell />
                    <div class="flex items-center gap-2">
                        <span class="h-8 w-8 rounded-full bg-indigo-600 flex items-center justify-center text-xs font-semibold text-white">
                            {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                        </span>
                        <span class="hidden md:inline text-sm text-gray-700">{{ auth()->user()->name }}</span>
                    </div>
                </div>
            </header>

            <main class="p-4 sm:p-6 lg:p-8">
                <x-partials.flash />
                {{ $slot }}
            </main>
        </div>
    </div>

    @livewireScripts
    @stack('scripts')
</body>
</html>