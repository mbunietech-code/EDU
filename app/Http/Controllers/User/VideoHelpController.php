<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\VideoHelpRequest;
use App\Services\VideoHelpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The member's "Video help" tab: ask for a video call with support, watch it
 * ring (polling), get sent into the support live room once an admin answers.
 */
class VideoHelpController extends Controller
{
    public function __construct(protected VideoHelpService $help)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        abort_if($user->isGuest(), 403);

        $active = $this->help->activeFor($user);

        $recent = VideoHelpRequest::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->limit(5)
            ->get();

        return view('user.video-help.index', [
            'active' => $active,
            'activeStatus' => $active ? $this->payload($active) : null,
            'recent' => $recent,
            'ringSeconds' => $this->help->ringSeconds(),
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        abort_if($user->isGuest(), 403);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $call = $this->help->request($user, $data['reason'] ?? null);

        if ($request->expectsJson()) {
            return response()->json($this->payload($call), 201);
        }

        return redirect()->route('user.video-help.index');
    }

    public function status(Request $request, VideoHelpRequest $videoHelpRequest): JsonResponse
    {
        abort_unless((int) $videoHelpRequest->user_id === (int) $request->user()->id, 404);

        // Polling also turns calls nobody answered in time into "missed".
        $this->help->expireOverdue();

        return response()->json($this->payload($videoHelpRequest));
    }

    public function cancel(Request $request, VideoHelpRequest $videoHelpRequest)
    {
        abort_unless((int) $videoHelpRequest->user_id === (int) $request->user()->id, 404);

        $this->help->cancel($videoHelpRequest);

        if ($request->expectsJson()) {
            return response()->json($this->payload($videoHelpRequest));
        }

        return redirect()->route('user.video-help.index');
    }

    private function payload(VideoHelpRequest $call): array
    {
        return $this->help->statusPayload($call) + [
            'status_url' => route('user.video-help.status', $call),
            'cancel_url' => route('user.video-help.cancel', $call),
        ];
    }
}
