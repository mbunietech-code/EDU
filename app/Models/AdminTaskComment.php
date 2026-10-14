<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminTaskComment extends Model
{
    protected $fillable = ['admin_task_id', 'user_id', 'body'];

    public function task(): BelongsTo
    {
        return $this->belongsTo(AdminTask::class, 'admin_task_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
