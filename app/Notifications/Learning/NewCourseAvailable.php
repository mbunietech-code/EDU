<?php

namespace App\Notifications\Learning;

use App\Models\LearningCourse;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class NewCourseAvailable extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public LearningCourse $course)
    {
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'learning.new_course',
            'course_id' => $this->course->id,
            'title' => 'New course',
            'message' => 'A new course is available: '.$this->course->title.'.',
            'url' => route('learn.courses.show', $this->course),
        ];
    }
}
