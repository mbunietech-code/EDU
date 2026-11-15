<x-layouts.admin title="AI Assistant" header="AI Assistant">
@php
    $firstName = \Illuminate\Support\Str::of(auth()->user()->name)->explode(' ')->first();
    $suggestions = [
        ['icon' => '🚨', 'title' => 'Any problems right now?', 'prompt' => 'Check the whole system now: new errors, payments waiting, customers waiting in chat, missed video help calls. What needs attention first?'],
        ['icon' => '📈', 'title' => 'This week in numbers', 'prompt' => 'Give me a short report for the last 7 days: orders, revenue, payments, new users and anything unusual.'],
        ['icon' => '💬', 'title' => 'Customers waiting', 'prompt' => 'Which customers are waiting for a reply in support chat? Summarise each and draft a reply.'],
        ['icon' => '🛠️', 'title' => 'Explain the latest errors', 'prompt' => 'List the latest unresolved server errors, explain the likely cause of each and how to fix it.'],
        ['icon' => '🗄️', 'title' => 'Review database optimization', 'prompt' => 'Review the latest database optimization scan. Which recommendations are safe to approve, which should I reject, and why?'],
        ['icon' => '🖼️', 'title' => 'Design a poster', 'prompt' => 'Design an A4 poster (1240x1754) promoting our AI tools with real product names from the catalogue. Text in Swahili.'],
        ['icon' => '✍️', 'title' => 'Write a social post', 'prompt' => 'Write an Instagram and WhatsApp status post in Swahili promoting MbunieEduHub live classes, with hashtags.'],
    ];
    $create = [
        ['label' => 'Poster (A4)', 'prompt' => 'Design an A4 poster (1240x1754) about: '],
        ['label' => 'Social post (1080x1080)', 'prompt' => 'Design a square social media post (1080x1080) about: '],
        ['label' => 'Story / status (1080x1920)', 'prompt' => 'Design a WhatsApp status / Instagram story (1080x1920) about: '],
        ['label' => 'Banner (1200x628)', 'prompt' => 'Design a web / Facebook banner (1200x628) about: '],
        ['label' => 'Website hero (1920x600)', 'prompt' => 'Design a website hero banner (1920x600) about: '],
        ['label' => 'YouTube thumbnail (1280x720)', 'prompt' => 'Design a YouTube thumbnail (1280x720) for a video about: '],
        ['label' => 'Logo or icon', 'prompt' => 'Design a simple logo / icon (512x512) for: '],
        ['label' => 'Certificate', 'prompt' => 'Design a certificate of completion (1754x1240) for: '],
        ['label' => 'Audio script (Listen button reads it)', 'prompt' => 'Write a short radio / voice over script (about 30 seconds) about: '],
        ['label' => 'Video script and storyboard', 'prompt' => 'Write a 30 second video script with a scene by scene storyboard (text on screen, voice over, visuals) about: '],
    ];
@endphp

