@props(['gatewayPayment', 'receiptUrl' => null])
{{-- Proof of an automatic payment: what the provider confirmed. Replaces the screenshot of manual payments. --}}
@php
    $g = $gatewayPayment;
    $label = config("payments.gateways.{$g->gateway}.label") ?? ucfirst($g->gateway);
    $method = $g->network ? str_replace(['MpesaTZ', 'TigoTZ', 'AirtelTZ', 'HaloPesaTZ'], ['M-Pesa', 'Mixx by Yas (Tigo)', 'Airtel Money', 'HaloPesa'], $g->network) : null;
    $rows = array_filter([
        'Paid through' => $label,
        'Paid with' => $method,
        'Account' => $g->phone ?: null,
        'Amount' => 'TZS '.number_format((float) $g->amount)
            .($g->charged_currency && $g->charged_currency !== 'TZS' ? ' (charged '.$g->charged_currency.' '.number_format((float) $g->charged_amount, 2).')' : ''),
        $label.' confirmation code' => $g->provider_reference,
        $label.' transaction ID' => $g->provider_transaction_id,
        'Our reference' => $g->external_id,
        'Confirmed at' => optional($g->completed_at)->format('d M Y, H:i'),
    ], fn ($v) => filled($v));
@endphp
<div>
    <h2 class="mbui-section-label">Payment proof</h2>
    <div class="mt-3 mbui-card overflow-hidden">
        <div class="flex items-center gap-3 border-b border-gray-100 px-5 py-4">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border {{ $g->isSuccessful() ? 'border-emerald-200 bg-emerald-50 text-emerald-600' : 'border-gray-200 bg-gray-50 text-gray-500' }}">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                </svg>
            </span>
            <div class="min-w-0">
                <p class="text-sm font-semibold text-gray-900">{{ $g->isSuccessful() ? 'Verified automatically with '.$label : 'Waiting for '.$label }}</p>
                <p class="text-xs text-gray-500">{{ $g->isSuccessful() ? 'The money was confirmed directly with '.$label.', not from a screenshot.' : 'Status: '.$g->status }}</p>
            </div>
        </div>
        <dl class="divide-y divide-gray-100 text-sm">
            @foreach ($rows as $name => $value)
                <div class="flex items-start justify-between gap-4 px-5 py-2.5">
                    <dt class="text-gray-500">{{ $name }}</dt>
                    <dd class="break-all text-right font-medium text-gray-900 {{ str_contains($name, 'code') || str_contains($name, 'ID') || str_contains($name, 'reference') ? 'font-mono text-xs' : '' }}">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
        @if ($receiptUrl)
            <div class="border-t border-gray-100 px-5 py-3 text-right">
                <a href="{{ $receiptUrl }}" target="_blank" rel="noopener" class="mbui-anchor text-sm font-semibold">Open receipt</a>
            </div>
        @endif
    </div>
</div>
