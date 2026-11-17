<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CallDevice;
use App\Models\VideoHelpRequest;
use App\Services\Ai\AiWatchdog;
use App\Services\VideoHelpService;
use App\Services\WebPushService;
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

        $devices = rescue(fn () => CallDevice::query()
            ->with('user:id,name')
            ->orderByRaw('user_id = ? desc', [$request->user()->id])
            ->latest('id')
            ->get(), collect(), false); // empty until database/alters/0043 is applied

        return view('admin.video-help.index', [
            'calls' => $calls,
            'room' => $this->help->room(),
            'devices' => $devices,
            'vapidKey' => app(WebPushService::class)->publicKey(),
        ]);
    }

    /**
     * What rings for me right now (the popup on every admin page polls this):
     * video help calls (support admins only) and AI Assistant alerts about
     * problems I can handle (AiWatchdog).
     */
    public function ringing(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && $user->is_admin, 403);

        $calls = collect();
        if (VideoHelpService::canAnswer($user)) {
            try {
                $calls = $this->help->ringingFor($user)->map(fn (VideoHelpRequest $c) => [
                    'id' => $c->id,
                    'name' => $c->user?->name ?? 'Member',
                    'email' => $c->user?->email,
                    'reason' => $c->reason,
                    'since' => $c->created_at?->toIso8601String(),
                    'seconds_left' => max(0, (int) now()->diffInSeconds($c->ring_until, false)),
                    'accept_url' => route('admin.video-help.accept', $c),
                    'decline_url' => route('admin.video-help.decline', $c),
                ]);
            } catch (QueryException) {
                // Stay quiet until the table exists (database/alters/0041).
            }
        }

        $alerts = rescue(fn () => app(AiWatchdog::class)->ringingFor($user)->map(fn ($n) => [
            'id' => $n->id,
            'title' => (string) ($n->data['title'] ?? 'AI Assistant'),
            'message' => (string) ($n->data['message'] ?? ''),
            'severity' => (string) ($n->data['severity'] ?? 'medium'),
            'url' => $n->data['url'] ?? null,
            'ask_url' => $user->can('ai.access') && ! empty($n->data['ask'])
                ? route('admin.ai-assistant.index', ['ask' => $n->data['ask']]) : null,
            'since' => $n->created_at?->toIso8601String(),
            'dismiss_url' => route('admin.video-help.alerts.dismiss', $n->id),
        ])->values(), collect(), false);

        return response()->json(['calls' => $calls->values(), 'alerts' => $alerts]);
    }

    /** Open / Dismiss on an AI alert: it stops ringing (the notification is read). */
    public function dismissAlert(Request $request, string $notification): JsonResponse
    {
        $request->user()->notifications()->whereKey($notification)->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
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
