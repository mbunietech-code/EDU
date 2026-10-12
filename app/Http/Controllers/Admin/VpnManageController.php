<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\CurrencyRateService;
use App\Services\MvpnClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use RuntimeException;

/**
 * Manage Mbunie VPN from the EduHub admin: customers, subscriptions, devices,
 * payments and plans, through the signed partner API. Every change is sent
 * with the admin's email (VPN audit log) and logged here too.
 */
class VpnManageController extends Controller
{
    public function __construct(private MvpnClient $vpn) {}

    // ---- pages ---------------------------------------------------------

    public function customers(Request $request): View
    {
        [$data, $error] = $this->fetch(fn () => $this->vpn->admin('GET', 'customers', $request->only('q', 'page')));

        return view('admin.vpn.customers', ['result' => $data, 'error' => $error, 'q' => $request->query('q')]);
    }

    public function customer(int $id): View
    {
        [$data, $error] = $this->fetch(fn () => $this->vpn->admin('GET', "customers/{$id}"));
        [$plans] = $this->fetch(fn () => $this->vpn->admin('GET', 'plans'));
        abort_if($data === null && $error === null, 404);

        return view('admin.vpn.customer', ['c' => $data, 'error' => $error, 'plans' => $plans['data'] ?? []]);
    }

    public function devices(Request $request): View
    {
        $filter = in_array($request->query('filter'), ['online', 'removed', 'errors'], true) ? $request->query('filter') : 'all';
        [$data, $error] = $this->fetch(fn () => $this->vpn->admin('GET', 'devices', ['filter' => $filter] + $request->only('q', 'page')));

        return view('admin.vpn.devices', ['result' => $data, 'error' => $error, 'q' => $request->query('q'), 'filter' => $filter]);
    }

    public function payments(Request $request): View
    {
        $status = in_array($request->query('status'), ['review', 'pending', 'paid', 'failed'], true) ? $request->query('status') : 'review';
        [$data, $error] = $this->fetch(fn () => $this->vpn->admin('GET', 'invoices', ['status' => $status] + $request->only('q', 'page')));

        return view('admin.vpn.payments', ['result' => $data, 'error' => $error, 'q' => $request->query('q'), 'status' => $status]);
    }

    public function plans(CurrencyRateService $currency): View
    {
        [$data, $error] = $this->fetch(fn () => $this->vpn->admin('GET', 'plans'));

        // Live rates (1 TZS in USD / CNY) so typing one price fills in the others.
        return view('admin.vpn.plans', ['plans' => $data['data'] ?? [], 'error' => $error, 'rates' => $currency->rates()]);
    }

    /** Payment screenshot, streamed from the VPN server. */
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

    // ---- actions -------------------------------------------------------

    public function customerStatus(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:active,suspended'],
            'reason' => ['nullable', 'string', 'max:200'],
        ]);

        return $this->act('POST', "customers/{$id}/status", $data, 'vpn_customer_'.$data['status'], $id,
            $data['status'] === 'suspended' ? 'Customer suspended: VPN off and signed out.' : 'Customer restored.');
    }

    public function grant(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'plan_code' => ['required', 'string', 'max:40'],
            'note' => ['nullable', 'string', 'max:200'],
        ]);

        return $this->act('POST', "customers/{$id}/grant", $data, 'vpn_plan_granted', $id, "Plan {$data['plan_code']} granted.");
    }

    public function extend(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'days' => ['required', 'integer', 'min:1', 'max:3650'],
            'note' => ['nullable', 'string', 'max:200'],
        ]);

        return $this->act('POST', "subscriptions/{$id}/extend", $data, 'vpn_subscription_extended', $id, "Added {$data['days']} day(s).");
    }

    public function suspend(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:200']]);

        return $this->act('POST', "subscriptions/{$id}/suspend", $data, 'vpn_subscription_suspended', $id, 'Subscription suspended: VPN is off.');
    }

    public function restore(int $id): RedirectResponse
    {
        return $this->act('POST', "subscriptions/{$id}/restore", [], 'vpn_subscription_restored', $id, 'Subscription restored.');
    }

    public function removeDevice(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:200']]);

        return $this->act('POST', "devices/{$id}/remove", $data, 'vpn_device_removed', $id,
            'Device removed: blocked, customer signed out, VPN keys changed.');
    }

    public function allowDevice(int $id): RedirectResponse
    {
        return $this->act('POST', "devices/{$id}/allow", [], 'vpn_device_allowed', $id, 'Device allowed again.');
    }

    public function approve(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:300']]);

        return $this->act('POST', "invoices/{$id}/approve", $data, 'vpn_payment_approved', $id, 'Payment approved: the VPN is active.');
    }

    public function reject(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:300']]);

        return $this->act('POST', "invoices/{$id}/reject", $data, 'vpn_payment_rejected', $id, 'Payment rejected.');
    }

    public function updatePlan(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'days' => ['required', 'integer', 'min:1', 'max:3650'],
            'max_devices' => ['required', 'integer', 'min:1', 'max:20'],
            'price_usd' => ['required', 'numeric', 'min:0'],
            'price_cny' => ['required', 'numeric', 'min:0'],
            'price_tzs' => ['required', 'numeric', 'min:0'],
        ]);
        $data['is_active'] = $request->boolean('is_active');

        return $this->act('PUT', "plans/{$id}", $data, 'vpn_plan_updated', $id, "Plan \"{$data['name']}\" saved.");
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

    private function act(string $method, string $path, array $data, string $log, int $id, string $ok): RedirectResponse
    {
        try {
            $this->vpn->admin($method, $path, [], $data + ['actor' => (string) auth()->user()->email]);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }
        \Illuminate\Support\Facades\Cache::forget('mvpn:payments_to_review');
        ActivityLog::log($log, 'Vpn', $id, $data);

        return back()->with('success', $ok);
    }
}
