<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ChatbotMessage;
use App\Services\Chatbot\ChatbotService;
use App\Support\ChatbotSettings;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Admin -> Website chatbot: provider, model, API keys, prompt, limits. */
class ChatbotSettingsController extends Controller
{
    public function __construct(private ChatbotSettings $settings)
    {
    }

    public function edit()
    {
        $providers = collect(ChatbotSettings::PROVIDERS)->map(fn ($p, $id) => $p + [
            'id' => $id,
            'model' => $this->settings->model($id),
            'masked_key' => $this->settings->maskedKey($id),
        ]);

        $recent = ChatbotMessage::latest('id')->paginate(30);

        $today = ChatbotMessage::where('role', 'user')->where('created_at', '>=', now()->startOfDay());
        $stats = [
            'questions_today' => (clone $today)->count(),
            'answered_today' => (clone $today)->where('status', 'ok')->count(),
            'visitors_today' => (clone $today)->distinct('session_key')->count('session_key'),
        ];

        return view('admin.chatbot.edit', [
            'settings' => $this->settings,
            'providers' => $providers,
            'recent' => $recent,
            'stats' => $stats,
            'defaultPrompt' => ChatbotSettings::defaultSystemPrompt(),
        ]);
    }

    public function update(Request $request)
    {
        $providerIds = array_keys(ChatbotSettings::PROVIDERS);

        $rules = [
            'enabled' => ['nullable', 'boolean'],
            'provider' => ['required', Rule::in($providerIds)],
            'system_prompt' => ['nullable', 'string', 'max:6000'],
            'daily_limit' => ['required', 'integer', 'min:0', 'max:100000'],
            'session_daily_limit' => ['required', 'integer', 'min:0', 'max:1000'],
        ];
        foreach ($providerIds as $id) {
            $rules["model_{$id}"] = ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9._:\/-]+$/'];
            $rules["key_{$id}"] = ['nullable', 'string', 'max:500'];
            $rules["remove_key_{$id}"] = ['nullable', 'boolean'];
        }
        $validated = $request->validate($rules);

        $this->settings->set('chatbot_enabled', $request->boolean('enabled') ? '1' : '0');
        $this->settings->set('chatbot_provider', $validated['provider']);
        $this->settings->set('chatbot_system_prompt', trim((string) ($validated['system_prompt'] ?? '')));
        $this->settings->set('chatbot_daily_limit', (string) $validated['daily_limit']);
        $this->settings->set('chatbot_session_daily_limit', (string) $validated['session_daily_limit']);

        $keysChanged = [];
        foreach ($providerIds as $id) {
            $this->settings->set("chatbot_model_{$id}", trim((string) ($validated["model_{$id}"] ?? '')));

            if ($request->boolean("remove_key_{$id}")) {
                $this->settings->setKey($id, null);
                $keysChanged[] = "{$id}:removed";
            } elseif ($request->filled("key_{$id}")) {
                $this->settings->setKey($id, $validated["key_{$id}"]);
                $keysChanged[] = "{$id}:set";
            }
        }

        ActivityLog::log('chatbot_settings_updated', 'Setting', null, [
            'enabled' => $request->boolean('enabled'),
            'provider' => $validated['provider'],
            'keys' => $keysChanged,
        ]);

        $warning = $request->boolean('enabled') && ! $this->settings->hasKey($validated['provider'])
            ? ' The chat stays hidden from visitors until '.ChatbotSettings::PROVIDERS[$validated['provider']]['label'].' has an API key.'
            : '';

        return back()->with('success', 'Chatbot settings saved.'.$warning);
    }

    public function test(Request $request, ChatbotService $chatbot)
    {
        $validated = $request->validate([
            'provider' => ['required', Rule::in(array_keys(ChatbotSettings::PROVIDERS))],
        ]);

        $result = $chatbot->test($validated['provider']);
        $label = ChatbotSettings::PROVIDERS[$validated['provider']]['label'];

        return back()->with(
            $result['ok'] ? 'success' : 'error',
            $result['ok'] ? "{$label} works. Reply: {$result['message']}" : "{$label} test failed: {$result['message']}"
        );
    }
}
