<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VideoHelpRequest;
use App\Services\VideoHelpService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Admin side of video help: the call log, the "incoming call" poll used by
 * the popup on every admin page, and Accept / Decline.
 */
class VideoHelpController extends Controller
{
    public function __construct(protected VideoHelpService $help)
    {
    }

    public function index(Request $request)
    {
        abort_unless(VideoHelpService::canAnswer($request->user()), 403);

        $this->help->expireOverdue();

        $calls = VideoHelpRequest::query()
            ->with(['user:id,name,email', 'acceptedBy:id,name'])
            ->latest('id')
            ->paginate(30);

        return view('admin.video-help.index', [
            'calls' => $calls,
            'room' => $this->help->room(),
        ]);
    }

    /** Calls ringing for me right now (the popup polls this). */
    public function ringing(Request $request): JsonResponse
    {
        abort_unless(VideoHelpService::canAnswer($request->user()), 403);

        try {
            $ringing = $this->help->ringingFor($request->user());
        } catch (QueryException) {
            // Every admin page polls this: stay quiet until the table exists (database/alters/0041).
            return response()->json(['calls' => []]);
        }

        $calls = $ringing->map(fn (VideoHelpRequest $c) => [
            'id' => $c->id,
            'name' => $c->user?->name ?? 'Member',
            'email' => $c->user?->email,
            'reason' => $c->reason,
            'since' => $c->created_at?->toIso8601String(),
            'seconds_left' => max(0, (int) now()->diffInSeconds($c->ring_until, false)),
            'accept_url' => route('admin.video-help.accept', $c),
            'decline_url' => route('admin.video-help.decline', $c),
        ]);

        return response()->json(['calls' => $calls]);
    }

    public function accept(Request $request, VideoHelpRequest $videoHelpRequest): JsonResponse
    {
        abort_unless(VideoHelpService::canAnswer($request->user()), 403);

        try {
            $won = $this->help->accept($videoHelpRequest, $request->user());
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'message' => collect($e->errors())->flatten()->first()], 409);
        }

        if (! $won) {
            $videoHelpRequest->refresh();
            $message = match ($videoHelpRequest->status) {
                VideoHelpRequest::ACCEPTED => ($videoHelpRequest->acceptedBy?->name ?? 'Another admin').' already answered this call.',
                VideoHelpRequest::CANCELLED => 'The member hung up.',
                default => 'This call is no longer ringing.',
            };

            return response()->json(['ok' => false, 'message' => $message], 409);
        }

        $room = $this->help->room();

        return response()->json([
            'ok' => true,
            // live=1: straight into the call with camera and microphone on, no second Join click.
            'url' => route('learn.rooms.live', ['room' => $room, 'live' => 1]),
        ]);
    }

    public function decline(Request $request, VideoHelpRequest $videoHelpRequest): JsonResponse
    {
        abort_unless(VideoHelpService::canAnswer($request->user()), 403);

        $this->help->decline($videoHelpRequest, $request->user());

        return response()->json(['ok' => true]);
    }
}
