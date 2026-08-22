<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'account_number',
        'qr_image',
        'link_url',
        'store_url',
        'instructions',
        'enabled',
        'sort_order',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('enabled', true);
    }

    public function qrImageUrl(): ?string
    {
        return $this->qr_image ? asset('storage/'.$this->qr_image) : null;
    }
}