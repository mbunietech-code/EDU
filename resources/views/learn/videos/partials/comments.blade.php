{{-- Lesson discussion. Expects $video, $comments (paginator of top-level comments with replies), $commentCount. --}}
@php
    $viewer = auth()->user();
    $replyTo = (int) old('parent_id', 0);
@endphp

<section id="comments" class="mbui-card scroll-mt-24 p-5 sm:p-6" aria-labelledby="comments-title">
    <h2 id="comments-title" class="mbui-section-label">
        Discussion <span class="ml-1 font-normal text-gray-400">({{ $commentCount }})</span>
    </h2>

    {{-- New comment --}}
    <form method="POST" action="{{ route('learn.videos.comments.store', $video) }}" class="mt-4"
        x-data="{ body: @js($replyTo ? '' : (string) old('body', '')), sending: false }" @submit="sending = true">
        @csrf
        <label for="comment-body" class="sr-only">Add a comment</label>
        <textarea id="comment-body" name="body" rows="3" maxlength="2000" required x-model="body"
            placeholder="Ask a question or share a thought about this lesson…"
            class="mbui-input w-full @if (! $replyTo) @error('body') border-red-400 @enderror @endif"></textarea>
        @if (! $replyTo)
            @error('body')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        @endif
        <div class="mt-2 flex items-center justify-between gap-3">
            <span class="text-xs text-gray-400"><span x-text="body.length">0</span>/2000</span>
            <x-mbui.button type="submit" x-bind:disabled="sending || body.trim().length === 0"
                class="disabled:cursor-not-allowed disabled:opacity-50">Post comment</x-mbui.button>
        </div>
    </form>

    @if ($comments->isEmpty())
        <div class="mt-6 rounded-lg border border-dashed border-gray-200 px-4 py-8 text-center">
            <p class="text-sm font-medium text-gray-900">No comments yet</p>
            <p class="mt-1 text-sm text-gray-500">Be the first to start the discussion.</p>
        </div>
    @else
        <ul class="mt-6 space-y-5">
            @foreach ($comments as $comment)
                <li id="comment-{{ $comment->id }}" class="scroll-mt-24"
                    x-data="{ replying: {{ $replyTo === $comment->id ? 'true' : 'false' }}, reply: @js($replyTo === $comment->id ? (string) old('body', '') : ''), sending: false }">
                    <div class="flex gap-3">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-sm font-semibold text-indigo-700" aria-hidden="true">
                            {{ Str::upper(Str::substr($comment->user?->name ?? '?', 0, 1)) }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                                <span class="text-sm font-semibold text-gray-900">{{ $comment->user?->name ?? 'Former member' }}</span>
                                @if ($comment->user_id && $comment->user_id === $video->instructor_id)
                                    <x-mbui.badge appearance="info" class="!py-0.5">Instructor</x-mbui.badge>
                                @endif
                                <time class="text-xs text-gray-500" datetime="{{ $comment->created_at?->toIso8601String() }}"
                                    title="{{ $comment->created_at?->format('d M Y H:i') }}">{{ $comment->created_at?->diffForHumans() }}</time>
                            </div>
                            <p class="mt-1 whitespace-pre-line break-words text-sm text-gray-700">{{ $comment->body }}</p>
                            <div class="mt-1 flex items-center gap-1">
                                <button type="button" class="rounded px-1.5 py-1 text-xs font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-700"
                                    @click="replying = ! replying; $nextTick(() => replying && $refs.reply.focus())" :aria-expanded="replying.toString()">
                                    Reply
                                </button>
                                @if (\App\Http\Controllers\Learn\VideoCommentController::canDelete($viewer, $video, $comment))
                                    @php($replyCount = $comment->replies->count())
                                    <x-learning.confirm-delete
                                        :action="route('learn.videos.comments.destroy', [$video, $comment])"
                                        title="Delete this comment?"
                                        :impact="array_values(array_filter([
                                            'The comment is removed from the discussion',
                                            $replyCount ? $replyCount.' '.Str::plural('reply', $replyCount).' will also be deleted' : null,
                                        ]))"
                                        button-label="Delete comment" />
                                @endif
                            </div>

                            {{-- Replies --}}
                            @if ($comment->replies->isNotEmpty())
                                <ul class="mt-3 space-y-3 border-l-2 border-gray-100 pl-4">
                                    @foreach ($comment->replies as $reply)
                                        <li id="comment-{{ $reply->id }}" class="flex gap-3 scroll-mt-24">
                                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-gray-100 text-xs font-semibold text-gray-600" aria-hidden="true">
                                                {{ Str::upper(Str::substr($reply->user?->name ?? '?', 0, 1)) }}
                                            </span>
                                            <div class="min-w-0 flex-1">
                                                <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                                                    <span class="text-sm font-semibold text-gray-900">{{ $reply->user?->name ?? 'Former member' }}</span>
                                                    @if ($reply->user_id && $reply->user_id === $video->instructor_id)
                                                        <x-mbui.badge appearance="info" class="!py-0.5">Instructor</x-mbui.badge>
                                                    @endif
                                                    <time class="text-xs text-gray-500" datetime="{{ $reply->created_at?->toIso8601String() }}"
                                                        title="{{ $reply->created_at?->format('d M Y H:i') }}">{{ $reply->created_at?->diffForHumans() }}</time>
                                                </div>
                                                <p class="mt-1 whitespace-pre-line break-words text-sm text-gray-700">{{ $reply->body }}</p>
                                                @if (\App\Http\Controllers\Learn\VideoCommentController::canDelete($viewer, $video, $reply))
                                                    <div class="mt-1">
                                                        <x-learning.confirm-delete
                                                            :action="route('learn.videos.comments.destroy', [$video, $reply])"
                                                            title="Delete this reply?"
                                                            :impact="['The reply is removed from the discussion']"
                                                            button-label="Delete reply" />
                                                    </div>
                                                @endif
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif

                            {{-- Reply form --}}
                            <form x-show="replying" x-cloak method="POST" action="{{ route('learn.videos.comments.store', $video) }}"
                                class="mt-3" @submit="sending = true">
                                @csrf
                                <input type="hidden" name="parent_id" value="{{ $comment->id }}">
                                <label for="reply-{{ $comment->id }}" class="sr-only">Reply to {{ $comment->user?->name ?? 'this comment' }}</label>
                                <textarea id="reply-{{ $comment->id }}" x-ref="reply" name="body" rows="2" maxlength="2000" required x-model="reply"
                                    placeholder="Write a reply…" class="mbui-input w-full"></textarea>
                                @if ($replyTo === $comment->id)
                                    @error('body')
                                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                    @enderror
                                    @error('parent_id')
                                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                    @enderror
                                @endif
                                <div class="mt-2 flex justify-end gap-2">
                                    <x-mbui.button variant="ghost" @click="replying = false">Cancel</x-mbui.button>
                                    <x-mbui.button type="submit" x-bind:disabled="sending || reply.trim().length === 0"
                                        class="disabled:cursor-not-allowed disabled:opacity-50">Reply</x-mbui.button>
                                </div>
                            </form>
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>

        <div class="mt-6">{{ $comments->links() }}</div>
    @endif
</section>
