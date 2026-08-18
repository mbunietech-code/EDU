<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePaymentMethodRequest;
use App\Http\Requests\Admin\UploadPaymentMethodQrRequest;
use App\Models\ActivityLog;
use App\Models\PaymentMethod;
use Illuminate\Support\Facades\Storage;

class PaymentMethodController extends Controller
{
    public function index()
    {
        $paymentMethods = PaymentMethod::orderBy('sort_order')->get();

        return view('admin.payment-methods.index', compact('paymentMethods'));
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