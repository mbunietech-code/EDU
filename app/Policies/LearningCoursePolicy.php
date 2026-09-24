<?php

namespace App\Policies;

use App\Models\LearningCourse;
use App\Models\User;

/**
 * Who may do what with a course. State rules (e.g. only enrol while the
 * course is published) are re-checked in the controllers/services, because
 * Gate::before lets super admins through every ability here.
 */
class LearningCoursePolicy
{
    public function view(User $user, LearningCourse $course): bool
    {
        return $course->isVisibleTo($user);
    }

    public function enroll(User $user, LearningCourse $course): bool
    {
        return $course->isPublished() && $course->isOpen();
    }

    public function manage(User $user): bool
    {
        return $user->hasPermission('learning.manage');
    }
}
