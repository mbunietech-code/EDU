<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'order_number',
        'product_id',
        'plan_id',
        'tool_id',
        'amount',
        'device',
        'status',
        'payment_instructions',
        'rejection_reason',
        'confirmed_at',
        'software_access_expires_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'confirmed_at' => 'datetime',
        'software_access_expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function tool(): BelongsTo
    {
        return $this->belongsTo(Tool::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * A customer may cancel their own order only while it is still pending
     * and no payment has been approved. Confirmed orders can never be
     * cancelled by the customer.
     */
    public function canBeCancelledByCustomer(): bool
    {
        return $this->status === 'pending'
            && ! $this->payments()->where('status', 'approved')->exists();
    }

    public function isSoftware(): bool
    {
        return $this->product?->isSoftware() ?? false;
    }

    public function isToolOrder(): bool
    {
        return $this->tool_id !== null;
    }

    public function itemName(): string
    {
        return $this->tool?->name ?? $this->product?->name ?? 'N/A';
    }

    public function softwareAccessActive(): bool
    {
        return $this->isConfirmed()
            && $this->software_access_expires_at
            && $this->software_access_expires_at->isFuture();
    }

    public function softwareAccessLocked(): bool
    {
        return $this->isConfirmed()
            && ($this->software_access_expires_at === null || $this->software_access_expires_at->isPast());
    }
}
