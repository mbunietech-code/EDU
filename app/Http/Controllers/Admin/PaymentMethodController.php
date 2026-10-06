<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePaymentMethodRequest;
use App\Http\Requests\Admin\UpdatePaymentMethodRequest;
use App\Http\Requests\Admin\UploadPaymentMethodQrRequest;
use App\Models\ActivityLog;
use App\Models\PaymentMethod;
use App\Services\Payments\GatewayException;
use App\Services\Payments\GatewayPaymentService;
use App\Services\Payments\PaymentGatewaySettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class PaymentMethodController extends Controller
{
    public function index(GatewayPaymentService $gateways, PaymentGatewaySettingsService $settings)
    {
        $paymentMethods = PaymentMethod::orderBy('sort_order')->get();
        $callbackToken = $settings->callbackToken();

        $onlineGateways = [];
        foreach (PaymentGatewaySettingsService::FIELDS as $key => $fields) {
            $gateway = $gateways->gateway($key);
            $onlineGateways[$key] = [
                'label' => $gateway->label(),
                'fields' => $fields,
                'values' => $settings->formValues($key),
                'ready' => $gateway->isEnabled(),
                'redirect' => $gateway->usesRedirect(),
                'callback_url' => $gateway->usesRedirect() ? null : route('api.payments.callback', [$key, $callbackToken]),
            ];
        }

        return view('admin.payment-methods.index', compact('paymentMethods', 'onlineGateways'));
    }

    public function updateGateway(Request $request, string $gateway, PaymentGatewaySettingsService $settings, GatewayPaymentService $gateways)
    {
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
        $settings->save($gateway, $validated);
        $gateways->gateway($gateway)->forgetToken();

        ActivityLog::log('payment_gateway_updated', 'PaymentGateway', null, [
            'gateway' => $gateway,
            'enabled' => ! empty($validated['enabled']),
        ]);

        $label = $gateways->gateway($gateway)->label();

        if (! empty($validated['enabled']) && ! $gateways->gateway($gateway)->isEnabled()) {
            return back()->with('error', "{$label} saved, but it stays off at checkout until all keys are filled in.");
        }

        return back()->with('success', "{$label} settings saved.");
    }

    public function testGateway(string $gateway, GatewayPaymentService $gateways)
    {
        abort_unless(isset(PaymentGatewaySettingsService::FIELDS[$gateway]), 404);

        $driver = $gateways->gateway($gateway);

        try {
            $driver->testConnection();
        } catch (GatewayException $e) {
            return back()->with('error', $driver->label().' rejected the connection: '.$e->getMessage().' Check the keys and the environment (sandbox / live).');
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Could not test '.$driver->label().': fill in all keys first.');
        }

        return back()->with('success', $driver->label().' connected successfully.');
    }

    public function regenerateCallbackToken(PaymentGatewaySettingsService $settings)
    {
        $settings->regenerateCallbackToken();

        ActivityLog::log('payment_callback_token_regenerated', 'PaymentGateway', null, []);

        return back()->with('success', 'New callback URLs created. Update them on the AzamPay and ClickPesa dashboards now.');
    }

    public function store(StorePaymentMethodRequest $request)
    {
        $paymentMethod = PaymentMethod::create($request->validated());

        ActivityLog::log(
            'payment_method_created',
            'PaymentMethod',
            $paymentMethod->id,
            ['code' => $paymentMethod->code]
        );

        return back()->with('success', 'Payment method added. Upload its QR code below.');
    }

    public function update(UpdatePaymentMethodRequest $request, PaymentMethod $paymentMethod)
    {
        $paymentMethod->update($request->validated());

        ActivityLog::log(
            'payment_method_updated',
            'PaymentMethod',
            $paymentMethod->id,
            ['code' => $paymentMethod->code]
        );

        return back()->with('success', 'Payment method updated.');
    }

    public function uploadQr(UploadPaymentMethodQrRequest $request, PaymentMethod $paymentMethod)
    {
        if ($paymentMethod->qr_image) {
            Storage::disk('public')->delete($paymentMethod->qr_image);
        }

        $paymentMethod->forceFill([
            'qr_image' => $request->file('qr_image')->store('payment-methods', 'public'),
        ])->save();

        ActivityLog::log(
            'payment_method_qr_updated',
            'PaymentMethod',
            $paymentMethod->id,
            ['code' => $paymentMethod->code]
        );

        return back()->with('success', 'QR code uploaded.');
    }

    public function removeQr(PaymentMethod $paymentMethod)
    {
        if ($paymentMethod->qr_image) {
            Storage::disk('public')->delete($paymentMethod->qr_image);
        }

        $paymentMethod->forceFill(['qr_image' => null])->save();

        ActivityLog::log(
            'payment_method_qr_removed',
            'PaymentMethod',
            $paymentMethod->id,
            ['code' => $paymentMethod->code]
        );

        return back()->with('success', 'QR code removed.');
    }
}