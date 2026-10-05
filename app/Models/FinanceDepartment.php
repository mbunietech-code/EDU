<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinanceDepartment extends Model
{
    protected $fillable = ['name', 'code', 'description', 'status'];

    public function positions(): HasMany
    {
        return $this->hasMany(FinancePosition::class);
    }

    public function staff(): HasMany
    {
        return $this->hasMany(FinanceStaff::class);
    }
}
