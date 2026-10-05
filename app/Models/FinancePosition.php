<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancePosition extends Model
{
    protected $fillable = ['finance_department_id', 'name', 'code', 'salary_grade', 'description', 'status'];

    public function department(): BelongsTo
    {
        return $this->belongsTo(FinanceDepartment::class, 'finance_department_id');
    }

    public function staff(): HasMany
    {
        return $this->hasMany(FinanceStaff::class);
    }
}
