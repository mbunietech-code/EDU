<?php

namespace App\Http\Controllers\Admin\Learning;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\Learning\LearningNotifier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Grant / revoke the instructor flag (users.can_teach).
 */
class InstructorController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('learning.view');

        $search = Str::limit(trim((string) $request->query('q', '')), 100, '');

        $instructors = User::query()
            ->where('can_teach', true)
            ->withCount(['hostedRooms', 'teachingVideos', 'taughtCourses'])
            ->when($search !== '', fn (Builder $q) => $q->where(function (Builder $q) use ($search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $q->where('name', 'like', $like)->orWhere('email', 'like', $like);
            }))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.learning.instructors.index', [
            'instructors' => $instructors,
            'search' => $search,
            'canManage' => Gate::allows('learning.manage'),
        ]);
    }

    public function store(Request $request, LearningNotifier $notifier): RedirectResponse
    {
        Gate::authorize('learning.manage');

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ], [
            'user_id.required' => 'Choose the member who should get instructor access.',
        ]);

        $user = User::query()->findOrFail((int) $data['user_id']);

        if ($user->isInstructor()) {
            return back()->with('success', $user->name.' already has instructor access.');
        }

        if (! $user->isActive()) {
            return back()->with('error', $user->name.' is not an active member. Activate the account first.');
        }

        $user->forceFill(['can_teach' => true])->save();

        ActivityLog::log('learning_instructor_granted', 'User', $user->id, ['email' => $user->email]);

        $notifier->notifyInstructorGranted($user, $request->user());

        return back()->with('success', $user->name.' can now host live rooms and upload lessons.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('learning.manage');

        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']]);

        if (! $user->isInstructor()) {
            return back()->with('success', $user->name.' does not have instructor access.');
        }

        $user->forceFill(['can_teach' => false])->save();

        ActivityLog::log('learning_instructor_revoked', 'User', $user->id, [
            'email' => $user->email,
            'reason' => $data['reason'],
            'hosted_rooms' => $user->hostedRooms()->count(),
            'lessons' => $user->teachingVideos()->count(),
            'courses' => $user->taughtCourses()->count(),
        ]);

        return back()->with('success', 'Instructor access removed from '.$user->name.'. Their rooms, lessons and courses stay in place.');
    }
}
