<?php

namespace App\Http\Controllers\Learn;

use App\Http\Controllers\Controller;
use App\Models\LearningVideo;
use App\Services\Learning\ProgressService;
use Illuminate\Http\Request;

/**
 * Player progress beacons and manual completion.
 */
class VideoProgressController extends Controller
{
    public function __construct(protected ProgressService $progress)
    {
    }

    /**
     * Beacon from learnVideoPlayer (fetch, or navigator.sendBeacon on
     * pagehide — which cannot read the answer but still carries _token).
     */
    public function store(Request $request, LearningVideo $video)
    {
        $this->authorize('view', $video);

        $data = $request->validate([
            'position' => ['required', 'integer', 'min:0', 'max:86400'],
            'duration' => ['nullable', 'integer', 'min:1', 'max:86400'],
            'event' => ['required', 'string', 'in:'.implode(',', ProgressService::EVENTS)],
            'watched' => ['nullable', 'integer', 'min:0', 'max:600'],
        ]);

        $row = $this->progress->record(
            $request->user(),
            $video,
            (int) $data['position'],
            isset($data['duration']) ? (int) $data['duration'] : null,
            $data['event'],
            (int) ($data['watched'] ?? 0),
        );

        return response()->json([
            'percent' => (int) $row->percent,
            'completed' => $row->isCompleted(),
            'position' => (int) $row->position_seconds,
        ]);
    }

    public function complete(Request $request, LearningVideo $video)
    {
        $this->authorize('view', $video);

        $data = $request->validate([
            'completed' => ['required', 'boolean'],
        ]);

        $completed = filter_var($data['completed'], FILTER_VALIDATE_BOOLEAN);
        $row = $this->progress->setCompleted($request->user(), $video, $completed);

        if ($request->expectsJson()) {
            return response()->json([
                'percent' => (int) $row->percent,
                'completed' => $row->isCompleted(),
                'position' => (int) $row->position_seconds,
            ]);
        }

        return back()->with('success', $completed ? 'Lesson marked as completed.' : 'Lesson marked as not completed.');
    }
}
