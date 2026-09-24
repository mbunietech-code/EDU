<?php

namespace App\Http\Controllers\Learn;

use App\Http\Controllers\Controller;
use App\Models\LearningVideo;
use App\Models\LearningVideoComment;
use App\Models\User;
use App\Services\Learning\LearningDeletionService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Lesson discussion comments (one reply level).
 */
class VideoCommentController extends Controller
{
    public function store(Request $request, LearningVideo $video)
    {
        $this->authorize('view', $video);

        $data = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:2000'],
            'parent_id' => [
                'nullable', 'integer',
                // Replies attach to a live top-level comment on this same lesson.
                Rule::exists('learning_video_comments', 'id')
                    ->where('learning_video_id', $video->id)
                    ->whereNull('parent_id')
                    ->whereNull('deleted_at'),
            ],
        ], [
            'parent_id.exists' => 'That comment is no longer available to reply to.',
        ]);

        $comment = LearningVideoComment::create([
            'learning_video_id' => $video->id,
            'user_id' => $request->user()->id,
            'parent_id' => $data['parent_id'] ?? null,
            'body' => trim($data['body']),
        ]);

        return redirect()
            ->to(route('learn.videos.show', $video).'#comment-'.($comment->parent_id ?? $comment->id))
            ->with('success', $comment->parent_id ? 'Reply posted.' : 'Comment posted.');
    }

    public function destroy(Request $request, LearningVideo $video, LearningVideoComment $comment, LearningDeletionService $deletions)
    {
        abort_unless((int) $comment->learning_video_id === (int) $video->id, 404);
        abort_unless($this->canDelete($request->user(), $video, $comment), 403);

        $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $deletions->deleteComment($comment);

        return redirect()
            ->to(route('learn.videos.show', $video).'#comments')
            ->with('success', 'Comment deleted.');
    }

    /** The author, content managers, or the lesson's own instructor. */
    public static function canDelete(User $user, LearningVideo $video, LearningVideoComment $comment): bool
    {
        return (int) $comment->user_id === (int) $user->id
            || $user->hasPermission('learning.manage')
            || $video->isOwnedBy($user);
    }
}
