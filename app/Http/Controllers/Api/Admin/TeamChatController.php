<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminGroup;
use App\Models\AdminGroupMessage;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Team chat (private 1:1 chats and groups between admins) for the app.
 * Same rules as Admin\TeamChatController / AdminGroupChatController: only
 * members read a chat, only super admins create or change groups.
 */
class TeamChatController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $chats = AdminGroup::with(['members', 'latestMessage.sender'])
                ->whereHas('members', fn ($q) => $q->whereKey($user->id))
                ->get()
                ->sortByDesc(fn ($g) => $g->latestMessage?->created_at ?? $g->created_at)
                ->values();
        } catch (\Throwable) {
            $chats = collect();
        }

        $talkingTo = $chats->filter->isDirect()->flatMap->directUserIds()->all();
        $ready = $this->privateChatsReady();

        return response()->json(['data' => [
            'chats' => $chats->map(fn (AdminGroup $g) => [
                'id' => $g->id,
                'name' => $this->chatName($g, $user),
                'is_direct' => $g->isDirect(),
                'members' => $g->members->count(),
                'unread' => $g->unreadFor($user),
                'last_message' => $g->latestMessage
                    ? ($g->latestMessage->is_deleted ? 'Message deleted' : ($g->latestMessage->body ?: ucfirst($g->latestMessage->type)))
                    : null,
                'last_at' => ($g->latestMessage?->created_at ?? $g->created_at)?->diffForHumans(),
            ])->values(),
            'others' => $ready
                ? User::where('is_admin', true)->where('id', '!=', $user->id)->whereNotIn('id', $talkingTo)->orderBy('name')->get(['id', 'name'])
                : [],
            'admins' => $user->isSuperAdmin() ? User::where('is_admin', true)->orderBy('name')->get(['id', 'name']) : [],
            'can_manage_groups' => $user->isSuperAdmin(),
        ]]);
    }

    public function start(Request $request, User $admin): JsonResponse
    {
        $user = $request->user();
        abort_unless($admin->is_admin && $admin->id !== $user->id, 404);
        abort_unless($this->privateChatsReady(), 503, 'Private chats are not enabled on the server yet.');

        return response()->json(['data' => ['id' => AdminGroup::directBetween($user, $admin)->id]]);
    }

    public function storeGroup(Request $request): JsonResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        $data = $this->validateGroup($request);
        $group = AdminGroup::create(['name' => $data['name'], 'created_by' => $request->user()->id]);
        $group->members()->sync($this->memberIds($data['members'], $request->user(), true));

        return response()->json(['data' => ['id' => $group->id], 'message' => 'Group created.'], 201);
    }

    public function updateGroup(Request $request, AdminGroup $group): JsonResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
        abort_if($group->isDirect(), 404);

        $data = $this->validateGroup($request);
        $group->update(['name' => $data['name']]);
        $group->members()->sync($this->memberIds($data['members'], $request->user(), false));

        return response()->json(['message' => 'Group updated.']);
    }

    public function destroyGroup(Request $request, AdminGroup $group): JsonResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
        abort_if($group->isDirect(), 404);

        $paths = $group->messages()->whereNotNull('file_path')->pluck('file_path');
        $group->delete();
        Storage::disk('private')->delete($paths->all());

        return response()->json(['message' => 'Group deleted.']);
    }

    /** Messages after ?after=<id> (or the latest 100). Marks the chat read. */
    public function messages(Request $request, AdminGroup $group): JsonResponse
    {
        $this->member($request, $group);
        $after = max(0, (int) $request->query('after', 0));

        $messages = $after > 0
            ? $group->messages()->with('sender')->where('id', '>', $after)->orderBy('id')->get()
            : $group->messages()->with('sender')->orderByDesc('id')->limit(100)->get()->reverse()->values();

        $group->markReadFor($request->user());

        return response()->json(['data' => [
            'name' => $this->chatName($group, $request->user()),
            'is_direct' => $group->isDirect(),
            'member_ids' => $group->members()->pluck('users.id'),
            'messages' => $messages->map(fn (AdminGroupMessage $m) => $this->payload($group, $m))->values(),
        ]]);
    }

    public function send(Request $request, AdminGroup $group): JsonResponse
    {
        $this->member($request, $group);
        $type = $request->input('type', 'text');

        $rules = ['type' => ['required', 'string', 'in:text,image,video,audio']];
        if ($type === 'text') {
            $rules['body'] = ['required', 'string', 'max:4000'];
        } else {
            $rules['body'] = ['nullable', 'string', 'max:4000'];
            $rules['file'] = match ($type) {
                'image' => ['required', 'file', 'mimes:jpg,jpeg,png,gif,webp', 'max:12288'],
                'video' => ['required', 'file', 'mimes:mp4,webm,mov,avi,mkv', 'max:61440'],
                'audio' => ['required', 'file', 'mimes:mp3,wav,ogg,webm,m4a,aac,amr', 'max:15360'],
            };
        }
        $validated = $request->validate($rules);

        $message = $group->messages()->create([
            'sender_id' => $request->user()->id,
            'type' => $type,
            'body' => $validated['body'] ?? null,
        ]);

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $message->forceFill([
                'file_path' => $file->store('admin-group-chat', 'private'),
                'file_name' => $file->getClientOriginalName(),
            ])->save();
        }

        $group->touch();
        $group->markReadFor($request->user());

        return response()->json(['data' => $this->payload($group, $message->load('sender'))], 201);
    }

    public function updateMessage(Request $request, AdminGroup $group, AdminGroupMessage $message): JsonResponse
    {
        $this->own($request, $group, $message);
        abort_if($message->is_deleted, 422, 'This message was deleted.');
        abort_unless($message->type === 'text', 422, 'Only text messages can be edited.');
        abort_if($message->created_at->lt(now()->subMinutes(30)), 422, 'This message can no longer be edited (past 30 minutes).');

        $data = $request->validate(['body' => ['required', 'string', 'max:4000']]);
        $message->update(['body' => $data['body'], 'edited_at' => now()]);

        return response()->json(['data' => $this->payload($group, $message->load('sender'))]);
    }

    public function destroyMessage(Request $request, AdminGroup $group, AdminGroupMessage $message): JsonResponse
    {
        $this->own($request, $group, $message);

        if ($message->file_path) {
            Storage::disk('private')->delete($message->file_path);
        }
        $message->update(['body' => null, 'file_path' => null, 'file_name' => null, 'is_deleted' => true]);

        return response()->json(['data' => $this->payload($group, $message->load('sender'))]);
    }

    /** The attachment file itself (the app fetches it with its token). */
    public function attachment(Request $request, AdminGroup $group, AdminGroupMessage $message)
    {
        $this->member($request, $group);
        abort_unless((int) $message->admin_group_id === (int) $group->id, 404);
        abort_if(! $message->file_path, 404);
        abort_unless(Storage::disk('private')->exists($message->file_path), 404, 'This attachment is no longer available on the server.');

        return Storage::disk('private')->download($message->file_path, $message->file_name ?: basename($message->file_path));
    }

    protected function payload(AdminGroup $group, AdminGroupMessage $m): array
    {
        $mine = (int) $m->sender_id === (int) auth()->id();

        return [
            'id' => $m->id,
            'mine' => $mine,
            'sender' => $mine ? null : ($m->sender?->name ?? 'Unknown'),
            'type' => $m->is_deleted ? 'text' : $m->type,
            'body' => $m->is_deleted ? 'This message was deleted' : $m->body,
            'has_file' => ! $m->is_deleted && (bool) $m->file_path,
            'file_name' => $m->file_name,
            'time' => $m->created_at->format('H:i'),
            'date' => $m->created_at->toDateString(),
            'deleted' => (bool) $m->is_deleted,
            'edited' => (bool) $m->edited_at,
            'editable' => $mine && ! $m->is_deleted && $m->type === 'text' && $m->created_at->gte(now()->subMinutes(30)),
        ];
    }

    protected function chatName(AdminGroup $group, User $viewer): string
    {
        if ($group->isDirect()) {
            $other = $group->members->firstWhere('id', '!=', $viewer->id);

            return $other?->name ?? 'Private chat';
        }

        return (string) $group->name;
    }

    protected function member(Request $request, AdminGroup $group): void
    {
        abort_unless($group->hasMember($request->user()), 403);
    }

    protected function own(Request $request, AdminGroup $group, AdminGroupMessage $message): void
    {
        $this->member($request, $group);
        abort_unless((int) $message->admin_group_id === (int) $group->id, 403);
        abort_unless((int) $message->sender_id === (int) $request->user()->id, 403);
    }

    protected function validateGroup(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'members' => ['required', 'array', 'min:1'],
            'members.*' => ['integer', 'exists:users,id'],
        ]);
    }

    /** Only admins; the creator stays in a new group. */
    protected function memberIds(array $ids, User $actor, bool $creating): array
    {
        $ids = User::whereIn('id', $ids)->where('is_admin', true)->pluck('id')->all();
        if ($creating) {
            $ids[] = $actor->id;
        }

        return array_values(array_unique($ids));
    }

    protected function privateChatsReady(): bool
    {
        try {
            return Schema::hasColumn('admin_groups', 'direct_key');
        } catch (\Throwable) {
            return false;
        }
    }
}
