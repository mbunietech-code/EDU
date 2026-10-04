<x-layouts.public title="Reset Password">

    <section class="mbui-container flex min-h-[72vh] items-center justify-center py-16">
        <div class="grid w-full max-w-4xl overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm lg:grid-cols-[0.9fr_1.1fr]">
            <div class="hidden bg-gray-950 p-8 text-white lg:flex lg:flex-col lg:justify-between">
                <div>
                    <x-brand-mark class="h-12 w-12" rounded="rounded-xl" />
                    <h1 class="mt-8 text-2xl font-bold tracking-tight">Secure your account</h1>
                    <p class="mt-3 text-sm leading-6 text-gray-300">
                        Create a fresh password for your MbunieEduHub account. Keep it unique and hard to guess.
                    </p>
                </div>
                <div class="rounded-xl border border-white/10 bg-white/5 p-4 text-sm text-gray-300">
                    Your reset link is time-limited and can only be used for this email address.
                </div>
            </div>

            <div class="p-6 sm:p-8">
                <div class="mb-6 flex justify-center lg:hidden">
                    <x-brand-mark class="h-12 w-12" rounded="rounded-xl" />
                </div>
                <h2 class="mbui-title text-center lg:text-left">Reset password</h2>
                <p class="mt-2 text-center text-sm text-gray-500 lg:text-left">
                    Enter the email that received the reset link and choose a new password.
                </p>

                <form method="POST" action="{{ route('password.store') }}" class="mt-6 space-y-5">
                    @csrf
                    <input type="hidden" name="token" value="{{ $request->route('token') }}">

                    <div>
                        <x-input-label for="email" value="Email Address" />
                        <x-text-input id="email" class="mbui-input mt-1" type="email" name="email" :value="old('email', $request->email)" required autofocus autocomplete="username" />
                        <x-input-error :messages="$errors->get('email')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="password" value="New Password" />
                        <x-text-input id="password" class="mbui-input mt-1" type="password" name="password" required autocomplete="new-password" />
                        <x-input-error :messages="$errors->get('password')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="password_confirmation" value="Confirm New Password" />
                        <x-text-input id="password_confirmation" class="mbui-input mt-1" type="password" name="password_confirmation" required autocomplete="new-password" />
                        <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
                    </div>

                    <button type="submit" class="inline-flex w-full items-center justify-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                        Save new password
                    </button>
                </form>

                <p class="mt-6 text-center text-sm text-gray-500">
                    Back to
                    <a href="{{ route('login') }}" class="font-medium text-indigo-600 hover:text-indigo-800">sign in</a>
                </p>
            </div>
        </div>
    </section>

</x-layouts.public>
