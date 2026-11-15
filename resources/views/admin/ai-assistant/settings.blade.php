<x-layouts.admin title="AI Assistant settings" header="AI Assistant">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">AI Assistant settings</h1>
            <p class="mt-1 text-sm text-gray-500">
                The admin chat runs on DeepSeek. The API key is stored encrypted, used only by the server and never shown again.
                Without a key of its own the assistant uses the website chatbot's DeepSeek key.
            </p>
        </div>
        <a href="{{ route('admin.ai-assistant.index') }}" class="inline-flex items-center justify-center rounded-lg bg-white px-4 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">Back to chat</a>
    </div>

    <form method="POST" action="{{ route('admin.ai-assistant.settings.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('PUT')

        <x-mbui.card class="p-6">
            <h2 class="mbui-section-label">DeepSeek</h2>

            <div class="mt-4 grid gap-5 sm:grid-cols-2">
                <div>
                    <x-input-label for="key" value="API key" />
                    <x-text-input id="key" name="key" type="password" class="mbui-input mt-1" autocomplete="new-password"
                                  placeholder="{{ $settings->maskedKey() ? 'Saved: '.$settings->maskedKey() : 'sk-...' }}" />
                    <p class="mt-1 text-xs text-gray-500">
                        @switch($settings->keySource())
                            @case('own') Using this assistant's own key. Leave empty to keep it. @break
                            @case('chatbot') Using the website chatbot's DeepSeek key. Paste a key here to give the assistant its own. @break
                            @default No key yet: the chat answers with simple built-in summaries only.
                        @endswitch
                        Get one at platform.deepseek.com.
                    </p>
                    @if ($settings->ownKey())
                        <label class="mt-2 flex items-center gap-2 text-sm text-gray-600">
                            <input type="checkbox" name="remove_key" value="1" class="rounded border-gray-300 text-red-600"> Remove the assistant's own key
                        </label>
                    @endif
                    <x-input-error :messages="$errors->get('key')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="model" value="Model" />
                    <select id="model" name="model" class="mbui-input mt-1">
                        @foreach ($models as $id => $label)
                            <option value="{{ $id }}" @selected(old('model', $settings->model()) === $id)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-gray-500">DeepSeek Chat reads live data (orders, payments, errors...). Reasoner thinks longer but cannot use the data tools.</p>
                </div>
                <div>
                    <x-input-label for="daily_limit" value="Messages per admin per day" />
                    <x-text-input id="daily_limit" name="daily_limit" type="number" min="0" class="mbui-input mt-1" :value="old('daily_limit', $settings->dailyLimit())" />
                    <p class="mt-1 text-xs text-gray-500">0 = no limit. Past the limit the chat falls back to free summaries.</p>
                </div>
                <div>
                    <x-input-label for="instructions" value="Extra instructions (optional)" />
                    <textarea id="instructions" name="instructions" rows="3" class="mbui-input mt-1" placeholder="e.g. Always sign customer replies as 'Timu ya MbunieEduHub'.">{{ old('instructions', $settings->extraInstructions()) }}</textarea>
                </div>
            </div>
        </x-mbui.card>

        <x-mbui.card class="p-6">
            <h2 class="mbui-section-label">Calls to the team</h2>
            <p class="mt-1 text-sm text-gray-500">
                Every minute the assistant checks the system. When something needs a person it rings the admins who can handle it:
                a popup with sound on every admin page and a push on the MHub app. Each problem rings once.
            </p>

            <label class="mt-4 flex items-start gap-3">
                <input type="hidden" name="alerts_enabled" value="0">
                <input type="checkbox" name="alerts_enabled" value="1" @checked(old('alerts_enabled', $settings->alertsEnabled())) class="mt-1 rounded border-gray-300 text-indigo-600">
                <span>
                    <span class="block text-sm font-medium text-gray-900">Ring admins about problems</span>
                    <span class="block text-sm text-gray-500">New server errors (and errors that keep happening), payments waiting for approval, stuck online payments, customers waiting in support chat, missed video help calls, paid orders not activated.</span>
                </span>
            </label>

            <div class="mt-5 grid gap-5 sm:grid-cols-2">
                <div>
                    <x-input-label for="payment_wait" value="Ring when a payment waits (minutes)" />
                    <x-text-input id="payment_wait" name="payment_wait" type="number" min="5" class="mbui-input mt-1" :value="old('payment_wait', $settings->paymentWaitMinutes())" />
                </div>
                <div>
                    <x-input-label for="chat_wait" value="Ring when a customer waits in chat (minutes)" />
                    <x-text-input id="chat_wait" name="chat_wait" type="number" min="1" class="mbui-input mt-1" :value="old('chat_wait', $settings->chatWaitMinutes())" />
                </div>
            </div>
        </x-mbui.card>

        <div class="flex flex-wrap items-center gap-3">
            <x-mbui.button type="submit" variant="primary">Save settings</x-mbui.button>
        </div>
    </form>

    <form method="POST" action="{{ route('admin.ai-assistant.settings.test') }}" class="mt-3">
        @csrf
        <x-mbui.button type="submit" variant="secondary">Test DeepSeek</x-mbui.button>
    </form>

</x-layouts.admin>