<div class="-m-4 sm:-m-6 lg:-m-8"
     x-data="aiChat({
        base: @js(url('admin/ai-assistant')),
        messageBase: @js(url('admin/ai-assistant/messages')),
        pollBase: @js(url('admin/ai-assistant/messages')),
        indexUrl: @js(route('admin.ai-assistant.index')),
        sendUrl: @js(route('admin.ai-assistant.send', $active)),
        regenerateUrl: @js(route('admin.ai-assistant.regenerate', $active)),
        activeId: {{ $active->id }},
        title: @js($active->displayTitle()),
        ask: @js($ask),
        messages: @js($messages),
        conversations: @js($conversations->map(fn ($c) => [
            'id' => $c->id,
            'title' => $c->displayTitle(),
            'updated_at' => $c->updated_at?->toIso8601String(),
            'url' => route('admin.ai-assistant.show', $c),
        ])->values()),
     })">

    <div class="flex h-[calc(100dvh-4rem)] overflow-hidden bg-white">

        {{-- Chats list ------------------------------------------------------- --}}
        <div x-show="sidebar" x-transition.opacity @click="sidebar = false" class="fixed inset-0 z-30 bg-gray-900/40 md:hidden" x-cloak></div>
        <aside :class="sidebar ? 'translate-x-0' : '-translate-x-full md:translate-x-0'"
               class="fixed inset-y-0 left-0 z-40 flex w-72 flex-col border-r border-gray-200 bg-gray-50 transition-transform md:static md:z-auto md:w-64 md:shrink-0">
            <div class="flex items-center gap-2 p-3">
                <form method="POST" action="{{ route('admin.ai-assistant.new') }}" class="flex-1">
                    @csrf
                    <button type="submit" class="flex w-full items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-800 shadow-sm hover:border-indigo-300 hover:text-indigo-700">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" /></svg>
                        New chat
                    </button>
                </form>
                <button type="button" class="rounded-lg p-2 text-gray-500 hover:bg-gray-200 md:hidden" @click="sidebar = false" aria-label="Close">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>
            <div class="px-3 pb-2">
                <input type="search" x-model="search" placeholder="Search chats" class="w-full rounded-lg border-gray-200 bg-white py-1.5 text-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            <nav class="flex-1 overflow-y-auto px-2 pb-3">
                <template x-for="g in groups" :key="g.label">
                    <div class="mt-3">
                        <p class="px-2 pb-1 text-[11px] font-semibold uppercase tracking-wider text-gray-400" x-text="g.label"></p>
                        <template x-for="c in g.items" :key="c.id">
                            <div class="group flex items-center rounded-lg" :class="c.id === activeId ? 'bg-indigo-50' : 'hover:bg-gray-200/60'">
                                <a :href="c.url" class="min-w-0 flex-1 truncate px-2 py-2 text-sm" :class="c.id === activeId ? 'font-medium text-indigo-700' : 'text-gray-700'" x-text="c.title"></a>
                                <button type="button" title="Rename" class="hidden rounded p-1 text-gray-400 hover:text-gray-700 group-hover:block" @click="renameConversation(c)">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487z" /></svg>
                                </button>
                                <button type="button" title="Delete" class="mr-1 hidden rounded p-1 text-gray-400 hover:text-red-600 group-hover:block" @click="deleteConversation(c)">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
                                </button>
                            </div>
                        </template>
                    </div>
                </template>
                <p x-show="groups.length === 0" class="px-2 py-6 text-center text-xs text-gray-400">No chats yet.</p>
            </nav>

            <div class="border-t border-gray-200 p-3 text-xs text-gray-500">
                <a href="{{ route('admin.ai-assistant.settings') }}" class="flex items-center justify-between rounded-lg px-2 py-1.5 hover:bg-gray-200/60">
                    <span class="flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full {{ $usesDeepSeek ? 'bg-emerald-500' : 'bg-amber-500' }}"></span>
                        {{ $usesDeepSeek ? 'DeepSeek: '.$model : 'Free mode (no DeepSeek key)' }}
                    </span>
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 010 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 010-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                </a>
            </div>
        </aside>

        {{-- Conversation ------------------------------------------------------ --}}
        <section class="flex min-w-0 flex-1 flex-col">
            <div class="flex items-center gap-2 border-b border-gray-100 px-3 py-2 md:px-5">
                <button type="button" class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 md:hidden" @click="sidebar = true" aria-label="Chats">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25H12" /></svg>
                </button>
                <p class="min-w-0 flex-1 truncate text-sm font-medium text-gray-800" x-text="title"></p>
                <span class="hidden rounded-full bg-gray-100 px-2.5 py-0.5 text-xs text-gray-500 sm:inline">Read-only access to live data</span>
            </div>

            <div class="flex-1 overflow-y-auto" x-ref="scroll" @scroll.passive="onScroll()" @click="onContentClick($event)">
                <div class="mx-auto w-full max-w-3xl px-4 py-6 md:px-6">

                    {{-- Empty chat: greeting and ideas --}}
                    <template x-if="messages.length === 0">
                        <div class="pt-6 md:pt-14">
                            <div class="flex items-center gap-3">
                                <x-brand-mark class="h-10 w-10" rounded="rounded-xl" />
                                <div>
                                    <h2 class="text-2xl font-semibold text-gray-900">Habari {{ $firstName }}, nikusaidie nini leo?</h2>
                                    <p class="text-sm text-gray-500">Ask about orders, payments, errors and customers, or create posters, banners and posts.</p>
                                </div>
                            </div>
                            @unless ($usesDeepSeek)
                                <div class="mt-5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                                    No DeepSeek API key yet, so answers come from simple built-in summaries.
                                    <a href="{{ route('admin.ai-assistant.settings') }}" class="font-semibold underline">Add the key</a> to turn on the full chat.
                                </div>
                            @endunless
                            <div class="mt-8 grid gap-3 sm:grid-cols-2">
                                @foreach ($suggestions as $s)
                                    <button type="button" @click="useSuggestion(@js($s['prompt']))"
                                            class="rounded-xl border border-gray-200 p-4 text-left transition hover:border-indigo-300 hover:bg-indigo-50/40">
                                        <p class="text-sm font-medium text-gray-900">{{ $s['icon'] }} {{ $s['title'] }}</p>
                                        <p class="mt-1 line-clamp-2 text-xs text-gray-500">{{ $s['prompt'] }}</p>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </template>

                    {{-- Messages --}}
                    <template x-for="m in messages" :key="m.id">
                        <div class="mb-6">
                            <template x-if="m.role === 'user'">
                                <div class="flex justify-end">
                                    <div class="max-w-[85%] whitespace-pre-wrap break-words rounded-2xl rounded-br-md bg-gray-100 px-4 py-2.5 text-[15px] leading-7 text-gray-900" x-text="m.content"></div>
                                </div>
                            </template>

                            <template x-if="m.role === 'assistant'">
                                <div class="flex gap-3">
                                    <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-indigo-600 text-xs font-bold text-white">AI</span>
                                    <div class="min-w-0 flex-1">
                                        <template x-if="m.status === 'streaming' && !m.content">
                                            <p class="flex items-center gap-2 py-1 text-sm text-gray-500"><span class="ai-spinner"></span><span x-text="m.activity || 'Thinking...'"></span></p>
                                        </template>
                                        <div class="ai-md" :class="m.status === 'streaming' && m.content ? 'ai-cursor' : ''" x-html="html(m)"></div>
                                        <p x-show="m.status === 'streaming' && m.content && m.activity" class="mt-2 flex items-center gap-2 text-xs text-gray-500"><span class="ai-spinner"></span><span x-text="m.activity"></span></p>
                                        <p x-show="m.status === 'error'" class="mt-2 text-xs text-red-600">This answer did not finish.</p>

                                        <div x-show="m.status !== 'streaming'" class="mt-2 flex flex-wrap items-center gap-1 text-gray-400">
                                            <button type="button" class="rounded-md p-1.5 hover:bg-gray-100 hover:text-gray-700" title="Copy" @click="copy(m.content, null)">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 01-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 011.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 00-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 01-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 00-3.375-3.375h-1.5a1.125 1.125 0 01-1.125-1.125v-1.5a3.375 3.375 0 00-3.375-3.375H9.75" /></svg>
                                            </button>
                                            <button type="button" class="rounded-md p-1.5 hover:bg-gray-100 hover:text-gray-700" :class="speakingId === m.id ? 'text-indigo-600' : ''" :title="speakingId === m.id ? 'Stop reading' : 'Listen'" @click="speak(m)">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.114 5.636a9 9 0 010 12.728M16.463 8.288a5.25 5.25 0 010 7.424M6.75 8.25l4.72-4.72a.75.75 0 011.28.53v15.88a.75.75 0 01-1.28.53l-4.72-4.72H4.51c-.88 0-1.704-.507-1.938-1.354A9.01 9.01 0 012.25 12c0-.83.112-1.633.322-2.396C2.806 8.756 3.63 8.25 4.51 8.25H6.75z" /></svg>
                                            </button>
                                            <button type="button" x-show="m.id === lastAssistantId" class="rounded-md p-1.5 hover:bg-gray-100 hover:text-gray-700" title="Answer again" @click="regenerate()">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" /></svg>
                                            </button>
                                            <template x-if="m.tools_used && m.tools_used.length">
                                                <span class="ml-1 text-xs" x-text="'Looked at: ' + [...new Set(m.tools_used)].map(t => t.replace(/^(get|list|find)_/, '').replace(/_/g, ' ')).join(', ')"></span>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </div>

            {{-- Composer ---------------------------------------------------------- --}}
            <div class="border-t border-gray-100 bg-white px-3 pb-3 pt-2 md:px-6">
                <div class="mx-auto w-full max-w-3xl">
                    <p x-show="error" x-text="error" class="mb-2 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700" x-cloak></p>
                    <form @submit.prevent="send()" class="relative rounded-2xl border border-gray-300 bg-white shadow-sm focus-within:border-indigo-400 focus-within:ring-1 focus-within:ring-indigo-400">
                        <textarea x-ref="input" x-model="draft" rows="1" @input="autosize()" @keydown="onKey($event)"
                                  placeholder="Message Mbunie AI"
                                  class="block max-h-60 w-full resize-none border-0 bg-transparent px-4 pb-1 pt-3 text-[15px] focus:ring-0"></textarea>
                        <div class="flex items-center justify-between px-2 pb-2">
                            <div class="relative" @click.outside="createOpen = false">
                                <button type="button" @click="createOpen = !createOpen" class="flex items-center gap-1.5 rounded-full px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-100">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.53 16.122a3 3 0 00-5.78 1.128 2.25 2.25 0 01-2.4 2.245 4.5 4.5 0 008.4-2.245c0-.399-.078-.78-.22-1.128zm0 0a15.998 15.998 0 003.388-1.62m-5.043-.025a15.994 15.994 0 011.622-3.395m3.42 3.42a15.995 15.995 0 004.764-4.648l3.876-5.814a1.151 1.151 0 00-1.597-1.597L14.146 6.32a15.996 15.996 0 00-4.649 4.763m3.42 3.42a6.776 6.776 0 00-3.42-3.42" /></svg>
                                    Create
                                </button>
                                <div x-show="createOpen" x-transition x-cloak class="absolute bottom-full left-0 z-20 mb-2 w-72 overflow-hidden rounded-xl border border-gray-200 bg-white py-1 shadow-lg">
                                    @foreach ($create as $c)
                                        <button type="button" @click="useSuggestion(@js($c['prompt']), false)" class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-indigo-50 hover:text-indigo-700">{{ $c['label'] }}</button>
                                    @endforeach
                                    <p class="border-t border-gray-100 px-4 py-2 text-[11px] leading-4 text-gray-400">Designs come as SVG with PNG download. Photos, voice files and video are not available yet.</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="hidden text-[11px] text-gray-400 sm:inline">Enter to send, Shift+Enter for a new line</span>
                                <button type="button" x-show="streaming" @click="stop()" title="Stop" class="flex h-9 w-9 items-center justify-center rounded-full bg-gray-900 text-white hover:bg-gray-700" x-cloak>
                                    <span class="h-3 w-3 rounded-sm bg-white"></span>
                                </button>
                                <button type="submit" x-show="!streaming" :disabled="busy || !draft.trim()" title="Send"
                                        class="flex h-9 w-9 items-center justify-center rounded-full bg-indigo-600 text-white hover:bg-indigo-700 disabled:bg-gray-200 disabled:text-gray-400">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 10.5L12 3m0 0l7.5 7.5M12 3v18" /></svg>
                                </button>
                            </div>
                        </div>
                    </form>
                    <p class="mt-1.5 text-center text-[11px] text-gray-400">Mbunie AI can make mistakes. It only reads data; changes are always made by you on the admin pages.</p>
                </div>
            </div>
        </section>
    </div>
</div>
</x-layouts.admin>
