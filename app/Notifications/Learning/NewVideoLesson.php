<?php

namespace App\Notifications\Learning;

use App\Models\LearningVideo;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class NewVideoLesson extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public LearningVideo $video)
    {
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        $course = $this->video->course;

        return [
            'type' => 'learning.new_video',
            'video_id' => $this->video->id,
            'course_id' => $course?->id,
            'title' => 'New lesson',
            'message' => '"'.$this->video->title.'" is now available'.($course ? ' in '.$course->title : '').'.',
            'url' => route('learn.videos.show', $this->video),
        ];
    }
}
