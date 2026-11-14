<x-layouts.admin title="Website chatbot" header="Website chatbot">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">Website chatbot</h1>
            <p class="mt-1 text-sm text-gray-500">
                The chat bubble visitors and customers see on the public pages. Answers come from the AI provider you pick below,
                using the live tools, plans, courses and scholarships on this site. It never talks about prices or payments. API keys are stored encrypted and are never shown again or sent to the browser.
            </p>
        </div>
    </div>

    <div class="mt-6 grid gap-4 sm:grid-cols-3">
        <x-mbui.card class="p-5">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Status</p>
            <p class="mt-2 text-lg font-semibold {{ $settings->isLive() ? 'text-emerald-700' : 'text-gray-700' }}">
                @if ($settings->isLive())
                    Live on the website
                @elseif (! $settings->enabled())
                    Turned off
                @else
                    Hidden: no API key
                @endif
            </p>
            <p class="mt-1 text-xs text-gray-500">{{ \App\Support\ChatbotSettings::PROVIDERS[$settings->provider()]['label'] }}, {{ $settings->model() }}</p>
        </x-mbui.card>
        <x-mbui.card class="p-5">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Questions today</p>
            <p class="mt-2 text-lg font-semibold text-gray-900">{{ number_format($stats['questions_today']) }}</p>
            <p class="mt-1 text-xs text-gray-500">{{ number_format($stats['answered_today']) }} answered by AI, {{ number_format($stats['visitors_today']) }} chats</p>
        </x-mbui.card>
        <x-mbui.card class="p-5">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Daily limits</p>
            <p class="mt-2 text-lg font-semibold text-gray-900">{{ number_format($settings->dailyLimit()) }} / day</p>
            <p class="mt-1 text-xs text-gray-500">{{ number_format($settings->sessionDailyLimit()) }} per chat per day, plus 6 per minute per IP</p>
        </x-mbui.card>
    </div>

    <form method="POST" action="{{ route('admin.chatbot.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('PUT')

        <x-mbui.card class="p-6">
            <h2 class="mbui-section-label">General</h2>

            <label class="mt-4 flex items-start gap-3">
                <input type="hidden" name="enabled" value="0">
                <input type="checkbox" name="enabled" value="1" @checked(old('enabled', $settings->enabled())) class="mt-1 rounded border-gray-300 text-indigo-600">
                <span>
                    <span class="block text-sm font-medium text-gray-900">Show the chatbot on the website</span>
                    <span class="block text-sm text-gray-500">It only appears once the active provider has an API key.</span>
                </span>
            </label>

            <div class="mt-5 grid gap-5 sm:grid-cols-3">
                <div>
                    <x-input-label for="provider" value="Active provider" />
                    <select id="provider" name="provider" class="mbui-input mt-1">
                        @foreach ($providers as $p)
                            <option value="{{ $p['id'] }}" @selected(old('provider', $settings->provider()) === $p['id'])>
                                {{ $p['label'] }}{{ $p['masked_key'] ? '' : ' (no key)' }}
                            </option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('provider')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="daily_limit" value="Max questions per day (all visitors)" />
                    <x-text-input id="daily_limit" class="mbui-input mt-1" type="number" min="0" name="daily_limit" :value="old('daily_limit', $settings->dailyLimit())" />
                    <p class="mt-1 text-xs text-gray-500">0 = no limit. Caps your AI bill.</p>
                    <x-input-error :messages="$errors->get('daily_limit')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="session_daily_limit" value="Max questions per chat per day" />
                    <x-text-input id="session_daily_limit" class="mbui-input mt-1" type="number" min="0" name="session_daily_limit" :value="old('session_daily_limit', $settings->sessionDailyLimit())" />
                    <x-input-error :messages="$errors->get('session_daily_limit')" class="mt-2" />
                </div>
            </div>
        </x-mbui.card>

        <x-mbui.card class="p-6">
            <h2 class="mbui-section-label">Providers and API keys</h2>
            <p class="mt-1 text-sm text-gray-500">Save a key for any provider; only the active one is used. Leave the key box empty to keep the saved key.</p>

            <div class="mt-5 grid gap-5 lg:grid-cols-3">
                @foreach ($providers as $p)
                    <div class="rounded-lg border border-gray-200 p-4">
                        <div class="flex items-center justify-between gap-2">
                            <h3 class="text-sm font-semibold text-gray-900">{{ $p['label'] }}</h3>
                            @if ($p['masked_key'])
                                <x-mbui.badge appearance="success">Key {{ $p['masked_key'] }}</x-mbui.badge>
                            @else
                                <x-mbui.badge appearance="neutral">No key</x-mbui.badge>
                            @endif
                        </div>

                        <div class="mt-4">
                            <x-input-label for="key_{{ $p['id'] }}" value="API key" />
                            <x-text-input id="key_{{ $p['id'] }}" class="mbui-input mt-1" type="password" name="key_{{ $p['id'] }}"
                                placeholder="{{ $p['masked_key'] ? 'Saved. Type a new key to replace it' : 'Paste API key' }}" autocomplete="new-password" />
                            <x-input-error :messages="$errors->get('key_'.$p['id'])" class="mt-2" />
                        </div>

                        <div class="mt-3">
                            <x-input-label for="model_{{ $p['id'] }}" value="Model" />
                            <x-text-input id="model_{{ $p['id'] }}" class="mbui-input mt-1" type="text" name="model_{{ $p['id'] }}"
                                :value="old('model_'.$p['id'], $p['model'])" list="models_{{ $p['id'] }}" />
                            <datalist id="models_{{ $p['id'] }}">
                                @foreach ($p['models'] as $m)
                                    <option value="{{ $m }}"></option>
                                @endforeach
                            </datalist>
                            <p class="mt-1 text-xs text-gray-500">Suggested: {{ implode(', ', $p['models']) }}</p>
                            <x-input-error :messages="$errors->get('model_'.$p['id'])" class="mt-2" />
                        </div>

                        @if ($p['masked_key'])
                            <label class="mt-3 flex items-center gap-2 text-sm text-gray-600">
                                <input type="checkbox" name="remove_key_{{ $p['id'] }}" value="1" class="rounded border-gray-300 text-red-600">
                                Remove saved key
                            </label>
                        @endif
                    </div>
                @endforeach
            </div>
        </x-mbui.card>

        <x-mbui.card class="p-6">
            <h2 class="mbui-section-label">Instructions (system prompt)</h2>
            <p class="mt-1 text-sm text-gray-500">
                How the bot should behave. Leave empty to use the built-in instructions. Live tools, plans, courses, scholarships, support links and the no-money rule are always added automatically.
            </p>
            <textarea name="system_prompt" rows="10" class="mbui-input mt-3 font-mono text-xs" placeholder="{{ $defaultPrompt }}">{{ old('system_prompt', $settings->customSystemPrompt()) }}</textarea>
            <x-input-error :messages="$errors->get('system_prompt')" class="mt-2" />
        </x-mbui.card>

        <div class="flex justify-end">
            <x-mbui.button type="submit">Save chatbot settings</x-mbui.button>
        </div>
    </form>

    <x-mbui.card class="mt-6 p-6">
        <h2 class="mbui-section-label">Test a provider</h2>
        <p class="mt-1 text-sm text-gray-500">Sends one short message with the saved key and model, so you can check the key works before going live.</p>
        <form method="POST" action="{{ route('admin.chatbot.test') }}" class="mt-4 flex flex-wrap items-center gap-3">
            @csrf
            <select name="provider" class="mbui-input !w-auto">
                @foreach ($providers as $p)
                    <option value="{{ $p['id'] }}" @selected($settings->provider() === $p['id'])>{{ $p['label'] }}</option>
                @endforeach
            </select>
            <x-mbui.button type="submit" variant="secondary">Send test message</x-mbui.button>
        </form>
    </x-mbui.card>

    <div class="mt-6">
        <x-mbui.card class="overflow-hidden">
            <x-mbui.table :title="'Recent visitor chats'">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200">
                        <th class="mbui-th">When</th>
                        <th class="mbui-th">Chat</th>
                        <th class="mbui-th">Who</th>
                        <th class="mbui-th">Message</th>
                        <th class="mbui-th">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse ($recent as $m)
                        <tr>
                            <td class="mbui-td whitespace-nowrap text-xs text-gray-500">{{ $m->created_at?->timezone('Africa/Dar_es_Salaam')->format('D j M, H:i') }}</td>
                            <td class="mbui-td font-mono text-xs text-gray-400">{{ substr($m->session_key, 0, 6) }}</td>
                            <td class="mbui-td text-xs">
                                @if ($m->role === 'user')
                                    {{ $m->user?->name ?? 'Visitor' }}
                                @else
                                    <span class="text-indigo-600">Bot</span>
                                @endif
                            </td>
                            <td class="mbui-td text-sm text-gray-700 whitespace-pre-line">{{ \Illuminate\Support\Str::limit($m->content, 300) }}</td>
                            <td class="mbui-td">
                                @if ($m->status)
                                    <x-mbui.badge :appearance="$m->status === 'ok' ? 'success' : ($m->status === 'error' ? 'danger' : 'warning')">{{ $m->status }}</x-mbui.badge>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="mbui-td text-center text-sm text-gray-500">No chats yet.</td></tr>
                    @endforelse
                </tbody>
            </x-mbui.table>
        </x-mbui.card>
        <div class="mt-4">{{ $recent->links() }}</div>
    </div>

</x-layouts.admin>
