<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinanceEmploymentType extends Model
{
    protected $fillable = ['name', 'code', 'status'];

    public function staff(): HasMany
    {
        return $this->hasMany(FinanceStaff::class);
    }
}
