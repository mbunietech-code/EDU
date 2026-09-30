{{-- Join a meeting through its guest link: just a name, no account. --}}
<x-layouts.classroom :title="$room->title">
    <main class="flex flex-1 items-center justify-center overflow-y-auto p-4">
        <div class="w-full max-w-sm rounded-2xl bg-gray-900 p-6 shadow-2xl ring-1 ring-gray-800">
            <p class="text-xs font-semibold uppercase tracking-wide text-indigo-300">You are invited</p>
            <h1 class="mt-1 text-xl font-semibold text-white">{{ $room->title }}</h1>
            @if ($room->host)
                <p class="mt-1 text-sm text-gray-400">Hosted by {{ $room->host->name }}</p>
            @endif
            @if ($room->scheduled_at && ! $room->isLive())
                <p class="mt-2 text-sm text-gray-300">{{ $room->scheduled_at->format('D, d M Y · H:i') }}</p>
            @endif

            <form method="POST" action="{{ route('guest.store', $token) }}" class="mt-6 space-y-3">
                @csrf
                <label for="guest-name" class="block text-sm font-medium text-gray-200">Your name</label>
                <input id="guest-name" name="name" type="text" required autofocus maxlength="60" autocomplete="name"
                    value="{{ old('name') }}"
                    class="w-full rounded-lg border-0 bg-gray-800 px-3 py-2.5 text-white placeholder-gray-500 ring-1 ring-gray-700 focus:ring-2 focus:ring-indigo-500"
                    placeholder="How others will see you">
                @error('name')
                    <p class="text-sm text-red-400" role="alert">{{ $message }}</p>
                @enderror
                <button type="submit" class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500">Join meeting</button>
            </form>

            <p class="mt-4 text-xs text-gray-500">
                No account needed. @if ($room->guest_waiting_room) The host lets you in. @endif
                Have an account? <a href="{{ route('login') }}" class="text-indigo-300 hover:text-indigo-200">Sign in</a> instead.
            </p>
        </div>
    </main>
</x-layouts.classroom>
