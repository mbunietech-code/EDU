<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Tool extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'image',
        'version',
        'license_key',
        'price',
        'status',
        'is_featured',
        'sort_order',
        'confirmed_at',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_featured' => 'boolean',
        'confirmed_at' => 'datetime',
    ];

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function isSoftwareDownload(): bool
    {
        return $this->status !== 'archived';
    }

    public function downloads(): HasMany
    {
        return $this->hasMany(ToolDownload::class)->orderBy('sort_order');
    }

    public function primaryDownload(): HasOne
    {
        return $this->hasOne(ToolDownload::class)->oldestOfMany('sort_order');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function imageUrl(): ?string
    {
        return $this->image ? asset('storage/' . $this->image) : null;
    }
}
