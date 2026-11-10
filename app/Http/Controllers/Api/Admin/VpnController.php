<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Services\CurrencyRateService;
use App\Services\MvpnClient;
use App\Services\VpnAccessService;
use App\Services\VpnPlanSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Mbunie VPN admin for the MHub app: the same pages and actions as
 * admin/vpn on the web. Rows come straight from the VPN control plane
 * (signed partner API); VPN orders sold on EduHub come from our database.
 */
class VpnController extends Controller
{
    public function __construct(private MvpnClient $vpn) {}

    public function overview(CurrencyRateService $currency): JsonResponse
    {
        [$stats, $error] = $this->fetch(fn () => $this->vpn->stats());

        $orders = Order::with(['user:id,name,email', 'plan:id,name,mvpn_plan_code'])
            ->whereHas('product', fn ($q) => $q->where('type', 'vpn'))
            ->latest()
            ->limit(30)
            ->get();

        return response()->json([
            'stats' => $stats,
            'error' => $error,
            'rates' => $currency->rates(),
            'can_manage' => (bool) request()->user()?->can('vpn.manage'),
            'orders' => $orders->map(fn (Order $o) => [
                'id' => $o->id,
                'order_number' => $o->order_number,
                'status' => $o->status,
                'customer_email' => $o->user?->email,
                'plan' => $o->plan?->name,
                'plan_code' => $o->plan?->mvpn_plan_code,
                'activated' => $o->vpn_activated_at !== null,
                'expires_at' => optional($o->vpn_expires_at)->toIso8601String(),
                'activation_error' => $o->vpn_activation_error,
                'activation_attempts' => (int) $o->vpn_activation_attempts,
                'can_retry' => $o->status === 'confirmed' && ! $o->vpn_activated_at,
            ])->values(),
        ]);
    }

    public function retry(Order $order, VpnAccessService $access): JsonResponse
    {
        abort_unless($order->product?->isVpn(), 404);

        ActivityLog::log('vpn_activation_retry', 'Order', $order->id);
        $ok = $access->activateForOrder($order);

        return $ok
            ? response()->json(['message' => "VPN activated for order #{$order->order_number}."])
            : response()->json(['message' => 'Still failing: '.($order->fresh()->vpn_activation_error ?? 'unknown error')], 422);
    }

    public function customers(Request $request): JsonResponse
    {
        return $this->page(fn () => $this->vpn->admin('GET', 'customers', $request->only('q', 'page')));
    }

    public function customer(int $id): JsonResponse
    {
        [$data, $error] = $this->fetch(fn () => $this->vpn->admin('GET', "customers/{$id}"));
        if ($error) {
            return response()->json(['message' => $error], 503);
        }
        abort_if($data === null, 404);
        [$plans] = $this->fetch(fn () => $this->vpn->admin('GET', 'plans'));

        return response()->json($data + ['plans' => $plans['data'] ?? []]);
    }

    public function devices(Request $request): JsonResponse
    {
        $filter = in_array($request->query('filter'), ['online', 'removed', 'errors'], true) ? $request->query('filter') : 'all';

        return $this->page(fn () => $this->vpn->admin('GET', 'devices', ['filter' => $filter] + $request->only('q', 'page')));
    }

    public function payments(Request $request): JsonResponse
    {
        $status = in_array($request->query('status'), ['review', 'pending', 'paid', 'failed'], true) ? $request->query('status') : 'review';

        return $this->page(fn () => $this->vpn->admin('GET', 'invoices', ['status' => $status] + $request->only('q', 'page')));
    }

    public function plans(CurrencyRateService $currency): JsonResponse
    {
        [$data, $error] = $this->fetch(fn () => $this->vpn->admin('GET', 'plans'));
        if ($error) {
            return response()->json(['message' => $error], 503);
        }

        return response()->json([
            'data' => $data['data'] ?? [],
            'rates' => $currency->rates(),
            'eduhub_products' => \App\Models\Product::where('type', 'vpn')->pluck('name'),
        ]);
    }

    public function proof(int $id): Response
    {
        try {
            [$bytes, $type] = $this->vpn->adminFile("invoices/{$id}/proof");
        } catch (RuntimeException) {
            abort(404);
        }
        abort_unless(str_starts_with($type, 'image/') || $type === 'application/pdf', 404);

        return response($bytes, 200, ['Content-Type' => $type, 'Cache-Control' => 'private, no-store']);
    }

    // ---- actions (vpn.manage) ------------------------------------------

