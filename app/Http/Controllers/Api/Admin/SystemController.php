<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ContactMessage;
use App\Models\DeletedRecord;
use App\Models\ErrorLog;
use App\Models\PaymentMethod;
use App\Models\Subscription;
use App\Services\DeletionService;
use App\Services\NotificationService;
use App\Services\SubscriptionService;
use App\Support\AppDownloads;
use App\Support\Branding;
use App\Support\DevSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SystemController extends Controller
{
    // ------------------------------------------------------------- Error logs
    public function errorLogs(Request $request): JsonResponse
    {
        $filter = $request->query('status', 'open');

        $logs = ErrorLog::with('user:id,name')
            ->when($filter === 'open', fn ($q) => $q->whereNull('resolved_at'))
            ->when($filter === 'resolved', fn ($q) => $q->whereNotNull('resolved_at'))
            ->orderByDesc('last_seen_at')
            ->paginate(30);

        return response()->json([
            'data' => collect($logs->items())->map(fn (ErrorLog $e) => [
                'id' => $e->id,
                'exception' => class_basename($e->exception),
                'message' => \Illuminate\Support\Str::limit((string) $e->message, 140),
                'location' => $e->file ? basename($e->file).':'.$e->line : null,
                'occurrences' => $e->occurrences,
                'resolved' => $e->resolved_at !== null,
                'last_seen_ago' => optional($e->last_seen_at)->diffForHumans(),
            ])->all(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'open' => ErrorLog::whereNull('resolved_at')->count(),
            ],
        ]);
    }

    public function errorLog(ErrorLog $errorLog): JsonResponse
    {
        $errorLog->load(['user:id,name,email', 'resolver:id,name']);

        return response()->json(['data' => [
            'id' => $errorLog->id,
            'exception' => $errorLog->exception,
            'message' => $errorLog->message,
            'file' => $errorLog->file,
            'line' => $errorLog->line,
            'url' => $errorLog->url,
            'method' => $errorLog->method,
            'occurrences' => $errorLog->occurrences,
            'trace' => $errorLog->trace,
            'context' => $errorLog->context,
            'user' => $errorLog->user?->only(['name', 'email']),
            'resolved' => $errorLog->resolved_at !== null,
            'resolved_by' => $errorLog->resolver->name ?? null,
            'first_seen' => optional($errorLog->first_seen_at)->toIso8601String(),
            'last_seen' => optional($errorLog->last_seen_at)->toIso8601String(),
        ]]);
    }

    public function resolveError(ErrorLog $errorLog): JsonResponse
    {
        $errorLog->update(['resolved_at' => now(), 'resolved_by' => request()->user()->id]);
        ActivityLog::log('error_log_resolved', 'ErrorLog', $errorLog->id);

        return response()->json(['message' => 'Marked as resolved.']);
    }

    public function reopenError(ErrorLog $errorLog): JsonResponse
    {
        $errorLog->update(['resolved_at' => null, 'resolved_by' => null]);

        return response()->json(['message' => 'Reopened.']);
    }

    public function deleteError(ErrorLog $errorLog): JsonResponse
    {
        $errorLog->delete();

        return response()->json(['message' => 'Error log deleted.']);
    }

    // -------------------------------------------------------- Deleted records
    public function deletedRecords(Request $request): JsonResponse
    {
        $records = DeletedRecord::with('deleter:id,name')
            ->when($request->filled('entity'), fn ($q) => $q->where('entity', $request->string('entity')))
            ->latest()
            ->paginate(30);

        return response()->json([
            'data' => collect($records->items())->map(fn (DeletedRecord $r) => [
                'id' => $r->id,
                'entity' => $r->entity,
                'label' => $r->label,
                'reason' => $r->reason,
                'deleted_by' => $r->deleter->name ?? null,
                'deleted_ago' => optional($r->created_at)->diffForHumans(),
            ])->all(),
            'meta' => [
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'entities' => DeletedRecord::select('entity')->distinct()->orderBy('entity')->pluck('entity'),
            ],
        ]);
    }

    public function deletedRecord(DeletedRecord $deletedRecord): JsonResponse
    {
        $deletedRecord->load('deleter:id,name');

        return response()->json(['data' => [
            'id' => $deletedRecord->id,
            'entity' => $deletedRecord->entity,
            'label' => $deletedRecord->label,
            'reason' => $deletedRecord->reason,
            'deleted_by' => $deletedRecord->deleter->name ?? null,
            'deleted_at' => optional($deletedRecord->created_at)->toIso8601String(),
            'snapshot' => $deletedRecord->snapshot,
        ]]);
    }

    // ----------------------------------------------------------- Activity log
    public function activity(Request $request): JsonResponse
    {
        $logs = ActivityLog::with('actor:id,name')
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->string('action')))
            ->when($request->filled('entity'), fn ($q) => $q->where('entity', $request->string('entity')))
            ->latest()
            ->paginate(50);

        return response()->json([
            'data' => collect($logs->items())->map(fn (ActivityLog $l) => [
                'id' => $l->id,
                'action' => $l->action,
                'entity' => $l->entity,
                'actor' => $l->actor->name ?? 'System',
                'metadata' => $l->metadata,
                'created_ago' => optional($l->created_at)->diffForHumans(),
                'created_at' => optional($l->created_at)->toIso8601String(),
            ])->all(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
            ],
        ]);
    }

    // --------------------------------------------------------------- Settings
    public function settings(): JsonResponse
    {
        return response()->json(['data' => [
            'admin_debug_enabled' => DevSettings::adminDebugEnabled(),
            'branding' => [
                'logo_url' => Branding::logoUrl(),
                'favicon_url' => Branding::faviconUrl(),
            ],
            'downloads' => collect(AppDownloads::adminRows())->map(fn ($row, $platform) => [
                'platform' => $platform,
                'label' => AppDownloads::PLATFORMS[$platform] ?? $platform,
                'version' => $row['version'] ?? null,
                'url' => $row['url'] ?? null,
                'has_file' => ! empty($row['path'] ?? null),
            ])->values(),
            'note' => 'Logo, favicon and app-download files are managed on the website.',
        ]]);
    }

    public function updateDev(Request $request): JsonResponse
    {
        $request->validate(['show_error_details' => ['required', 'boolean']]);

        DevSettings::set($request->boolean('show_error_details'));
        ActivityLog::log('dev_settings_updated', 'Setting', null, [
            'show_error_details' => $request->boolean('show_error_details'),
        ]);

        return response()->json([
            'data' => ['admin_debug_enabled' => DevSettings::adminDebugEnabled()],
            'message' => 'Developer settings saved.',
        ]);
    }

    // ---------------------------------------------------------- Subscriptions
    public function subscriptions(Request $request): JsonResponse
    {
        $subs = Subscription::with(['user:id,name,email', 'product:id,name', 'plan:id,name'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('search'), fn ($q) => $q->whereHas('user', fn ($u) => $u
                ->where('name', 'like', '%'.$request->string('search').'%')
                ->orWhere('email', 'like', '%'.$request->string('search').'%')))
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => collect($subs->items())->map(fn (Subscription $s) => $this->subRow($s))->all(),
            'meta' => [
                'current_page' => $subs->currentPage(),
                'last_page' => $subs->lastPage(),
                'total' => $subs->total(),
            ],
        ]);
    }

    public function subscription(Subscription $subscription): JsonResponse
    {
        $subscription->load(['user:id,name,email', 'product:id,name', 'plan:id,name', 'order:id,order_number']);

        return response()->json(['data' => array_merge($this->subRow($subscription), [
            'customer_email' => $subscription->user->email ?? null,
            'order_number' => $subscription->order->order_number ?? null,
            'start_date' => optional($subscription->start_date)->toDateString(),
        ])]);
    }

    public function extendSubscription(Request $request, Subscription $subscription, SubscriptionService $service): JsonResponse
    {
        $data = $request->validate(['days' => ['required', 'integer', 'min:1', 'max:3650']]);
        $service->extend($subscription, $data['days']);

        return response()->json(['data' => $this->subRow($subscription->fresh()), 'message' => 'Subscription extended.']);
    }

    public function setSubscriptionState(Request $request, Subscription $subscription, NotificationService $notifications): JsonResponse
    {
        $data = $request->validate(['state' => ['required', Rule::in(['suspended', 'revoked', 'expired', 'active'])]]);

        $subscription->update(['status' => $data['state']]);

        if (in_array($data['state'], ['revoked', 'expired'], true)
            && $subscription->account && $subscription->account->status === 'assigned') {
            $subscription->account->release();
        }

        if ($data['state'] === 'revoked') {
            $notifications->notifyUserAccessRevoked($subscription->user, $subscription);
        }

        ActivityLog::log('subscription_'.$data['state'], 'Subscription', $subscription->id);

        return response()->json(['data' => $this->subRow($subscription->fresh()), 'message' => 'Subscription updated.']);
    }

    // -------------------------------------------------------- Payment methods
    public function paymentMethods(): JsonResponse
    {
        return response()->json([
            'data' => PaymentMethod::orderBy('sort_order')->get()->map(fn (PaymentMethod $m) => [
                'id' => $m->id,
                'code' => $m->code,
                'name' => $m->name,
                'description' => $m->description,
                'account_number' => $m->account_number,
                'instructions' => $m->instructions,
                'enabled' => (bool) $m->enabled,
                'sort_order' => $m->sort_order,
                'has_qr' => ! empty($m->qr_image),
                'qr_url' => $m->qr_image ? asset('storage/'.$m->qr_image) : null,
            ])->all(),
        ]);
    }

    public function storePaymentMethod(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:255', Rule::unique('payment_methods', 'code')],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'account_number' => ['nullable', 'string', 'max:255'],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'enabled' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $method = PaymentMethod::create($data);
        ActivityLog::log('payment_method_created', 'PaymentMethod', $method->id, ['code' => $method->code]);

        return response()->json(['data' => ['id' => $method->id], 'message' => 'Payment method added.'], 201);
    }

    public function uploadPaymentMethodQr(Request $request, PaymentMethod $paymentMethod): JsonResponse
    {
        $request->validate([
            'qr_image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        if ($paymentMethod->qr_image && \Illuminate\Support\Facades\Storage::disk('public')->exists($paymentMethod->qr_image)) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($paymentMethod->qr_image);
        }

        $paymentMethod->forceFill([
            'qr_image' => $request->file('qr_image')->store('payment-methods', 'public'),
        ])->save();

        ActivityLog::log('payment_method_qr_updated', 'PaymentMethod', $paymentMethod->id, ['code' => $paymentMethod->code]);

        return response()->json([
            'data' => ['qr_url' => asset('storage/'.$paymentMethod->qr_image)],
            'message' => 'QR code uploaded.',
        ]);
    }

    public function updatePaymentMethod(Request $request, PaymentMethod $paymentMethod): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'account_number' => ['nullable', 'string', 'max:255'],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'enabled' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $paymentMethod->update($data);
        ActivityLog::log('payment_method_updated', 'PaymentMethod', $paymentMethod->id, ['code' => $paymentMethod->code]);

        return response()->json(['message' => 'Payment method updated.']);
    }

    // -------------------------------------------------------- Contact messages
    public function contactMessages(): JsonResponse
    {
        $messages = ContactMessage::latest()->paginate(30);

        return response()->json([
            'data' => collect($messages->items())->map(fn (ContactMessage $m) => [
                'id' => $m->id,
                'name' => $m->name,
                'email' => $m->email,
                'subject' => $m->subject,
                'message' => $m->message,
                'is_read' => (bool) $m->is_read,
                'created_ago' => optional($m->created_at)->diffForHumans(),
                'created_at' => optional($m->created_at)->toIso8601String(),
            ])->all(),
            'meta' => [
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'unread' => ContactMessage::where('is_read', false)->count(),
            ],
        ]);
    }

    public function contactMessage(ContactMessage $contactMessage): JsonResponse
    {
        if (! $contactMessage->is_read) {
            $contactMessage->update(['is_read' => true]);
        }

        return response()->json(['data' => [
            'id' => $contactMessage->id,
            'name' => $contactMessage->name,
            'email' => $contactMessage->email,
            'subject' => $contactMessage->subject,
            'message' => $contactMessage->message,
            'created_at' => optional($contactMessage->created_at)->toIso8601String(),
        ]]);
    }

    public function deleteContactMessage(Request $request, ContactMessage $contactMessage, DeletionService $deletions): JsonResponse
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'max:2000']])['reason'];
        $deletions->delete($contactMessage, $reason);

        return response()->json(['message' => 'Message deleted.']);
    }

    private function subRow(Subscription $s): array
    {
        return [
            'id' => $s->id,
            'customer_name' => $s->user->name ?? '—',
            'product' => $s->product->name ?? '—',
            'plan' => $s->plan->name ?? null,
            'status' => $s->status,
            'expiry_date' => optional($s->expiry_date)->toDateString(),
            'days_remaining' => $s->expiry_date ? (int) now()->diffInDays($s->expiry_date, false) : null,
        ];
    }
}
