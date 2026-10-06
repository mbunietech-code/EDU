<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\OptimizationRecommendation;
use App\Models\OptimizationScan;
use App\Models\Setting;
use App\Services\AdminAlertService;
use App\Services\BeemSmsService;
use App\Services\CredentialService;
use App\Services\DatabaseOptimizationService;
use App\Services\MailSettingsService;
use App\Services\OptimizationExecutor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * Platform settings in the app, same rules as the web pages:
 * alert channels (email + SMS) and the AI database optimization agent
 * (database.access = super admin), email (SMTP) settings
 * (settings.manage) and the Finance PIN (super admin). Secrets are
 * never sent back; a blank secret keeps the saved one.
 */
class PlatformSettingsController extends Controller
{
    private const ALERT_THRESHOLDS = ['alert_expired_min', 'alert_expiring_min', 'alert_storage_mb', 'alert_errors_min'];

    // --- Alerts -----------------------------------------------------------

    public function alerts(Request $request, AdminAlertService $alerts, BeemSmsService $sms): JsonResponse
    {
        $this->allow($request, 'database.access');

        return response()->json(['data' => [
            'sms_configured' => $sms->isConfigured(),
            'alert_emails' => Setting::get('alert_emails', ''),
            'alert_phones' => Setting::get('alert_phones', ''),
            'beem_api_key' => Setting::get('beem_api_key', ''),
            'beem_sender_id' => Setting::get('beem_sender_id', ''),
            'has_beem_secret' => (string) Setting::get('beem_secret_key', '') !== '',
            'thresholds' => collect(self::ALERT_THRESHOLDS)->mapWithKeys(fn ($k) => [$k => $alerts->threshold($k)]),
        ]]);
    }

    public function updateAlerts(Request $request, CredentialService $credentials): JsonResponse
    {
        $this->allow($request, 'database.access');
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
        foreach (self::ALERT_THRESHOLDS as $key) {
            Setting::set($key, $data[$key], 'integer', 'alerts');
        }
        if ($request->filled('beem_secret_key')) {
            Setting::set('beem_secret_key', $credentials->encrypt($data['beem_secret_key']), 'encrypted', 'alerts');
        }

        ActivityLog::log('alert_settings_updated', 'Setting');

        return response()->json(['message' => 'Alert settings saved.']);
    }

    public function testAlerts(Request $request, AdminAlertService $alerts): JsonResponse
    {
        $this->allow($request, 'database.access');
        $result = $alerts->sendTest();

        if (! $result['sent']) {
            $why = $result['errors'] !== [] ? implode(' | ', $result['errors']) : 'No email or SMS recipient/channel is configured.';

            return response()->json(['message' => 'Test alert was not sent: '.$why, 'sent' => false], 422);
        }

        $channels = collect(['email' => $result['email'], 'SMS' => $result['sms']])->filter()->keys()->implode(' + ');
        $note = $result['errors'] !== [] ? ' (some failed: '.implode(' | ', $result['errors']).')' : '';

        return response()->json(['message' => "Test alert sent via {$channels}.{$note}", 'sent' => true]);
    }

    // --- AI database optimization -----------------------------------------

    public function optimization(Request $request, DatabaseOptimizationService $optimizer): JsonResponse
    {
        $this->allow($request, 'database.access');
        $scan = OptimizationScan::with('recommendations')->latest('id')->first();

        return response()->json(['data' => [
            'supported' => $optimizer->isSupported(),
            'scan' => $scan ? $this->scanRow($scan) + [
                'recommendations' => $scan->recommendations->map(fn ($r) => $this->recommendationRow($r))->values(),
            ] : null,
            'history' => OptimizationScan::latest('id')->take(10)->get()->map(fn ($s) => $this->scanRow($s))->values(),
        ]]);
    }

    public function scan(Request $request, DatabaseOptimizationService $optimizer): JsonResponse
    {
        $this->allow($request, 'database.access');
        $scan = $optimizer->scan($request->user()->id);
        ActivityLog::log('optimization_scan_run', 'OptimizationScan', $scan->id, ['recommendations' => $scan->recommendations->count()]);

        return response()->json(['message' => 'Scan complete: '.$scan->recommendations->count().' recommendation(s) found.']);
    }

