<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\Payments\GatewayException;
use App\Services\Payments\GatewayPaymentService;
use App\Services\Payments\PaymentGatewaySettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Automatic payment gateways (AzamPay, ClickPesa, card, PayPal) for the
 * admin app: same settings as Admin → Payment Methods → Automatic.
 */
class PaymentGatewayController extends Controller
{
    public function __construct(
        protected GatewayPaymentService $gateways,
        protected PaymentGatewaySettingsService $settings,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizeManage($request);
        $token = $this->settings->callbackToken();

        $rows = [];
        foreach (PaymentGatewaySettingsService::FIELDS as $key => $fields) {
            $gateway = $this->gateways->gateway($key);
            $values = $this->settings->formValues($key);

            $rows[] = [
                'key' => $key,
                'label' => $gateway->label(),
                'ready' => $gateway->isEnabled(),
                'enabled' => (bool) ($values['enabled'] ?? false),
                'callback_url' => $gateway->usesRedirect() ? null : route('api.payments.callback', [$key, $token]),
                'fields' => collect($fields)->map(fn ($meta, $field) => [
                    'key' => $field,
                    'label' => $meta[0],
                    'type' => $meta[1],
                    // Secrets are never sent back, only whether one is saved.
                    'value' => $meta[1] === 'secret' ? null : $values[$field],
                    'is_set' => $meta[1] === 'secret' ? (bool) $values[$field] : null,
                ])->values(),
            ];
        }

        return response()->json(['data' => $rows]);
    }

    public function update(Request $request, string $gateway): JsonResponse
    {
        $this->authorizeManage($request);
        abort_unless(isset(PaymentGatewaySettingsService::FIELDS[$gateway]), 404);

        $rules = [];
        foreach (PaymentGatewaySettingsService::FIELDS[$gateway] as $field => [, $type]) {
            $rules[$field] = match ($type) {
                'toggle' => ['nullable', 'boolean'],
                'mode' => ['required', Rule::in(['sandbox', 'live'])],
                default => ['nullable', 'string', 'max:500'],
            };
        }

        $validated = $request->validate($rules);
        $this->settings->save($gateway, $validated);
        $driver = $this->gateways->gateway($gateway);
        $driver->forgetToken();

        ActivityLog::log('payment_gateway_updated', 'PaymentGateway', null, ['gateway' => $gateway, 'enabled' => ! empty($validated['enabled']), 'via' => 'api']);

        $warning = ! empty($validated['enabled']) && ! $driver->isEnabled();

        return response()->json([
            'ready' => $driver->isEnabled(),
            'message' => $warning
                ? $driver->label().' saved, but it stays off at checkout until all keys are filled in.'
                : $driver->label().' settings saved.',
        ], $warning ? 422 : 200);
    }

    public function test(Request $request, string $gateway): JsonResponse
    {
        $this->authorizeManage($request);
        abort_unless(isset(PaymentGatewaySettingsService::FIELDS[$gateway]), 404);
        $driver = $this->gateways->gateway($gateway);

        try {
            $driver->testConnection();
        } catch (GatewayException $e) {
            return response()->json(['ok' => false, 'message' => $driver->label().' rejected the connection: '.$e->getMessage()], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['ok' => false, 'message' => 'Could not test '.$driver->label().': fill in all keys first.'], 422);
        }

        return response()->json(['ok' => true, 'message' => $driver->label().' connected successfully.']);
    }

    protected function authorizeManage(Request $request): void
    {
        $user = $request->user();
        abort_unless($user->isSuperAdmin() || $user->hasPermission('payment_methods.manage'), 403, 'You cannot manage payment methods.');
    }
}
