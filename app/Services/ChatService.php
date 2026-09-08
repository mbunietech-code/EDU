<?php

namespace App\Services;

use App\Models\ChatMessage;
use App\Models\Conversation;
use Illuminate\Http\Request;

class ChatService
{
    public const EDIT_WINDOW_MINUTES = 30;

    public function __construct(protected NotificationService $notifications)
    {
    }

    public function send(Conversation $conversation, Request $request, bool $fromAdmin): ChatMessage
    {
        $type = $request->input('type', 'text');

        $rules = [
            'type' => ['required', 'string', 'in:text,image,video,audio'],
            'body' => ['nullable', 'string', 'max:4000'],
        ];

        if ($type === 'text') {
            $rules['body'] = ['nullable', 'string', 'max:4000'];
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
            'is_from_admin' => $fromAdmin,
            'type' => $type,
            'body' => $validated['body'] ?? null,
        ]);

        if ($request->hasFile('file')) {
            $file = $request->file('file');

            $message->forceFill([
                'file_path' => $file->store('chat', 'private'),
                'file_name' => $file->getClientOriginalName(),
            ])->save();
        }

        $conversation->touch();

        if ($fromAdmin) {
            $this->notifications->notifyUserNewChatMessage($conversation);
        } else {
            $this->notifications->notifyAdminsNewChatMessage($conversation);
        }

        return $message;
    }

    /**
     * Edit a message's text. Only the side that sent it may edit (admins are
     * treated as one collective side, same as everywhere else in this
     * feature), only within the edit window, and only while it isn't
     * already deleted or a media message.
     */
    public function edit(ChatMessage $message, bool $asAdmin, string $body): ChatMessage
    {
        abort_unless((bool) $message->is_from_admin === $asAdmin, 403);
        abort_if($message->is_deleted, 422, 'This message was deleted.');
        abort_unless($message->type === 'text', 422, 'Only text messages can be edited.');
        abort_if(
            $message->created_at->lt(now()->subMinutes(self::EDIT_WINDOW_MINUTES)),
            422,
            'This message can no longer be edited (past '.self::EDIT_WINDOW_MINUTES.' minutes).',
        );

        $message->update(['body' => $body, 'edited_at' => now()]);

        return $message;
    }

    /**
     * Tombstone a message — the row stays (for the other side's history
     * scroll position) but its content is gone and it renders as
     * "This message was deleted". Any time, no window.
     */
    public function delete(ChatMessage $message, bool $asAdmin): ChatMessage
    {
        abort_unless((bool) $message->is_from_admin === $asAdmin, 403);

        if ($message->file_path) {
            \Illuminate\Support\Facades\Storage::disk('private')->delete($message->file_path);
        }

        $message->update([
            'body' => null,
            'file_path' => null,
            'file_name' => null,
            'is_deleted' => true,
        ]);

        return $message;
    }

    /**
     * @param  bool  $viewerIsAdmin  Whether the page rendering this payload belongs
     *                               to an admin (as opposed to the customer) — used
     *                               to compute whether *this viewer* may edit/delete
     *                               the message (only their own side's messages).
     */
    public function payload(Conversation $conversation, ChatMessage $message, string $attachmentRoute, bool $viewerIsAdmin = false): array
    {
        $mine = (bool) $message->is_from_admin === $viewerIsAdmin;

        return [
            'id' => $message->id,
            'fromAdmin' => (bool) $message->is_from_admin,
            'type' => $message->is_deleted ? 'text' : $message->type,
            'body' => $message->is_deleted ? 'This message was deleted' : $message->body,
            'file' => (! $message->is_deleted && $message->file_path) ? route($attachmentRoute, [$conversation, $message]) : null,
            'filename' => $message->file_name,
            'time' => $message->created_at->format('H:i'),
            'deleted' => (bool) $message->is_deleted,
            'edited' => (bool) $message->edited_at,
            'mine' => $mine,
            'editable' => $mine && $this->isEditable($message),
        ];
    }

    protected function isEditable(ChatMessage $message): bool
    {
        return ! $message->is_deleted
            && $message->type === 'text'
            && $message->created_at->gte(now()->subMinutes(self::EDIT_WINDOW_MINUTES));
    }
}