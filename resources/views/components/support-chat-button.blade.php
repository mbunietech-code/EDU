@props(['href' => null, 'label' => null, 'hideOn' => ['user.chat.*']])

@php
    // Hidden on the page it points to (and, in admin, on chat pages where it would cover the send button).
    $onChatPage = request()->routeIs(...(array) $hideOn);

    $unread = 0;
    if ($href === null && auth()->check() && ! $onChatPage) {
        try {
            $unread = \App\Models\ChatMessage::where('is_from_admin', true)
                ->where('is_read', false)
                ->whereHas('conversation', fn ($q) => $q->where('user_id', auth()->id()))
                ->count();
        } catch (\Throwable $e) {
            $unread = 0;
        }
    }
@endphp

@unless ($onChatPage)
    {{-- Styled here on purpose (not with Tailwind classes): the compiled site CSS only
         contains classes that existed at the last asset build, so new utility classes
         would silently not apply until someone rebuilds. --}}
    <style>
        .mb-chat-fab { position: fixed; right: 20px; bottom: 20px; z-index: 9999; display: flex; align-items: center; gap: 12px; text-decoration: none; }
        .mb-chat-fab__label { display: none; max-width: 220px; padding: 8px 12px; border-radius: 12px; background: #fff; color: #1f2937; font-size: 14px; font-weight: 500; line-height: 1.3; box-shadow: 0 10px 25px rgba(0,0,0,.15); border: 1px solid #e5e7eb; opacity: 0; transition: opacity .15s; pointer-events: none; }
        .mb-chat-fab__btn { position: relative; display: flex; align-items: center; justify-content: center; width: 64px; height: 64px; border-radius: 9999px; background: #fff; box-shadow: 0 10px 25px rgba(0,0,0,.25); border: 2px solid rgba(3,86,178,.25); transition: transform .15s, border-color .15s; }
        .mb-chat-fab__btn img { width: 52px; height: 52px; object-fit: contain; display: block; }
        .mb-chat-fab:hover .mb-chat-fab__btn { transform: scale(1.06); border-color: rgba(3,86,178,.6); }
        .mb-chat-fab:hover .mb-chat-fab__label { opacity: 1; }
        .mb-chat-fab__badge { position: absolute; top: -4px; right: -4px; min-width: 20px; height: 20px; padding: 0 6px; border-radius: 9999px; background: #dc2626; color: #fff; font-size: 12px; font-weight: 600; line-height: 20px; text-align: center; border: 2px solid #fff; box-sizing: content-box; }
        @media (min-width: 640px) { .mb-chat-fab { right: 24px; bottom: 24px; } .mb-chat-fab__label { display: block; } }
    </style>

    <a href="{{ $href ?? (auth()->check() ? route('user.chat.index') : route('login')) }}" class="mb-chat-fab" aria-label="Mbunie AI">
        <span class="mb-chat-fab__label">{{ $label ?? (auth()->check() ? 'Ongea na msaada wetu' : 'Ingia ili kuongea na msaada') }}</span>
        <span class="mb-chat-fab__btn">
            <picture>
                <source srcset="{{ asset('images/mbunie-ai.webp') }}" type="image/webp">
                <img src="{{ asset('images/mbunie-ai.png') }}" alt="Mbunie AI" width="52" height="52">
            </picture>
            @if ($unread > 0)
                <span class="mb-chat-fab__badge">{{ $unread > 9 ? '9+' : $unread }}</span>
            @endif
        </span>
    </a>
@endunless
