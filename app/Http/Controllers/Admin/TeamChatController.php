<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminConversation;
use App\Models\AdminMessage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Internal messaging between the admin team — separate from the customer
 * support chat (AdminChatController). One thread per non-super-admin user;
 * every super admin can see and reply in any thread, appearing to the line
 * admin as a single "Super Admin" counterpart.
 */
class TeamChatController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if (! $user->isSuperAdmin()) {
            return redirect()->route('admin.team-chat.show', $this->conversationFor($user));
        }

        $conversations = AdminConversation::with(['admin', 'latestMessage'])
            ->whereHas('admin', fn ($q) => $q->where('is_admin', true))
            ->get()
            ->map(function (AdminConversation $c) use ($user) {
                $c->unread = $c->messages()
                    ->where('sender_id', '!=', $user->id)
                    ->where('is_read', false)
                    ->count();

                return $c;
            })
            ->sortByDesc(fn ($c) => $c->latestMessage?->created_at ?? $c->created_at)
            ->values();

        // Every admin/super-admin should have a thread available to pick from,
        // even before they've sent their first message.
        $missing = User::where('is_admin', true)
            ->where('id', '!=', $user->id)
            ->whereDoesntHave('adminConversation')
            ->get();

        return view('admin.team-chat.index', compact('conversations', 'missing'));
    }

    public function start(Request $request, User $admin)
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
        abort_unless($admin->is_admin, 404);

        $conversation = $this->conversationFor($admin);

        return redirect()->route('admin.team-chat.show', $conversation);
    }

    public function show(Request $request, AdminConversation $conversation)
    {
        $this->authorizeAccess($request, $conversation);

        $conversation->load('admin');

        $messages = $conversation->messages()->orderByDesc('id')->limit(100)->get()->reverse();

        $viewerIsTheAdmin = $conversation->admin_id === $request->user()->id;

        $payload = $messages->map(fn (AdminMessage $m) => $this->payload($conversation, $m))->values();

        $this->markRead($conversation, $request->user());

        return view('admin.team-chat.show', compact('conversation', 'messages', 'payload', 'viewerIsTheAdmin'));
    }

    public function fetch(Request $request, AdminConversation $conversation)
    {
        $this->authorizeAccess($request, $conversation);

        $after = max(0, (int) $request->input('after', 0));

        $messages = $conversation->messages()
            ->where('id', '>', $after)
            ->orderBy('id')
            ->get();

        $this->markRead($conversation, $request->user());

        return response()->json([
            'messages' => $messages->map(fn (AdminMessage $m) => $this->payload($conversation, $m)),
        ]);
    }

    public function store(Request $request, AdminConversation $conversation)
    {
        $this->authorizeAccess($request, $conversation);

        $type = $request->input('type', 'text');

        $rules = [
            'type' => ['required', 'string', 'in:text,image,video,audio'],
            'body' => ['nullable', 'string', 'max:4000'],
        ];

        if ($type === 'text') {
            $rules['file'] = ['nullable', 'prohibited'];
        } else {
            $rules['file'] = match ($type) {
                'image' => ['required', 'file', 'mimes:jpg,jpeg,png,gif,webp', 'max:12288'],
                'video' => ['required', 'file', 'mimes:mp4,webm,mov,avi,mkv', 'max:61440'],
                'audio' => ['required', 'file', 'mimes:mp3,wav,ogg,webm,m4a,aac,amr', 'max:15360'],
                default => ['nullable'],
            };
        }

        $validated = $request->validate($rules);

        $message = $conversation->messages()->create([
            'sender_id' => $request->user()->id,
            'type' => $type,
            'body' => $validated['body'] ?? null,
        ]);

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $message->forceFill([
                'file_path' => $file->store('admin-chat', 'private'),
                'file_name' => $file->getClientOriginalName(),
            ])->save();
        }

        $conversation->touch();

        if ($request->expectsJson()) {
            return response()->json(['message' => $this->payload($conversation, $message)]);
        }

        return back();
    }

    public function update(Request $request, AdminConversation $conversation, AdminMessage $message)
    {
        $this->authorizeAccess($request, $conversation);
        abort_unless($message->admin_conversation_id === $conversation->id, 403);
        abort_unless($message->sender_id === $request->user()->id, 403);
        abort_if($message->is_deleted, 422, 'This message was deleted.');
        abort_unless($message->type === 'text', 422, 'Only text messages can be edited.');
        abort_if(
            $message->created_at->lt(now()->subMinutes(30)),
            422,
            'This message can no longer be edited (past 30 minutes).',
        );

        $data = $request->validate(['body' => ['required', 'string', 'max:4000']]);
        $message->update(['body' => $data['body'], 'edited_at' => now()]);

        return response()->json(['message' => $this->payload($conversation, $message)]);
    }

    public function destroy(Request $request, AdminConversation $conversation, AdminMessage $message)
    {
        $this->authorizeAccess($request, $conversation);
        abort_unless($message->admin_conversation_id === $conversation->id, 403);
        abort_unless($message->sender_id === $request->user()->id, 403);

        if ($message->file_path) {
            Storage::disk('private')->delete($message->file_path);
        }

        $message->update([
            'body' => null,
            'file_path' => null,
            'file_name' => null,
            'is_deleted' => true,
        ]);

        return response()->json(['message' => $this->payload($conversation, $message)]);
    }

    public function attachment(AdminConversation $conversation, AdminMessage $message)
    {
        $this->authorizeAccess(request(), $conversation);
        abort_unless($message->admin_conversation_id === $conversation->id, 403);
        abort_if(! $message->file_path, 404);
        abort_unless(Storage::disk('private')->exists($message->file_path), 404, 'This attachment is no longer available on the server.');

        return Storage::disk('private')->download($message->file_path, $message->file_name ?: basename($message->file_path));
    }

    protected function conversationFor(User $admin): AdminConversation
    {
        return AdminConversation::firstOrCreate(['admin_id' => $admin->id]);
    }

    protected function authorizeAccess(Request $request, AdminConversation $conversation): void
    {
        $user = $request->user();
        abort_unless(
            $user->isSuperAdmin() || $conversation->admin_id === $user->id,
            403,
        );
    }

    protected function markRead(AdminConversation $conversation, User $viewer): void
    {
        $conversation->messages()
            ->where('sender_id', '!=', $viewer->id)
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);
    }

    protected function payload(AdminConversation $conversation, AdminMessage $message): array
    {
        return [
            'id' => $message->id,
            // "fromAdmin" here means "sent by the line admin" (as opposed to
            // any super admin) — the shared chat.partials.thread component
            // aligns bubbles by comparing this against the viewer's own side.
            'fromAdmin' => $message->sender_id === $conversation->admin_id,
            'type' => $message->is_deleted ? 'text' : $message->type,
            'body' => $message->is_deleted ? 'This message was deleted' : $message->body,
            'file' => (! $message->is_deleted && $message->file_path) ? route('admin.team-chat.attachment', [$conversation, $message]) : null,
            'filename' => $message->file_name,
            'time' => $message->created_at->format('H:i'),
            'deleted' => (bool) $message->is_deleted,
            'edited' => (bool) $message->edited_at,
            'mine' => $message->sender_id === auth()->id(),
            'editable' => $message->sender_id === auth()->id()
                && ! $message->is_deleted
                && $message->type === 'text'
                && $message->created_at->gte(now()->subMinutes(30)),
        ];
    }
}
