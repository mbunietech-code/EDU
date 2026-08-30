<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\CredentialService;
use App\Services\MailSettingsService;
use App\Support\AppDownloads;
use App\Support\Branding;
use App\Support\DevSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SettingController extends Controller
{
    public function __construct(protected CredentialService $credentialService)
    {
    }

    public function index()
    {
        $settings = Setting::whereNotIn('group', ['mail', Branding::GROUP, AppDownloads::GROUP])
            ->where('key', '!=', DevSettings::KEY)
            ->orderBy('group')
            ->orderBy('key')
            ->paginate(20);

        $mailSettings = Setting::where('group', 'mail')->pluck('value', 'key');

        $branding = [
            'logo' => Branding::logoUrl(),
            'favicon' => Branding::faviconUrl(),
        ];

        $appDownloads = AppDownloads::adminRows();
        $appPlatforms = AppDownloads::PLATFORMS;
        $adminDebug = DevSettings::adminDebugEnabled();

        return view('admin.settings.index', compact('settings', 'mailSettings', 'branding', 'appDownloads', 'appPlatforms', 'adminDebug'));
    }

    public function updateDev(Request $request)
    {
        $request->validate(['show_error_details' => ['nullable', 'boolean']]);

        DevSettings::set($request->boolean('show_error_details'));

        \App\Models\ActivityLog::log('dev_settings_updated', 'Setting', null, [
            'show_error_details' => $request->boolean('show_error_details'),
        ]);

        return back()->with('success', 'Developer settings saved.');
    }

    public function updateDownloads(Request $request)
    {
        $rules = [];
        foreach (array_keys(AppDownloads::PLATFORMS) as $platform) {
            $rules["file_{$platform}"] = ['nullable', 'file', 'max:262144', 'extensions:apk,zip,exe,msi,dmg,deb,appimage,gz,tar'];
            $rules["url_{$platform}"] = ['nullable', 'url', 'max:2048'];
            $rules["version_{$platform}"] = ['nullable', 'string', 'max:50'];
            $rules["remove_{$platform}"] = ['nullable', 'boolean'];
        }
        $request->validate($rules);

        $disk = Storage::disk('public');

        foreach (array_keys(AppDownloads::PLATFORMS) as $platform) {
            $pathKey = AppDownloads::key($platform, 'path');
            $urlKey = AppDownloads::key($platform, 'url');
            $versionKey = AppDownloads::key($platform, 'version');

            $currentPath = Setting::get($pathKey);
            $remove = (bool) $request->boolean("remove_{$platform}");

            if ($request->hasFile("file_{$platform}")) {
                if ($currentPath && $disk->exists($currentPath)) {
                    $disk->delete($currentPath);
                }
                $stored = $request->file("file_{$platform}")->store('app-downloads', 'public');
                Setting::set($pathKey, $stored, 'string', AppDownloads::GROUP);
                Setting::set($urlKey, '', 'string', AppDownloads::GROUP);
            } elseif ($remove) {
                if ($currentPath && $disk->exists($currentPath)) {
                    $disk->delete($currentPath);
                }
                Setting::set($pathKey, '', 'string', AppDownloads::GROUP);
                Setting::set($urlKey, '', 'string', AppDownloads::GROUP);
            } elseif ($request->filled("url_{$platform}")) {
                // A URL replaces any uploaded file.
                if ($currentPath && $disk->exists($currentPath)) {
                    $disk->delete($currentPath);
                }
                Setting::set($pathKey, '', 'string', AppDownloads::GROUP);
                Setting::set($urlKey, $request->input("url_{$platform}"), 'string', AppDownloads::GROUP);
            }

            Setting::set($versionKey, $request->input("version_{$platform}", '') ?? '', 'string', AppDownloads::GROUP);
        }

        AppDownloads::forget();

        \App\Models\ActivityLog::log('app_downloads_updated', 'Setting');

        return back()->with('success', 'App downloads updated.');
    }

    public function updateBranding(Request $request)
    {
        $request->validate([
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'favicon' => ['nullable', 'file', 'mimes:png,ico', 'max:512'],
            'remove_logo' => ['nullable', 'boolean'],
            'remove_favicon' => ['nullable', 'boolean'],
        ]);

        $disk = Storage::disk('public');

        $this->handleBrandingAsset(
            $request, $disk, 'logo', Branding::LOGO_KEY, (bool) $request->boolean('remove_logo'),
        );
        $this->handleBrandingAsset(
            $request, $disk, 'favicon', Branding::FAVICON_KEY, (bool) $request->boolean('remove_favicon'),
        );

        Branding::forget();

        \App\Models\ActivityLog::log('branding_updated', 'Setting');

        return back()->with('success', 'Branding updated.');
    }

    private function handleBrandingAsset(Request $request, $disk, string $field, string $key, bool $remove): void
    {
        $current = Setting::get($key);

        if ($request->hasFile($field)) {
            if ($current && $disk->exists($current)) {
                $disk->delete($current);
            }
            $path = $request->file($field)->store('branding', 'public');
            Setting::set($key, $path, 'string', Branding::GROUP);

            return;
        }

        if ($remove) {
            if ($current && $disk->exists($current)) {
                $disk->delete($current);
            }
            Setting::set($key, '', 'string', Branding::GROUP);
        }
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
