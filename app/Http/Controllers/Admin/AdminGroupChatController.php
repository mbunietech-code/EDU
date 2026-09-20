<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminGroup;
use App\Models\AdminGroupMessage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Group chats inside Team Chat. Any member can read and post; only super
 * admins create groups, rename them and change who is in them.
 */
class AdminGroupChatController extends Controller
{
    public function create(Request $request)
    {
        $this->requireSuperAdmin($request);

        return view('admin.team-chat.group-form', [
            'group' => null,
            'admins' => $this->eligibleAdmins(),
            'selected' => [$request->user()->id],
        ]);
    }

    public function store(Request $request)
    {
        $this->requireSuperAdmin($request);

        $data = $this->validateGroup($request);

        $group = AdminGroup::create(['name' => $data['name'], 'created_by' => $request->user()->id]);
        $group->members()->sync($this->memberIds($data, $request->user()));

        return redirect()->route('admin.team-chat.groups.show', $group)->with('success', 'Group created.');
    }

    public function settings(Request $request, AdminGroup $group)
    {
        $this->requireSuperAdmin($request);

        return view('admin.team-chat.group-form', [
            'group' => $group,
            'admins' => $this->eligibleAdmins(),
            'selected' => $group->members()->pluck('users.id')->all(),
        ]);
    }

    public function update(Request $request, AdminGroup $group)
    {
        $this->requireSuperAdmin($request);

        $data = $this->validateGroup($request);

        $group->update(['name' => $data['name']]);
        $group->members()->sync($this->memberIds($data, $request->user(), $group));

        return redirect()->route('admin.team-chat.groups.show', $group)->with('success', 'Group updated.');
    }

    public function destroy(Request $request, AdminGroup $group)
    {
        $this->requireSuperAdmin($request);

        $paths = $group->messages()->whereNotNull('file_path')->pluck('file_path');
        $group->delete();
        Storage::disk('private')->delete($paths->all());

        return redirect()->route('admin.team-chat.index')->with('success', 'Group deleted.');
    }

    public function show(Request $request, AdminGroup $group)
    {
        $this->authorizeMember($request, $group);

        $group->load('members');

        $messages = $group->messages()->with('sender')->orderByDesc('id')->limit(100)->get()->reverse();
        $payload = $messages->map(fn (AdminGroupMessage $m) => $this->payload($group, $m))->values();

        $group->markReadFor($request->user());

        return view('admin.team-chat.group', compact('group', 'payload'));
    }

    public function fetch(Request $request, AdminGroup $group)
    {
        $this->authorizeMember($request, $group);

        $messages = $group->messages()
            ->with('sender')
            ->where('id', '>', max(0, (int) $request->input('after', 0)))
            ->orderBy('id')
            ->get();

        $group->markReadFor($request->user());

        return response()->json([
            'messages' => $messages->map(fn (AdminGroupMessage $m) => $this->payload($group, $m)),
        ]);
    }

    public function send(Request $request, AdminGroup $group)
    {
        $this->authorizeMember($request, $group);

        $type = $request->input('type', 'text');

        $rules = [
            'type' => ['required', 'string', 'in:text,image,video,audio'],
            'body' => ['nullable', 'string', 'max:4000'],
        ];

        if ($type === 'text') {
            $rules['body'] = ['required', 'string', 'max:4000'];
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

        return response()->json(['message' => $this->payload($group, $message->load('sender'))]);
    }

    public function updateMessage(Request $request, AdminGroup $group, AdminGroupMessage $message)
    {
        $this->authorizeMember($request, $group);
        abort_unless($message->admin_group_id === $group->id, 403);
        abort_unless($message->sender_id === $request->user()->id, 403);
        abort_if($message->is_deleted, 422, 'This message was deleted.');
        abort_unless($message->type === 'text', 422, 'Only text messages can be edited.');
        abort_if($message->created_at->lt(now()->subMinutes(30)), 422, 'This message can no longer be edited (past 30 minutes).');

        $data = $request->validate(['body' => ['required', 'string', 'max:4000']]);
        $message->update(['body' => $data['body'], 'edited_at' => now()]);

        return response()->json(['message' => $this->payload($group, $message->load('sender'))]);
    }

    public function destroyMessage(Request $request, AdminGroup $group, AdminGroupMessage $message)
    {
        $this->authorizeMember($request, $group);
        abort_unless($message->admin_group_id === $group->id, 403);
        abort_unless($message->sender_id === $request->user()->id, 403);

        if ($message->file_path) {
            Storage::disk('private')->delete($message->file_path);
        }

        $message->update(['body' => null, 'file_path' => null, 'file_name' => null, 'is_deleted' => true]);

        return response()->json(['message' => $this->payload($group, $message->load('sender'))]);
    }

    public function attachment(Request $request, AdminGroup $group, AdminGroupMessage $message)
    {
        $this->authorizeMember($request, $group);
        abort_unless($message->admin_group_id === $group->id, 403);
        abort_if(! $message->file_path, 404);
        abort_unless(Storage::disk('private')->exists($message->file_path), 404, 'This attachment is no longer available on the server.');

        return Storage::disk('private')->download($message->file_path, $message->file_name ?: basename($message->file_path));
    }

    protected function requireSuperAdmin(Request $request): void
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
    }

    protected function authorizeMember(Request $request, AdminGroup $group): void
    {
        abort_unless($group->hasMember($request->user()), 403);
    }

    /**
     * @return \Illuminate\Support\Collection<int,User>
     */
    protected function eligibleAdmins()
    {
        return User::where('is_admin', true)->orderBy('name')->get();
    }

    /**
     * @return array{name:string,members:list<int>}
     */
    protected function validateGroup(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'members' => ['required', 'array', 'min:1'],
            'members.*' => ['integer', 'exists:users,id'],
        ]);
    }

    /**
     * Only real admins can be members. The person saving the group stays in
     * it when creating it, so a super admin can't lock themselves out by
     * accident.
     *
     * @param  array{members:list<int>}  $data
     * @return list<int>
     */
    protected function memberIds(array $data, User $actor, ?AdminGroup $group = null): array
    {
        $ids = User::whereIn('id', $data['members'])->where('is_admin', true)->pluck('id')->all();

        if ($group === null) {
            $ids[] = $actor->id;
        }

        return array_values(array_unique($ids));
    }

    protected function payload(AdminGroup $group, AdminGroupMessage $message): array
    {
        $mine = $message->sender_id === auth()->id();

        return [
            'id' => $message->id,
            // The shared chat thread aligns bubbles with `fromAdmin === viewer`;
            // group views always pass viewer=true, so this simply means "mine".
            'fromAdmin' => $mine,
            'sender' => $mine ? null : ($message->sender?->name ?? 'Unknown'),
            'type' => $message->is_deleted ? 'text' : $message->type,
            'body' => $message->is_deleted ? 'This message was deleted' : $message->body,
            'file' => (! $message->is_deleted && $message->file_path) ? route('admin.team-chat.groups.attachment', [$group, $message]) : null,
            'filename' => $message->file_name,
            'time' => $message->created_at->format('H:i'),
            'deleted' => (bool) $message->is_deleted,
            'edited' => (bool) $message->edited_at,
            'mine' => $mine,
            'editable' => $mine
                && ! $message->is_deleted
                && $message->type === 'text'
                && $message->created_at->gte(now()->subMinutes(30)),
        ];
    }
}
