<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Research;
use App\Models\ResearchReview;
use App\Models\User;

/**
 * The submit -> review -> publish lifecycle for a piece of research.
 * Every transition is logged (research_reviews + activity_logs) and the
 * relevant party is notified.
 */
class ResearchWorkflow
{
    public function __construct(protected NotificationService $notifications)
    {
    }

    public function submit(Research $research): void
    {
        $research->forceFill([
            'status' => 'submitted',
            'submitted_at' => now(),
            'review_note' => null,
        ])->save();

        $this->log($research, null, 'submitted');
        ActivityLog::log('research_submitted', 'Research', $research->id, ['title' => $research->title]);
        $this->notifications->notifyAdminsResearchSubmitted($research);
    }

    public function startReview(Research $research, User $reviewer): void
    {
        if ($research->status === 'submitted') {
            $research->forceFill(['status' => 'under_review', 'reviewed_by' => $reviewer->id])->save();
        }
    }

    public function requestChanges(Research $research, User $reviewer, string $comment): void
    {
        $research->forceFill([
            'status' => 'changes_requested',
            'reviewed_by' => $reviewer->id,
            'review_note' => $comment,
        ])->save();

        $this->log($research, $reviewer, 'changes_requested', $comment);
        ActivityLog::log('research_changes_requested', 'Research', $research->id, ['title' => $research->title]);
        $this->notifications->notifyAuthorResearchReviewed($research, 'changes_requested', $comment);
    }

    public function reject(Research $research, User $reviewer, ?string $comment = null): void
    {
        $research->forceFill([
            'status' => 'archived',
            'reviewed_by' => $reviewer->id,
            'review_note' => $comment,
        ])->save();

        $this->log($research, $reviewer, 'rejected', $comment);
        ActivityLog::log('research_rejected', 'Research', $research->id, ['title' => $research->title]);
        $this->notifications->notifyAuthorResearchReviewed($research, 'rejected', $comment);
    }

    public function approveAndPublish(Research $research, User $reviewer, ?string $comment = null): void
    {
        $research->forceFill([
            'status' => 'published',
            'reviewed_by' => $reviewer->id,
            'review_note' => $comment,
            'published_at' => $research->published_at ?? now(),
        ])->save();

        $this->log($research, $reviewer, 'published', $comment);
        ActivityLog::log('research_published', 'Research', $research->id, ['title' => $research->title]);
        $this->notifications->notifyAuthorResearchReviewed($research, 'published', $comment);
    }

    public function unpublish(Research $research, User $reviewer): void
    {
        $research->forceFill(['status' => 'approved'])->save();
        $this->log($research, $reviewer, 'unpublished');
        ActivityLog::log('research_unpublished', 'Research', $research->id, ['title' => $research->title]);
    }

    private function log(Research $research, ?User $reviewer, string $action, ?string $comment = null): void
    {
        ResearchReview::create([
            'research_id' => $research->id,
            'reviewer_id' => $reviewer?->id,
            'action' => $action,
            'comment' => $comment,
        ]);
    }
}