    public function customerStatus(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:active,suspended'],
            'reason' => ['nullable', 'string', 'max:200'],
        ]);

        return $this->act('POST', "customers/{$id}/status", $data, 'vpn_customer_'.$data['status'], $id,
            $data['status'] === 'suspended' ? 'Customer suspended: VPN off and signed out.' : 'Customer restored.');
    }

    public function grant(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'plan_code' => ['required', 'string', 'max:40'],
            'note' => ['nullable', 'string', 'max:200'],
        ]);

        return $this->act('POST', "customers/{$id}/grant", $data, 'vpn_plan_granted', $id, "Plan {$data['plan_code']} granted.");
    }

    public function extend(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'days' => ['required', 'integer', 'min:1', 'max:3650'],
            'note' => ['nullable', 'string', 'max:200'],
        ]);

        return $this->act('POST', "subscriptions/{$id}/extend", $data, 'vpn_subscription_extended', $id, "Added {$data['days']} day(s).");
    }

    public function suspend(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:200']]);

        return $this->act('POST', "subscriptions/{$id}/suspend", $data, 'vpn_subscription_suspended', $id, 'Subscription suspended: VPN is off.');
    }

    public function restore(int $id): JsonResponse
    {
        return $this->act('POST', "subscriptions/{$id}/restore", [], 'vpn_subscription_restored', $id, 'Subscription restored.');
    }

    public function removeDevice(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:200']]);

        return $this->act('POST', "devices/{$id}/remove", $data, 'vpn_device_removed', $id,
            'Device removed: blocked, customer signed out, VPN keys changed.');
    }

    public function allowDevice(int $id): JsonResponse
    {
        return $this->act('POST', "devices/{$id}/allow", [], 'vpn_device_allowed', $id, 'Device allowed again.');
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:300']]);

        return $this->act('POST', "invoices/{$id}/approve", $data, 'vpn_payment_approved', $id, 'Payment approved: the VPN is active.');
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:300']]);

        return $this->act('POST', "invoices/{$id}/reject", $data, 'vpn_payment_rejected', $id, 'Payment rejected.');
    }

    public function updatePlan(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'days' => ['required', 'integer', 'min:1', 'max:3650'],
            'max_devices' => ['required', 'integer', 'min:1', 'max:20'],
            'price_usd' => ['required', 'numeric', 'min:0'],
            'price_cny' => ['required', 'numeric', 'min:0'],
            'price_tzs' => ['required', 'numeric', 'min:0'],
            'is_active' => ['required', 'boolean'],
        ]);
        $data['is_active'] = (bool) $data['is_active'];

        $result = $this->act('PUT', "plans/{$id}", $data, 'vpn_plan_updated', $id, "Plan \"{$data['name']}\" saved.");
        if ($result->getStatusCode() !== 200) {
            return $result;
        }

        // Keep the EduHub "Mbunie VPN" product plans the same.
        try {
            app(VpnPlanSync::class)->sync();

            return response()->json(['message' => "Plan \"{$data['name']}\" saved, here and on the EduHub VPN product."]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => 'Saved on the VPN, but the EduHub product could not be updated: '.$e->getMessage()]);
        }
    }

    public function syncPlans(): JsonResponse
    {
        try {
            $s = app(VpnPlanSync::class)->sync();
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => "EduHub VPN product updated: {$s['created']} added, {$s['updated']} changed, {$s['disabled']} switched off."]);
    }

    // ---- helpers -------------------------------------------------------

    /** @return array{0: array<string,mixed>|null, 1: string|null} */
    private function fetch(callable $call): array
    {
        if (! $this->vpn->configured()) {
            return [null, 'MVPN_PARTNER_SECRET is not set in the EduHub .env.'];
        }
        try {
            return [$call(), null];
        } catch (RuntimeException $e) {
            return [null, $e->getCode() === 404 ? null : 'Could not reach the VPN server: '.$e->getMessage()];
        }
    }

    /** A partner-API list ({data, meta: {page, last_page, total}}) passed through. */
    private function page(callable $call): JsonResponse
    {
        [$data, $error] = $this->fetch($call);
        if ($error) {
            return response()->json(['message' => $error], 503);
        }

        return response()->json([
            'data' => $data['data'] ?? [],
            'meta' => $data['meta'] ?? ['page' => 1, 'last_page' => 1, 'total' => count($data['data'] ?? [])],
        ]);
    }

    private function act(string $method, string $path, array $data, string $log, int $id, string $ok): JsonResponse
    {
        try {
            $this->vpn->admin($method, $path, [], $data + ['actor' => (string) request()->user()->email]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        Cache::forget('mvpn:payments_to_review');
        ActivityLog::log($log, 'Vpn', $id, $data);

        return response()->json(['message' => $ok]);
    }
}