    public function approve(Request $request, OptimizationRecommendation $recommendation): JsonResponse
    {
        $this->allow($request, 'database.access');
        abort_unless($recommendation->status === 'pending', 400, 'Only pending recommendations can be approved.');

        $recommendation->update(['status' => 'approved', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
        ActivityLog::log('optimization_recommendation_approved', 'OptimizationRecommendation', $recommendation->id, [
            'table' => $recommendation->table_name,
            'category' => $recommendation->category,
        ]);

        return response()->json(['data' => $this->recommendationRow($recommendation), 'message' => 'Approved. You can now run it.']);
    }

    public function reject(Request $request, OptimizationRecommendation $recommendation): JsonResponse
    {
        $this->allow($request, 'database.access');
        abort_unless(in_array($recommendation->status, ['pending', 'approved'], true), 400, 'This recommendation cannot be rejected.');
        $data = $request->validate(['rejection_reason' => ['nullable', 'string', 'max:1000']]);

        $recommendation->update([
            'status' => 'rejected',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'rejection_reason' => $data['rejection_reason'] ?? null,
        ]);
        ActivityLog::log('optimization_recommendation_rejected', 'OptimizationRecommendation', $recommendation->id, ['table' => $recommendation->table_name]);

        return response()->json(['data' => $this->recommendationRow($recommendation), 'message' => 'Recommendation rejected.']);
    }

    public function execute(Request $request, OptimizationRecommendation $recommendation, OptimizationExecutor $executor): JsonResponse
    {
        $this->allow($request, 'database.access');

        try {
            $result = $executor->execute($recommendation, $request->user()->id);
        } catch (HttpException $e) {
            throw $e;
        } catch (Throwable $e) {
            return response()->json(['message' => 'Execution failed: '.$e->getMessage().'. No changes were made beyond the backup step, if reached.'], 422);
        }

        return response()->json([
            'data' => $this->recommendationRow($recommendation->refresh()),
            'message' => "Done. {$result['count']} row(s) affected.".($result['backup_path'] ? ' Backup saved.' : ''),
        ]);
    }

    // --- Email (SMTP) -------------------------------------------------------

    public function mail(Request $request): JsonResponse
    {
        $this->allow($request, 'settings.manage');
        $values = Setting::where('group', 'mail')->pluck('value', 'key');

        return response()->json(['data' => [
            'mail_mailer' => $values['mail_mailer'] ?? 'smtp',
            'mail_host' => $values['mail_host'] ?? '',
            'mail_port' => $values['mail_port'] ?? '',
            'mail_encryption' => $values['mail_encryption'] ?? '',
            'mail_username' => $values['mail_username'] ?? '',
            'mail_from_address' => $values['mail_from_address'] ?? '',
            'mail_from_name' => $values['mail_from_name'] ?? '',
            'has_password' => (string) ($values['mail_password'] ?? '') !== '',
        ]]);
    }

    public function updateMail(Request $request, CredentialService $credentials): JsonResponse
    {
        $this->allow($request, 'settings.manage');
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
            Setting::set('mail_password', $credentials->encrypt($validated['mail_password']), 'encrypted', 'mail');
        }

        ActivityLog::log('mail_settings_updated', 'Setting');

        return response()->json(['message' => 'Email settings saved.']);
    }

    public function testMail(Request $request, MailSettingsService $mailSettings): JsonResponse
    {
        $this->allow($request, 'settings.manage');
        $mailSettings->apply();
        $to = $request->user()->email;

        try {
            Mail::raw(
                'This is a test email from MbunieEduHub sent '.now()->format('d M Y H:i').' to confirm your email settings are working.',
                fn ($message) => $message->to($to)->subject('MbunieEduHub - Test Email'),
            );
        } catch (Throwable $e) {
            ActivityLog::log('mail_settings_test_failed', 'Setting', null, ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Test email failed: '.$e->getMessage()], 422);
        }

        ActivityLog::log('mail_settings_test_sent', 'Setting');

        return response()->json(['message' => 'Test email sent to '.$to.'. Check the inbox (and spam folder).']);
    }

    // --- Finance PIN ----------------------------------------------------------

    /** Set or change the 6-digit Finance PIN (the current PIN is needed to change it). */
    public function financePin(Request $request): JsonResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403, 'Only a super admin can change the Finance PIN.');
        $current = (string) Setting::get('finance_pin', '');

        $data = $request->validate([
            'current_pin' => [$current !== '' ? 'required' : 'nullable', 'string'],
            'pin' => ['required', 'string', 'regex:/^\d{6}$/', 'confirmed'],
        ], ['pin.regex' => 'The PIN must be exactly 6 digits.']);

        if ($current !== '' && ! hash_equals($current, (string) $data['current_pin'])) {
            ActivityLog::log('finance_pin_change_failed', 'Setting');
            throw ValidationException::withMessages(['current_pin' => 'The current PIN is wrong.']);
        }

        Setting::set('finance_pin', $data['pin'], 'string', 'finance');
        ActivityLog::log('finance_pin_changed', 'Setting');

        return response()->json(['message' => 'Finance PIN saved.']);
    }

    // --- Helpers --------------------------------------------------------------

    private function scanRow(OptimizationScan $s): array
    {
        return [
            'id' => $s->id,
            'created_at' => $s->created_at?->toIso8601String(),
            'total_tables' => (int) $s->total_tables,
            'total_rows' => (int) $s->total_rows,
            'total_size_mb' => (float) $s->total_size_mb,
            'expired_count' => (int) $s->expired_count,
            'estimated_recovery_mb' => (float) $s->estimated_recovery_mb,
        ];
    }

    private function recommendationRow(OptimizationRecommendation $r): array
    {
        return [
            'id' => $r->id,
            'category' => $r->category,
            'operation' => $r->operation,
            'table' => $r->table_name,
            'column' => $r->column_name,
            'affected_count' => (int) $r->affected_count,
            'action' => $r->action,
            'risk_level' => $r->risk_level,
            'estimated_recovery_mb' => (float) $r->estimated_recovery_mb,
            'sql_preview' => $r->sql_preview,
            'rollback_note' => $r->rollback_note,
            'status' => $r->status,
            'can_run' => in_array($r->operation, ['delete', 'update', 'create_index'], true),
            'rejection_reason' => $r->rejection_reason,
            'executed_count' => $r->executed_count,
            'executed_at' => $r->executed_at?->toIso8601String(),
        ];
    }

    private function allow(Request $request, string $ability): void
    {
        abort_unless(Gate::forUser($request->user())->allows($ability), 403, 'You do not have access to these settings.');
    }
}
