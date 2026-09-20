<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\OptimizationScan;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Turns a finished optimization scan into urgent-issue alerts and sends
 * them to admins by email and SMS (Beem Africa). Thresholds and recipients
 * are editable from Admin > AI Optimization > Alerts. The same set of
 * alerts is never re-sent within 24 hours.
 */
class AdminAlertService
{
    public const DEFAULTS = [
        'alert_expired_min' => 10,
        'alert_expiring_min' => 5,
        'alert_storage_mb' => 500,
        'alert_errors_min' => 10,
    ];

    public function __construct(private BeemSmsService $sms)
    {
    }

    public function threshold(string $key): int
    {
        return (int) Setting::get($key, self::DEFAULTS[$key]);
    }

    /**
     * @return list<string> human-readable alert lines (empty = all fine)
     */
    public function evaluate(OptimizationScan $scan): array
    {
        $alerts = [];

        if ($this->threshold('alert_expired_min') > 0 && $scan->expired_count >= $this->threshold('alert_expired_min')) {
            $alerts[] = "Accounts zilizoisha muda: {$scan->expired_count}";
        }

        if ($this->threshold('alert_expiring_min') > 0 && $scan->expiring_soon_count >= $this->threshold('alert_expiring_min')) {
            $alerts[] = "Subscriptions zinazoisha ndani ya siku 7: {$scan->expiring_soon_count}";
        }

        if ($this->threshold('alert_storage_mb') > 0 && (float) $scan->total_size_mb >= $this->threshold('alert_storage_mb')) {
            $alerts[] = "Ukubwa wa database: {$scan->total_size_mb} MB";
        }

        if ($scan->orphaned_count > 0) {
            $alerts[] = "Rows zilizovunjika (orphaned): {$scan->orphaned_count}";
        }

        if ($this->threshold('alert_errors_min') > 0 && Schema::hasTable('error_logs')) {
            $errors = DB::table('error_logs')->whereNull('resolved_at')->count();
            if ($errors >= $this->threshold('alert_errors_min')) {
                $alerts[] = "Hitilafu ambazo hazijatatuliwa: {$errors}";
            }
        }

        return $alerts;
    }

    /**
     * Evaluate a scan and notify admins if there is anything new to report.
     *
     * @return array{alerts:list<string>,sent:bool,email:bool,sms:bool,errors:list<string>}
     */
    public function notifyFor(OptimizationScan $scan): array
    {
        $alerts = $this->evaluate($scan);
        $result = ['alerts' => $alerts, 'sent' => false, 'email' => false, 'sms' => false, 'errors' => []];

        if ($alerts === []) {
            return $result;
        }

        $hash = md5(implode('|', $alerts));
        $lastSent = Setting::get('alert_last_sent_at');
        if (Setting::get('alert_last_hash') === $hash && $lastSent && \Illuminate\Support\Carbon::parse($lastSent)->diffInHours(now()) < 24) {
            return $result;
        }

        $result = $this->dispatch("Tahadhari ya mfumo:\n- ".implode("\n- ", $alerts), $result);

        if ($result['sent']) {
            Setting::set('alert_last_hash', $hash, 'string', 'alerts');
            Setting::set('alert_last_sent_at', now()->toDateTimeString(), 'string', 'alerts');
        }

        return $result;
    }

    /**
     * Send a test message through every configured channel.
     *
     * @return array{alerts:list<string>,sent:bool,email:bool,sms:bool,errors:list<string>}
     */
    public function sendTest(): array
    {
        return $this->dispatch(
            'Huu ni ujumbe wa majaribio kutoka AI Optimization — tahadhari zinafanya kazi.',
            ['alerts' => [], 'sent' => false, 'email' => false, 'sms' => false, 'errors' => []],
        );
    }

    /**
     * @param  array{alerts:list<string>,sent:bool,email:bool,sms:bool,errors:list<string>}  $result
     * @return array{alerts:list<string>,sent:bool,email:bool,sms:bool,errors:list<string>}
     */
    private function dispatch(string $message, array $result): array
    {
        $emails = $this->emailRecipients();
        if ($emails !== []) {
            try {
                foreach ($emails as $email) {
                    Mail::raw($message, fn ($m) => $m->to($email)->subject('Tahadhari ya mfumo'));
                }
                $result['email'] = true;
            } catch (Throwable $e) {
                $result['errors'][] = 'Email: '.$e->getMessage();
            }
        }

        $phones = $this->list(Setting::get('alert_phones'));
        if ($phones !== [] && $this->sms->isConfigured()) {
            try {
                $this->sms->send($phones, $message);
                $result['sms'] = true;
            } catch (Throwable $e) {
                $result['errors'][] = 'SMS: '.$e->getMessage();
            }
        }

        $result['sent'] = $result['email'] || $result['sms'];

        ActivityLog::log('admin_alert_sent', 'Alert', null, [
            'email' => $result['email'],
            'sms' => $result['sms'],
            'errors' => $result['errors'],
        ]);

        return $result;
    }

    /**
     * @return list<string>
     */
    private function emailRecipients(): array
    {
        $configured = $this->list(Setting::get('alert_emails'));
        if ($configured !== []) {
            return $configured;
        }

        return User::where('role', 'super_admin')->pluck('email')->all();
    }

    /**
     * @return list<string>
     */
    private function list(?string $csv): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $csv))));
    }
}
