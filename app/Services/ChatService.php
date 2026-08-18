<?php

namespace App\Services;

use App\Models\ChatMessage;
use App\Models\Conversation;
use Illuminate\Http\Request;

class ChatService
{
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

    public function payload(Conversation $conversation, ChatMessage $message, string $attachmentRoute): array
    {
        return [
            'id' => $message->id,
            'fromAdmin' => (bool) $message->is_from_admin,
            'type' => $message->type,
            'body' => $message->body,
            'file' => $message->file_path ? route($attachmentRoute, [$conversation, $message]) : null,
            'filename' => $message->file_name,
            'time' => $message->created_at->format('H:i'),
        ];
    }
}