<x-layouts.admin title="Auto-reply" header="Messages">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Auto-reply</h1>
            <p class="mt-1 text-sm text-gray-500">When a customer writes and nobody answers within the set time, the system sends them this message once. It is sent only once until someone replies.</p>
        </div>
        <a href="{{ route('admin.chat.index') }}" class="mbui-anchor text-sm">&larr; Chats</a>
    </div>

    <div class="mt-6 mbui-card max-w-2xl p-6">
        <form method="POST" action="{{ route('admin.chat.auto-reply.update') }}" class="space-y-5">
            @csrf
            @method('PUT')

            <label class="flex items-center gap-3">
                <input type="checkbox" name="enabled" value="1" class="rounded border-gray-300 text-indigo-600" @checked(old('enabled', $enabled))>
                <span class="text-sm font-medium text-gray-900">Turn on auto-reply</span>
            </label>

            <div>
                <x-input-label for="minutes" value="Send after how many minutes without a reply" />
                <input id="minutes" name="minutes" type="number" min="1" max="1440" required class="mbui-input mt-1 w-32" value="{{ old('minutes', $minutes) }}">
                <x-input-error :messages="$errors->get('minutes')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="message" value="Message (English)" />
                <textarea id="message" name="message" rows="4" maxlength="1000" required class="mbui-input mt-1">{{ old('message', $message) }}</textarea>
                <p class="mt-1 text-xs text-gray-400">Every customer gets this message in English, whatever language they wrote in. Write <span class="font-mono">{name}</span> where the customer's name should go.</p>
                <x-input-error :messages="$errors->get('message')" class="mt-2" />
            </div>

            <div class="flex items-center justify-end border-t border-gray-100 pt-4">
                <x-mbui.button type="submit">Save</x-mbui.button>
            </div>
        </form>
    </div>

</x-layouts.admin>
