<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One way to pay at checkout (M-Pesa via ClickPesa, card via PesaPal, ...).
 * kind: mobile | card | bank | wallet | manual.
 */
class PaymentChannel extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'kind',
        'gateway',
        'network',
        'enabled',
        'sort_order',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * How checkout collects the payment:
     * push   – USSD prompt on the customer's phone (ClickPesa, AzamPay)
     * hosted – PesaPal's form inside our checkout page
     * card   – ClickPesa card page
     * paypal – PayPal
     * manual – QR code and proof upload
     */
    public function flow(): string
    {
        return match (true) {
            $this->kind === 'manual' => 'manual',
            $this->gateway === 'paypal' => 'paypal',
            $this->gateway === 'clickpesa_card' => 'card',
            $this->gateway === 'pesapal' => 'hosted',
            default => 'push',
        };
    }

    public function needsPhone(): bool
    {
        return in_array($this->flow(), ['push', 'card'], true)
            || ($this->flow() === 'hosted' && $this->kind === 'mobile');
    }

    public function needsName(): bool
    {
        return $this->flow() === 'card';
    }
}
