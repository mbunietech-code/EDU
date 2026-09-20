<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Setting;
use App\Services\AdminAlertService;
use App\Services\BeemSmsService;
use App\Services\CredentialService;
use Illuminate\Http\Request;

class AlertSettingsController extends Controller
{
    public function __construct(
        private AdminAlertService $alerts,
        private BeemSmsService $sms,
        private CredentialService $credentials,
    ) {
    }

    public function index()
    {
        return view('admin.alerts.index', [
            'smsConfigured' => $this->sms->isConfigured(),
            'values' => [
                'alert_emails' => Setting::get('alert_emails', ''),
                'alert_phones' => Setting::get('alert_phones', ''),
                'beem_api_key' => Setting::get('beem_api_key', ''),
                'beem_sender_id' => Setting::get('beem_sender_id', ''),
                'alert_expired_min' => $this->alerts->threshold('alert_expired_min'),
                'alert_expiring_min' => $this->alerts->threshold('alert_expiring_min'),
                'alert_storage_mb' => $this->alerts->threshold('alert_storage_mb'),
                'alert_errors_min' => $this->alerts->threshold('alert_errors_min'),
            ],
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'alert_emails' => ['nullable', 'string', 'max:1000'],
            'alert_phones' => ['nullable', 'string', 'max:1000'],
            'beem_api_key' => ['nullable', 'string', 'max:255'],
            'beem_secret_key' => ['nullable', 'string', 'max:255'],
            'beem_sender_id' => ['nullable', 'string', 'max:20'],
            'alert_expired_min' => ['required', 'integer', 'min:0'],
            'alert_expiring_min' => ['required', 'integer', 'min:0'],
            'alert_storage_mb' => ['required', 'integer', 'min:0'],
            'alert_errors_min' => ['required', 'integer', 'min:0'],
        ]);

        foreach (['alert_emails', 'alert_phones', 'beem_api_key', 'beem_sender_id'] as $key) {
            Setting::set($key, $data[$key] ?? '', 'string', 'alerts');
        }
        foreach (['alert_expired_min', 'alert_expiring_min', 'alert_storage_mb', 'alert_errors_min'] as $key) {
            Setting::set($key, $data[$key], 'integer', 'alerts');
        }

        // Blank secret means "keep the one already saved".
        if ($request->filled('beem_secret_key')) {
            Setting::set('beem_secret_key', $this->credentials->encrypt($data['beem_secret_key']), 'encrypted', 'alerts');
        }

        ActivityLog::log('alert_settings_updated', 'Setting');

        return back()->with('success', 'Alert settings saved.');
    }

    public function test()
    {
        $result = $this->alerts->sendTest();

        if (! $result['sent']) {
            $why = $result['errors'] !== [] ? implode(' | ', $result['errors']) : 'No email or SMS recipient/channel is configured.';

            return back()->with('error', 'Test alert was not sent: '.$why);
        }

        $channels = collect(['email' => $result['email'], 'SMS' => $result['sms']])->filter()->keys()->implode(' + ');
        $note = $result['errors'] !== [] ? ' (some failed: '.implode(' | ', $result['errors']).')' : '';

        return back()->with('success', "Test alert sent via {$channels}.{$note}");
    }
}
