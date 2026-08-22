<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\CredentialService;
use App\Services\MailSettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class SettingController extends Controller
{
    public function __construct(protected CredentialService $credentialService)
    {
    }

    public function index()
    {
        $settings = Setting::where('group', '!=', 'mail')
            ->orderBy('group')
            ->orderBy('key')
            ->paginate(20);

        $mailSettings = Setting::where('group', 'mail')->pluck('value', 'key');

        return view('admin.settings.index', compact('settings', 'mailSettings'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'settings' => ['required', 'array'],
            'settings.*.key' => ['required', 'string', 'max:255'],
            'settings.*.value' => ['nullable', 'string'],
        ]);

        foreach ($validated['settings'] as $item) {
            Setting::set($item['key'], $item['value'] ?? '');
        }

        \App\Models\ActivityLog::log('settings_updated', 'Setting');

        return back()->with('success', 'Settings updated.');
    }

    public function updateMail(Request $request)
    {
        $validated = $request->validate([
            'mail_mailer' => ['required', Rule::in(['smtp', 'log'])],
            'mail_host' => ['nullable', 'string', 'max:255'],
            'mail_port' => ['nullable', 'integer'],
            'mail_encryption' => ['nullable', Rule::in(['tls', 'ssl', ''])],
            'mail_username' => ['nullable', 'string', 'max:255'],
            'mail_password' => ['nullable', 'string', 'max:255'],
            'mail_from_address' => ['required', 'email', 'max:255'],
            'mail_from_name' => ['required', 'string', 'max:255'],
        ]);

        foreach (['mail_mailer', 'mail_host', 'mail_port', 'mail_encryption', 'mail_username', 'mail_from_address', 'mail_from_name'] as $key) {
            Setting::set($key, $validated[$key] ?? '', 'string', 'mail');
        }

        if ($request->filled('mail_password')) {
            Setting::set('mail_password', $this->credentialService->encrypt($validated['mail_password']), 'encrypted', 'mail');
        }

        \App\Models\ActivityLog::log('mail_settings_updated', 'Setting');

        return back()->with('success', 'Email settings saved.');
    }

    public function testMail(Request $request, MailSettingsService $mailSettingsService)
    {
        $mailSettingsService->apply();

        try {
            Mail::raw(
                'This is a test email from MbunieEduHub sent ' . now()->format('d M Y H:i') . ' to confirm your email settings are working.',
                function ($message) use ($request) {
                    $message->to($request->user()->email)->subject('MbunieEduHub - Test Email');
                }
            );
        } catch (\Throwable $e) {
            \App\Models\ActivityLog::log('mail_settings_test_failed', 'Setting', null, ['error' => $e->getMessage()]);

            return back()->with('error', 'Test email failed: ' . $e->getMessage());
        }

        \App\Models\ActivityLog::log('mail_settings_test_sent', 'Setting');

        return back()->with('success', 'Test email sent to ' . $request->user()->email . '. Check your inbox (and spam folder).');
    }
}
