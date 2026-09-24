<?php

namespace App\Http\Controllers\Admin\Learning;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\LearningCourse;
use App\Models\LearningEnrollment;
use App\Models\User;
use App\Services\Learning\LearningAnalytics;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Admin-managed course enrolments.
 */
class EnrollmentController extends Controller
{
    public function store(Request $request, LearningCourse $course, LearningAnalytics $analytics): RedirectResponse
    {
        Gate::authorize('learning.manage');

        $data = $request->validate([
            'user_ids' => ['required', 'array', 'min:1', 'max:200'],
            'user_ids.*' => ['integer', 'distinct'],
        ], [
            'user_ids.required' => 'Choose at least one member to enrol.',
        ]);

        $users = User::query()
            ->whereIn('id', array_map('intval', $data['user_ids']))
            ->where('status', 'active')
            ->get(['id', 'name']);

        if ($users->isEmpty()) {
            return back()->with('error', 'None of the chosen members can be enrolled (they must be active members).');
        }

        $actor = $request->user();
        $created = [];

        DB::transaction(function () use ($users, $course, $actor, &$created) {
            foreach ($users as $user) {
                $enrollment = LearningEnrollment::firstOrCreate(
                    ['user_id' => $user->id, 'learning_course_id' => $course->id],
                    ['source' => 'admin', 'enrolled_by' => $actor->id, 'enrolled_at' => now()],
                );

                if ($enrollment->wasRecentlyCreated) {
                    $created[] = $user->id;
                }
            }

            ActivityLog::log('learning_enrollments_added', 'LearningCourse', $course->id, [
                'title' => $course->title,
                'user_ids' => $created,
            ]);
        });

        $analytics->forget();

        $already = $users->count() - count($created);
        $skipped = count($data['user_ids']) - $users->count();
        $message = count($created).' '.Str::plural('learner', count($created)).' enrolled in “'.$course->title.'”.';
        if ($already > 0) {
            $message .= ' '.$already.' already enrolled.';
        }
        if ($skipped > 0) {
            $message .= ' '.$skipped.' skipped (inactive or not found).';
        }

        return redirect()->route('admin.learning.courses.show', [$course, 'tab' => 'enrolments'])->with('success', $message);
    }

    public function destroy(Request $request, LearningCourse $course, LearningEnrollment $enrollment, LearningAnalytics $analytics): RedirectResponse
    {
        Gate::authorize('learning.manage');

        abort_unless((int) $enrollment->learning_course_id === (int) $course->id, 404);

        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']]);

        $name = $enrollment->user?->name ?? 'The learner';

        DB::transaction(function () use ($enrollment, $course, $data) {
            $enrollment->delete();

            ActivityLog::log('learning_enrollment_removed', 'LearningEnrollment', $enrollment->id, [
                'course_id' => $course->id,
                'user_id' => $enrollment->user_id,
                'source' => $enrollment->source,
                'reason' => $data['reason'],
            ]);
        });

        $analytics->forget();

        return redirect()->route('admin.learning.courses.show', [$course, 'tab' => 'enrolments'])
            ->with('success', $name.' was removed from “'.$course->title.'”. Their lesson progress is kept.');
    }
}
