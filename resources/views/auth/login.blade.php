<x-layouts.public title="Login">

    <section class="mbui-container flex min-h-[70vh] items-center justify-center py-16">
        <div class="w-full max-w-md">
            <div class="mbui-card p-8">
                <h1 class="mbui-title text-center">Welcome back</h1>
                <p class="mt-1 text-center text-sm text-gray-500">Sign in to your MbunieEduHub account.</p>

                @if (session('status'))
                    <div class="mt-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                        {{ session('status') }}
                    </div>
                @endif

                <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-5">
                    @csrf

                    <div>
                        <x-input-label for="email" value="Email Address" />
                        <x-text-input id="email" class="mbui-input mt-1" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
                        <x-input-error :messages="$errors->get('email')" class="mt-2" />
                    </div>

                    <div>
                        <div class="flex items-center justify-between">
                            <x-input-label for="password" value="Password" />
                            @if (Route::has('password.request'))
                                <a href="{{ route('password.request') }}" class="text-sm text-indigo-600 hover:text-indigo-800">Forgot password?</a>
                            @endif
                        </div>
                        <x-text-input id="password" class="mbui-input mt-1" type="password" name="password" required autocomplete="current-password" />
                        <x-input-error :messages="$errors->get('password')" class="mt-2" />
                    </div>

                    <label class="flex items-center gap-2 text-sm text-gray-600">
                        <input type="checkbox" name="remember" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        Remember me
                    </label>

                    <button type="submit" class="inline-flex w-full items-center justify-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                        Sign in
                    </button>
                </form>

                @if (Route::has('register'))
                    <p class="mt-6 text-center text-sm text-gray-500">
                        Don't have an account?
                        <a href="{{ route('register') }}" class="font-medium text-indigo-600 hover:text-indigo-800">Create one</a>
                    </p>
                @endif
            </div>
        </div>
    </section>

</x-layouts.public>
