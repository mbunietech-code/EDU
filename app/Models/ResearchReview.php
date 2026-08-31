<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResearchReview extends Model
{
    protected $fillable = ['research_id', 'reviewer_id', 'action', 'comment'];

    public function research(): BelongsTo
    {
        return $this->belongsTo(Research::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function actionLabel(): string
    {
        return match ($this->action) {
            'submitted' => 'Submitted for review',
            'approved' => 'Approved',
            'published' => 'Published',
            'rejected' => 'Rejected',
            'changes_requested' => 'Changes requested',
            default => ucfirst(str_replace('_', ' ', $this->action)),
        };
    }
}
