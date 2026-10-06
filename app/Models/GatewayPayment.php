<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One mobile money push (USSD prompt) sent through a payment gateway.
 * Status: pending → success | failed | expired.
 */
class GatewayPayment extends Model
{
    protected $fillable = [
        'order_id',
        'user_id',
        'payment_id',
        'gateway',
        'network',
        'phone',
        'amount',
        'currency',
        'external_id',
        'provider_transaction_id',
        'provider_reference',
        'status',
        'message',
        'callback_payload',
        'completed_at',
        'last_checked_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'completed_at' => 'datetime',
        'last_checked_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isSuccessful(): bool
    {
        return $this->status === 'success';
    }

    public function isFinal(): bool
    {
        return in_array($this->status, ['success', 'failed'], true);
    }
}
