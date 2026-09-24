<?php

namespace App\Http\Controllers\Admin\Learning;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\LearningCourse;
use App\Models\LearningTopic;
use App\Services\Learning\LearningDeletionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Course topics (admin).
 */
class TopicController extends Controller
{
    public function index(Request $request, LearningDeletionService $deletions): View
    {
        Gate::authorize('learning.view');

        $courseId = $request->integer('course') ?: null;

        $topics = LearningTopic::query()
            ->whereHas('course')
            ->with('course:id,title')
            ->withCount('videos')
            ->when($courseId, fn (Builder $q, int $id) => $q->where('learning_course_id', $id))
            ->orderBy('learning_course_id')
            ->orderBy('position')
            ->orderBy('id')
            ->paginate(30)
            ->withQueryString();

        $canManage = Gate::allows('learning.manage');
        $impact = [];
        if ($canManage) {
            foreach ($topics as $topic) {
                $impact[$topic->id] = $deletions->impact($topic);
            }
        }

        return view('admin.learning.topics.index', [
            'topics' => $topics,
            'courseId' => $courseId,
            'courses' => LearningCourse::query()->orderBy('title')->get(['id', 'title']),
            'canManage' => $canManage,
            'impact' => $impact,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('learning.manage');

        $data = $request->validate([
            'learning_course_id' => ['required', 'integer', Rule::exists('learning_courses', 'id')->whereNull('deleted_at')],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'position' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        $topic = LearningTopic::create([
            'learning_course_id' => (int) $data['learning_course_id'],
            'title' => trim($data['title']),
            'description' => filled($data['description'] ?? null) ? trim($data['description']) : null,
            'position' => isset($data['position'])
                ? (int) $data['position']
                : (int) LearningTopic::query()->where('learning_course_id', $data['learning_course_id'])->max('position') + 1,
        ]);

        ActivityLog::log('learning_topic_created', 'LearningTopic', $topic->id, [
            'title' => $topic->title,
            'course_id' => $topic->learning_course_id,
        ]);

        return back()->with('success', 'Topic “'.$topic->title.'” added.');
    }

    public function update(Request $request, LearningTopic $topic): RedirectResponse
    {
        Gate::authorize('learning.manage');

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'position' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        $topic->update([
            'title' => trim($data['title']),
            'description' => filled($data['description'] ?? null) ? trim($data['description']) : null,
            'position' => isset($data['position']) ? (int) $data['position'] : (int) $topic->position,
        ]);

        ActivityLog::log('learning_topic_updated', 'LearningTopic', $topic->id, [
            'title' => $topic->title,
            'course_id' => $topic->learning_course_id,
            'changes' => array_values(array_diff(array_keys($topic->getChanges()), ['updated_at'])),
        ]);

        return back()->with('success', 'Topic “'.$topic->title.'” updated.');
    }

    public function destroy(Request $request, LearningTopic $topic, LearningDeletionService $deletions): RedirectResponse
    {
        Gate::authorize('learning.manage');

        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']]);

        $deletions->deleteTopic($topic, $data['reason']);

        return back()->with('success', 'Topic “'.$topic->title.'” deleted. Its lessons stay in the course without a topic.');
    }
}
