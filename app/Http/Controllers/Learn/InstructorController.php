<?php

namespace App\Http\Controllers\Learn;

use App\Http\Controllers\Controller;
use App\Models\LearningCourse;
use App\Models\LearningRoom;
use App\Models\LearningVideo;
use App\Models\User;
use App\Services\Learning\ProgressService;
use Illuminate\Http\Request;

/**
 * Instructor profile with their courses, lessons and rooms.
 *
 * Public-facing within the member area: only the name, "member since" and
 * content the viewer may open are shown — never the email address.
 */
class InstructorController extends Controller
{
    public function show(Request $request, User $instructor, ProgressService $progress)
    {
        $viewer = $request->user();

        $coursesQuery = LearningCourse::query()
            ->where('instructor_id', $instructor->id)
            ->published()
            ->visibleTo($viewer);

        $lessonsQuery = LearningVideo::query()
            ->where('instructor_id', $instructor->id)
            ->published()
            ->visibleTo($viewer);

        $roomsQuery = LearningRoom::query()
            ->where('host_id', $instructor->id)
            ->whereIn('status', ['scheduled', 'live'])
            ->visibleTo($viewer);

        $counts = [
            'courses' => (clone $coursesQuery)->count(),
            'lessons' => (clone $lessonsQuery)->count(),
            'rooms' => (clone $roomsQuery)->count(),
        ];

        abort_unless(
            $instructor->isInstructor() || $counts['courses'] + $counts['lessons'] + $counts['rooms'] > 0,
            404,
        );

        $courses = $coursesQuery
            ->with(['category:id,name,slug', 'instructor:id,name'])
            ->withCount(['videos' => fn ($q) => $q->published()->visibleTo($viewer)])
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(12)
            ->get();

        $courseProgress = $courses->mapWithKeys(fn (LearningCourse $c) => [$c->id => $progress->courseProgress($viewer, $c)]);

        $lessons = $lessonsQuery
            ->with([
                'category:id,name,slug',
                'instructor:id,name',
                'progress' => fn ($q) => $q->where('user_id', $viewer->id),
            ])
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(12, ['*'], 'lessons')
            ->withQueryString();

        $rooms = $roomsQuery
            ->with(['host:id,name', 'category:id,name'])
            ->orderByRaw("case when status = 'live' then 0 else 1 end")
            ->orderBy('scheduled_at')
            ->limit(6)
            ->get();

        return view('learn.instructors.show', [
            'instructor' => $instructor,
            'counts' => $counts,
            'courses' => $courses,
            'courseProgress' => $courseProgress,
            'lessons' => $lessons,
            'rooms' => $rooms,
        ]);
    }
}
