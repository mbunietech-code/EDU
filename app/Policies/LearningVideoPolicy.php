<?php

namespace App\Policies;

use App\Models\LearningVideo;
use App\Models\User;

/**
 * Who may do what with a video lesson. State rules (a lesson needs a file
 * before it can be published, …) are enforced in VideoService, because
 * Gate::before lets super admins through every ability here.
 */
class LearningVideoPolicy
{
    public function view(User $user, LearningVideo $video): bool
    {
        return $video->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        return $user->canUploadLessons();
    }

    public function update(User $user, LearningVideo $video): bool
    {
        return $this->owns($user, $video);
    }

    public function delete(User $user, LearningVideo $video): bool
    {
        return $this->owns($user, $video);
    }

    public function publish(User $user, LearningVideo $video): bool
    {
        return $this->owns($user, $video);
    }

    /** Content managers, or the lesson's own instructor while they still teach. */
    private function owns(User $user, LearningVideo $video): bool
    {
        return $user->hasPermission('learning.manage')
            || ($video->isOwnedBy($user) && $user->isInstructor());
    }
}
