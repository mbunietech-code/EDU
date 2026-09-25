{{-- Side panel: lg = 22rem right column (during a call on desktop it slides in over the
     video from the right edge); below lg = full-screen sheet. Tabs Chat | Q&A | People | Info. --}}
<aside x-show="panelVisible" x-cloak x-ref="panel"
    x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
    x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
    @mouseenter="panelHover = true; showPanel()" @mouseleave="onPanelLeave()"
    class="fixed inset-0 z-40 flex flex-col bg-gray-900 sm:inset-y-0 sm:left-auto sm:right-0 sm:w-[22rem] sm:border-l sm:border-gray-800 sm:shadow-2xl lg:shrink-0"
    :class="autoHide ? 'lg:absolute lg:inset-y-0 lg:left-auto lg:right-0 lg:z-40 lg:shadow-2xl' : 'lg:static lg:inset-auto lg:z-auto lg:shadow-none'"
    aria-label="Class chat, questions and people"
    :role="isLg ? null : 'dialog'" :aria-modal="isLg ? null : 'true'">

    <div class="flex h-12 shrink-0 items-center border-b border-gray-800 px-2">
        <div class="flex min-w-0 flex-1 items-center gap-1" role="tablist" aria-label="Panel sections">
            @php($tabOrder = ['chat', 'qa', 'people', 'info'])
            @foreach (['chat' => 'Chat', 'qa' => 'Q&A', 'people' => 'People', 'info' => 'Info'] as $key => $label)
                @php($tabIndex = array_search($key, $tabOrder, true))
                @php($nextTab = $tabOrder[($tabIndex + 1) % 4])
                @php($prevTab = $tabOrder[($tabIndex + 3) % 4])
                <button type="button" role="tab" id="panel-tab-{{ $key }}" aria-controls="panel-{{ $key }}"
                    :aria-selected="(tab === '{{ $key }}').toString()" :tabindex="tab === '{{ $key }}' ? 0 : -1"
                    @click="openTab('{{ $key }}')"
                    @keydown.arrow-right.prevent="openTab('{{ $nextTab }}'); $nextTick(() => document.getElementById('panel-tab-' + tab)?.focus())"
                    @keydown.arrow-left.prevent="openTab('{{ $prevTab }}'); $nextTick(() => document.getElementById('panel-tab-' + tab)?.focus())"
                    class="relative inline-flex items-center gap-1 rounded-md px-2.5 py-1.5 text-sm font-medium focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-500"
                    :class="tab === '{{ $key }}' ? 'bg-gray-800 text-white' : 'text-gray-400 hover:text-gray-200'">
                    {{ $label }}
                    @if ($key === 'chat')
                        <span x-show="unread.chat > 0" x-cloak class="rounded-full bg-indigo-600 px-1.5 text-[11px] leading-4 text-white" x-text="unread.chat > 99 ? '99+' : unread.chat"></span>
                    @elseif ($key === 'qa')
                        <span x-show="unread.qa > 0" x-cloak class="rounded-full bg-indigo-600 px-1.5 text-[11px] leading-4 text-white" x-text="unread.qa > 99 ? '99+' : unread.qa"></span>
                    @elseif ($key === 'people')
                        <span class="text-xs tabular-nums text-gray-500" x-text="counts.participants"></span>
                    @endif
                </button>
            @endforeach
        </div>
        <button type="button" @click="closePanel()" x-show="!isLg || autoHide"
            class="ml-1 inline-flex h-9 w-9 items-center justify-center rounded-lg text-gray-300 hover:bg-gray-800 hover:text-white"
            aria-label="Close panel">
            @include('learn.rooms.partials.icon', ['name' => 'x'])
        </button>
    </div>

    {{-- Chat --}}
    <section id="panel-chat" role="tabpanel" aria-labelledby="panel-tab-chat" x-show="tab === 'chat'" class="flex min-h-0 flex-1 flex-col">
        <template x-if="announcements.length">
            <div class="max-h-40 shrink-0 overflow-y-auto border-b border-gray-800 bg-amber-500/10 px-3 py-2" aria-label="Pinned announcements">
                <p class="flex items-center gap-1 text-xs font-semibold uppercase tracking-wide text-amber-300">
                    @include('learn.rooms.partials.icon', ['name' => 'megaphone', 'class' => 'h-4 w-4'])
                    Announcements
                </p>
                <ul class="mt-1 space-y-1.5" role="list">
                    <template x-for="a in announcements" :key="'a' + a.id">
                        <li class="text-sm text-amber-100">
                            <p class="whitespace-pre-wrap break-words" x-text="a.body"></p>
                            <p class="text-[11px] text-amber-300/80"><span x-text="a.user.name"></span> · <span x-text="formatTime(a.created_at)"></span></p>
                        </li>
                    </template>
                </ul>
            </div>
        </template>

        <div x-ref="chatList" class="min-h-0 flex-1 overflow-y-auto px-3 py-3" aria-live="polite" aria-relevant="additions">
            <template x-if="feedLoaded && chatMessages.length === 0">
                <div class="py-10 text-center text-sm text-gray-400">
                    <p class="font-medium text-gray-300">No messages yet.</p>
                    <p class="mt-1" x-text="canChat ? 'Say hello to the class!' : chatDisabledText"></p>
                </div>
            </template>
            <template x-if="!feedLoaded">
                <p class="py-10 text-center text-sm text-gray-400">Loading messages…</p>
            </template>
            {{-- Chat bubbles: mine on the right, everyone else on the left (consecutive messages grouped). --}}
            <template x-for="(m, i) in chatMessages" :key="m.id">
                <div class="group flex items-end gap-2"
                    :class="[isMine(m) ? 'flex-row-reverse' : '', startsGroup(i) ? 'mt-3' : 'mt-0.5']">
                    {{-- Avatar (others only, on the first message of a group) --}}
                    <div class="h-7 w-7 shrink-0" x-show="!isMine(m)" aria-hidden="true">
                        <div x-show="endsGroup(i)" class="flex h-7 w-7 items-center justify-center rounded-full bg-gray-700 text-xs font-semibold text-gray-200"
                            x-text="(m.user.name || '?').trim().charAt(0).toUpperCase()"></div>
                    </div>

                    <div class="flex min-w-0 max-w-[80%] flex-col" :class="isMine(m) ? 'items-end' : 'items-start'">
                        <p x-show="startsGroup(i) && !isMine(m)" class="mb-0.5 flex items-center gap-1.5 px-1 text-xs">
                            <span class="font-semibold text-gray-200" x-text="m.user.name"></span>
                            <span x-show="m.is_host" class="rounded bg-indigo-600 px-1 text-[10px] font-semibold uppercase text-white">Host</span>
                        </p>
                        <div class="rounded-2xl px-3 py-1.5 text-sm"
                            :class="[
                                isMine(m) ? 'bg-indigo-600 text-white' : 'bg-gray-800 text-gray-100',
                                isMine(m) && endsGroup(i) ? 'rounded-br-sm' : '',
                                !isMine(m) && endsGroup(i) ? 'rounded-bl-sm' : '',
                                m.is_deleted ? 'opacity-70' : '',
                            ]">
                            <p x-show="!m.is_deleted" class="whitespace-pre-wrap break-words" x-text="m.body"></p>
                            <p x-show="m.is_deleted" class="italic" :class="isMine(m) ? 'text-indigo-100' : 'text-gray-400'">Message removed</p>
                            <p class="mt-0.5 text-right text-[10px] leading-none" :class="isMine(m) ? 'text-indigo-200' : 'text-gray-500'" x-text="formatTime(m.created_at)"></p>
                        </div>
                    </div>

                    <button type="button" x-show="m.can_delete" @click="askDelete(m)" :disabled="busy.message === m.id"
                        class="mb-1 shrink-0 rounded p-1 text-gray-500 opacity-100 hover:bg-gray-800 hover:text-red-400 focus-visible:opacity-100 lg:opacity-0 lg:group-hover:opacity-100"
                        aria-label="Delete message">
                        @include('learn.rooms.partials.icon', ['name' => 'trash', 'class' => 'h-4 w-4'])
                    </button>
                </div>
            </template>
        </div>

        <form class="shrink-0 border-t border-gray-800 p-3" @submit.prevent="send('chat')">
            <template x-if="canChat">
                <div>
                    <label for="chat-input" class="sr-only" x-text="announceMode ? 'Announcement' : 'Message'">Message</label>
                    <div class="flex items-end gap-2">
                        <textarea id="chat-input" x-model="drafts.chat" rows="2" :maxlength="cfg.maxMessageLength"
                            @keydown="onComposerKey($event, 'chat')"
                            :placeholder="announceMode ? 'Write an announcement for everyone…' : 'Write a message…'"
                            class="block min-h-[2.5rem] w-full resize-none rounded-lg border-0 bg-gray-800 px-3 py-2 text-sm text-white placeholder-gray-500 ring-1 ring-inset ring-gray-700 focus:ring-2 focus:ring-indigo-500"
                            :class="announceMode && 'ring-amber-500'"></textarea>
                        <button type="submit" :disabled="sending.chat || !drafts.chat.trim()"
                            class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-indigo-600 text-white hover:bg-indigo-500 disabled:opacity-50"
                            :aria-label="announceMode ? 'Post announcement' : 'Send message'">
                            @include('learn.rooms.partials.icon', ['name' => 'send', 'class' => 'h-5 w-5'])
                        </button>
                    </div>
                    <template x-if="isManager && urls.studio">
                        <label class="mt-2 inline-flex items-center gap-2 text-xs text-gray-300">
                            <input type="checkbox" x-model="announceMode" class="rounded border-gray-600 bg-gray-800 text-amber-500 focus:ring-amber-500">
                            Post as an announcement (pinned and notified)
                        </label>
                    </template>
                    <p x-show="composerError.chat" class="mt-1 text-xs text-red-400" role="alert" x-text="composerError.chat"></p>
                </div>
            </template>
            <template x-if="!canChat">
                <p class="text-center text-xs text-gray-400" x-text="chatDisabledText"></p>
            </template>
        </form>
    </section>

    {{-- Q&A --}}
    <section id="panel-qa" role="tabpanel" aria-labelledby="panel-tab-qa" x-show="tab === 'qa'" x-cloak class="flex min-h-0 flex-1 flex-col">
        <div class="flex shrink-0 items-center justify-between gap-2 border-b border-gray-800 px-3 py-2">
            <div class="inline-flex rounded-lg bg-gray-800 p-0.5 text-xs" role="group" aria-label="Filter questions">
                @foreach (['all' => 'All', 'open' => 'Open', 'answered' => 'Answered'] as $key => $label)
                    <button type="button" @click="qaFilter = '{{ $key }}'" :aria-pressed="(qaFilter === '{{ $key }}').toString()"
                        class="rounded-md px-2.5 py-1 font-medium"
                        :class="qaFilter === '{{ $key }}' ? 'bg-gray-700 text-white' : 'text-gray-400 hover:text-gray-200'">{{ $label }}</button>
                @endforeach
            </div>
            <span class="text-xs text-gray-400"><span class="tabular-nums" x-text="counts.questions_open"></span> open</span>
        </div>

        <div x-ref="qaList" class="min-h-0 flex-1 space-y-2 overflow-y-auto px-3 py-3" aria-live="polite" aria-relevant="additions">
            <template x-if="feedLoaded && questions.length === 0">
                <div class="py-10 text-center text-sm text-gray-400">
                    <p class="font-medium text-gray-300" x-text="qaFilter === 'all' ? 'No questions yet.' : (qaFilter === 'open' ? 'No open questions.' : 'No answered questions yet.')"></p>
                    <p class="mt-1" x-show="qaFilter === 'all'" x-text="canAsk ? 'Ask the host anything about the class.' : askDisabledText"></p>
                </div>
            </template>
            <template x-for="q in questions" :key="q.id">
                <div class="rounded-lg bg-gray-800/60 p-3 ring-1 ring-gray-800" :class="q.is_answered && 'opacity-80'">
                    <p class="flex flex-wrap items-baseline gap-x-1.5 text-xs">
                        <span class="font-semibold text-gray-100" x-text="q.user.name"></span>
                        <span class="text-gray-500" x-text="formatTime(q.created_at)"></span>
                        <span x-show="q.is_answered" class="rounded bg-emerald-600/20 px-1.5 text-[10px] font-semibold uppercase text-emerald-300">Answered</span>
                    </p>
                    <p x-show="!q.is_deleted" class="mt-1 whitespace-pre-wrap break-words text-sm text-gray-100" x-text="q.body"></p>
                    <p x-show="q.is_deleted" class="mt-1 text-sm italic text-gray-500">Question removed</p>
                    <div class="mt-2 flex flex-wrap gap-2" x-show="q.can_answer || q.can_delete">
                        <button type="button" x-show="q.can_answer" @click="answer(q)" :disabled="busy.message === q.id"
                            class="inline-flex items-center gap-1 rounded-md bg-emerald-600 px-2 py-1 text-xs font-semibold text-white hover:bg-emerald-500 disabled:opacity-60">
                            @include('learn.rooms.partials.icon', ['name' => 'check-circle', 'class' => 'h-4 w-4'])
                            Mark answered
                        </button>
                        <button type="button" x-show="q.can_delete" @click="askDelete(q)" :disabled="busy.message === q.id"
                            class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-medium text-gray-300 ring-1 ring-inset ring-gray-700 hover:text-red-400"
                            aria-label="Delete question">
                            @include('learn.rooms.partials.icon', ['name' => 'trash', 'class' => 'h-4 w-4'])
                            Delete
                        </button>
                    </div>
                </div>
            </template>
        </div>

        <form class="shrink-0 border-t border-gray-800 p-3" @submit.prevent="send('question')">
            <template x-if="canAsk">
                <div>
                    <label for="question-input" class="sr-only">Your question</label>
                    <div class="flex items-end gap-2">
                        <textarea id="question-input" x-model="drafts.question" rows="2" :maxlength="cfg.maxMessageLength"
                            @keydown="onComposerKey($event, 'question')" placeholder="Ask the host a question…"
                            class="block min-h-[2.5rem] w-full resize-none rounded-lg border-0 bg-gray-800 px-3 py-2 text-sm text-white placeholder-gray-500 ring-1 ring-inset ring-gray-700 focus:ring-2 focus:ring-indigo-500"></textarea>
                        <button type="submit" :disabled="sending.question || !drafts.question.trim()"
                            class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-indigo-600 text-white hover:bg-indigo-500 disabled:opacity-50"
                            aria-label="Send question">
                            @include('learn.rooms.partials.icon', ['name' => 'send', 'class' => 'h-5 w-5'])
                        </button>
                    </div>
                    <p x-show="composerError.question" class="mt-1 text-xs text-red-400" role="alert" x-text="composerError.question"></p>
                </div>
            </template>
            <template x-if="!canAsk">
                <p class="text-center text-xs text-gray-400" x-text="askDisabledText"></p>
            </template>
        </form>
    </section>

    {{-- People --}}
    <section id="panel-people" role="tabpanel" aria-labelledby="panel-tab-people" x-show="tab === 'people'" x-cloak class="flex min-h-0 flex-1 flex-col">
        <div class="flex shrink-0 items-center justify-between gap-2 border-b border-gray-800 px-3 py-2 text-xs text-gray-400">
            <span><span class="tabular-nums" x-text="counts.participants"></span> in the class</span>
            <template x-if="isManager && room.status === 'live'">
                <div class="flex items-center gap-1">
                    <button type="button" @click="muteEveryone('audio')" :disabled="busy.mod"
                        class="rounded-md px-2 py-1 font-medium text-indigo-300 hover:bg-gray-800 hover:text-indigo-200 disabled:opacity-50">Mute all</button>
                    <button type="button" @click="toggleLock()" :disabled="busy.mod"
                        class="inline-flex items-center gap-1 rounded-md px-2 py-1 font-medium text-indigo-300 hover:bg-gray-800 hover:text-indigo-200 disabled:opacity-50"
                        :aria-label="room.is_locked ? 'Unlock the room' : 'Lock the room'">
                        <span x-show="!room.is_locked">@include('learn.rooms.partials.icon', ['name' => 'lock', 'class' => 'h-3.5 w-3.5'])</span>
                        <span x-show="room.is_locked" x-cloak>@include('learn.rooms.partials.icon', ['name' => 'unlock', 'class' => 'h-3.5 w-3.5'])</span>
                        <span x-text="room.is_locked ? 'Unlock' : 'Lock'"></span>
                    </button>
                </div>
            </template>
        </div>
        <ul class="min-h-0 flex-1 divide-y divide-gray-800 overflow-y-auto" role="list">
            <template x-if="feedLoaded && participants.length === 0">
                <li class="px-3 py-10 text-center text-sm text-gray-400">No one is in the class yet.</li>
            </template>
            <template x-for="p in sortedParticipants" :key="p.user_id">
                <li class="px-3 py-2.5" x-data="{ open: false }">
                    <div class="flex items-center gap-3">
                        <div class="relative flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gray-700 text-sm font-semibold text-gray-200" aria-hidden="true">
                            <span x-text="(p.name || '?').trim().charAt(0).toUpperCase()"></span>
                            <span x-show="tileState(p.identity) && tileState(p.identity).speaking" class="absolute inset-0 rounded-full ring-2 ring-emerald-400"></span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-gray-100">
                                <span x-text="p.name"></span>
                                <span x-show="p.is_me" class="font-normal text-gray-400">(you)</span>
                            </p>
                            <p class="text-xs" :class="p.role === 'host' ? 'text-indigo-300' : 'text-gray-500'"
                                x-text="p.role === 'host' ? 'Host' : (tileState(p.identity) ? 'In the video call' : 'Watching the class page')"></p>
                        </div>
                        {{-- Live state from the SFU --}}
                        <div class="flex shrink-0 items-center gap-1 text-gray-400">
                            <span x-show="tileState(p.identity) && tileState(p.identity).screen" class="text-indigo-300" title="Sharing the screen">
                                @include('learn.rooms.partials.icon', ['name' => 'screen', 'class' => 'h-4 w-4'])<span class="sr-only">Sharing the screen</span>
                            </span>
                            <span :class="tileState(p.identity) && tileState(p.identity).cam ? 'text-gray-200' : 'text-gray-600'"
                                :title="tileState(p.identity) && tileState(p.identity).cam ? 'Camera on' : 'Camera off'">
                                @include('learn.rooms.partials.icon', ['name' => 'camera', 'class' => 'h-4 w-4'])
                                <span class="sr-only" x-text="tileState(p.identity) && tileState(p.identity).cam ? 'Camera on' : 'Camera off'"></span>
                            </span>
                            <span :class="tileState(p.identity) && tileState(p.identity).mic ? 'text-gray-200' : 'text-red-400'"
                                :title="tileState(p.identity) && tileState(p.identity).mic ? 'Microphone on' : 'Microphone off'">
                                <span x-show="tileState(p.identity) && tileState(p.identity).mic">@include('learn.rooms.partials.icon', ['name' => 'mic', 'class' => 'h-4 w-4'])</span>
                                <span x-show="!(tileState(p.identity) && tileState(p.identity).mic)">@include('learn.rooms.partials.icon', ['name' => 'mic-off', 'class' => 'h-4 w-4'])</span>
                                <span class="sr-only" x-text="tileState(p.identity) && tileState(p.identity).mic ? 'Microphone on' : 'Microphone off'"></span>
                            </span>
                        </div>
                        <button type="button" x-show="inCall && tileState(p.identity)" @click="pinParticipant(p)"
                            class="shrink-0 rounded p-1 text-gray-400 hover:bg-gray-800 hover:text-white" :aria-label="'Pin ' + p.name" title="Pin to the main view">
                            @include('learn.rooms.partials.icon', ['name' => 'pin', 'class' => 'h-4 w-4'])
                        </button>
                        <button type="button" x-show="canModerate(p)" @click="open = !open" :aria-expanded="open.toString()"
                            class="shrink-0 rounded p-1 text-gray-400 hover:bg-gray-800 hover:text-white" :aria-label="'Host controls for ' + p.name" title="Host controls">
                            @include('learn.rooms.partials.icon', ['name' => 'dots', 'class' => 'h-4 w-4'])
                        </button>
                    </div>

                    {{-- Host controls for this person --}}
                    <template x-if="canModerate(p)">
                        <div x-show="open" x-cloak class="mt-2 space-y-2 rounded-lg bg-gray-800/70 p-2 text-xs">
                            <div class="flex flex-wrap gap-1">
                                <button type="button" @click="muteParticipant(p, 'audio')" :disabled="busy.mod"
                                    class="rounded-md bg-gray-700 px-2 py-1 font-medium text-gray-100 hover:bg-gray-600 disabled:opacity-50">Mute mic</button>
                                <button type="button" @click="muteParticipant(p, 'video')" :disabled="busy.mod"
                                    class="rounded-md bg-gray-700 px-2 py-1 font-medium text-gray-100 hover:bg-gray-600 disabled:opacity-50">Stop camera</button>
                                <button type="button" @click="muteParticipant(p, 'screen')" :disabled="busy.mod" x-show="tileState(p.identity) && tileState(p.identity).screen"
                                    class="rounded-md bg-gray-700 px-2 py-1 font-medium text-gray-100 hover:bg-gray-600 disabled:opacity-50">Stop screen share</button>
                            </div>
                            <template x-if="p.permissions">
                                <div class="space-y-1">
                                    <p class="text-[11px] uppercase tracking-wide text-gray-500">May use</p>
                                    @foreach (['audio' => 'Microphone', 'video' => 'Camera', 'screen' => 'Screen share'] as $key => $label)
                                        <div class="flex items-center justify-between gap-2">
                                            <span class="text-gray-300">{{ $label }}</span>
                                            <button type="button" role="switch" :aria-checked="(!!p.permissions.{{ $key }}).toString()" :disabled="busy.mod"
                                                @click="setParticipantRight(p, '{{ $key }}', !p.permissions.{{ $key }})"
                                                :aria-label="'{{ $label }} for ' + p.name"
                                                class="relative inline-flex h-5 w-9 shrink-0 items-center rounded-full transition disabled:opacity-50"
                                                :class="p.permissions.{{ $key }} ? 'bg-emerald-500' : 'bg-gray-600'">
                                                <span class="inline-block h-4 w-4 rounded-full bg-white shadow transition" :class="p.permissions.{{ $key }} ? 'translate-x-4' : 'translate-x-0.5'"></span>
                                            </button>
                                        </div>
                                    @endforeach
                                    <button type="button" @click="resetParticipantRights(p)" :disabled="busy.mod"
                                        class="text-[11px] font-medium text-indigo-300 hover:text-indigo-200">Reset to the room settings</button>
                                </div>
                            </template>
                            <button type="button" @click="askRemove(p)" :disabled="busy.remove === p.user_id"
                                class="w-full rounded-md px-2 py-1 text-left font-medium text-red-300 ring-1 ring-inset ring-red-500/40 hover:bg-red-500/10 disabled:opacity-60">Remove from class</button>
                        </div>
                    </template>
                </li>
            </template>
        </ul>
    </section>

    {{-- Info + materials --}}
    <section id="panel-info" role="tabpanel" aria-labelledby="panel-tab-info" x-show="tab === 'info'" x-cloak class="min-h-0 flex-1 overflow-y-auto px-4 py-4 text-sm text-gray-300">
        <h2 class="text-base font-semibold text-white">{{ $room->title }}</h2>
        <dl class="mt-3 space-y-2">
            @if ($room->host)
                <div><dt class="text-xs text-gray-500">Host</dt><dd class="text-gray-100">{{ $room->host->name }}</dd></div>
            @endif
            @if ($room->scheduled_at)
                <div>
                    <dt class="text-xs text-gray-500">Scheduled</dt>
                    <dd class="text-gray-100">{{ $room->scheduled_at->format('D, d M Y · H:i') }}@if ($room->endsAt()) – {{ $room->endsAt()->format('H:i') }}@endif</dd>
                </div>
            @endif
            <div><dt class="text-xs text-gray-500">Who can join</dt><dd class="text-gray-100">{{ $room->accessLabel() }}</dd></div>
            @if ($room->category)
                <div><dt class="text-xs text-gray-500">Category</dt><dd class="text-gray-100">{{ $room->category->name }}</dd></div>
            @endif
            @if ($room->course)
                <div><dt class="text-xs text-gray-500">Course</dt><dd class="text-gray-100">{{ $room->course->title }}</dd></div>
            @endif
            <div><dt class="text-xs text-gray-500">Video</dt><dd class="text-gray-100">Our own secure video server</dd></div>
        </dl>

        {{-- Materials --}}
        <div class="mt-4 border-t border-gray-800 pt-4">
            <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500">Class materials</h3>
            <template x-if="materials.length === 0">
                <p class="mt-2 text-xs text-gray-500" x-text="isManager ? 'Share slides or worksheets with the class below.' : 'The host has not shared any files yet.'"></p>
            </template>
            <ul class="mt-2 space-y-1.5" role="list">
                <template x-for="m in materials" :key="m.id">
                    <li class="flex items-center gap-2 rounded-lg bg-gray-800/70 px-2 py-1.5">
                        @include('learn.rooms.partials.icon', ['name' => 'document', 'class' => 'h-4 w-4 shrink-0 text-indigo-300'])
                        <a :href="m.url" class="min-w-0 flex-1 truncate text-sm text-gray-100 hover:text-white hover:underline" x-text="m.title" :title="m.name"></a>
                        <span class="shrink-0 text-[11px] text-gray-500" x-text="formatBytes(m.size)"></span>
                        <button type="button" x-show="isManager" @click="openConfirm('material', 'Remove “' + m.title + '”?', 'Learners will no longer be able to download it.', 'Remove', m)"
                            class="shrink-0 rounded p-0.5 text-gray-500 hover:text-red-300" :aria-label="'Remove ' + m.title">
                            @include('learn.rooms.partials.icon', ['name' => 'trash', 'class' => 'h-4 w-4'])
                        </button>
                    </li>
                </template>
            </ul>
            <template x-if="isManager">
                <form class="mt-3 space-y-2" @submit.prevent="uploadMaterial($event)">
                    <label class="block">
                        <span class="sr-only">Title (optional)</span>
                        <input type="text" x-model="upload.title" maxlength="255" placeholder="Title (optional)"
                            class="w-full rounded-lg border-gray-700 bg-gray-800 px-3 py-1.5 text-sm text-white placeholder-gray-500">
                    </label>
                    <label class="block">
                        <span class="sr-only">File</span>
                        <input type="file" required :accept="(cfg.materialExtensions || []).map((e) => '.' + e).join(',')"
                            class="block w-full text-xs text-gray-300 file:mr-2 file:rounded-md file:border-0 file:bg-gray-700 file:px-2 file:py-1 file:text-xs file:font-semibold file:text-white hover:file:bg-gray-600">
                    </label>
                    <p class="text-[11px] text-gray-500" x-text="'Up to ' + (cfg.maxMaterialMb || 50) + ' MB · ' + (cfg.materialExtensions || []).join(', ').toUpperCase()"></p>
                    <p x-show="upload.error" class="text-xs text-red-300" x-text="upload.error" role="alert"></p>
                    <button type="submit" :disabled="busy.upload"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-500 disabled:opacity-60">
                        @include('learn.rooms.partials.icon', ['name' => 'upload', 'class' => 'h-4 w-4'])
                        <span x-text="busy.upload ? 'Uploading…' : 'Share file'"></span>
                    </button>
                </form>
            </template>
        </div>

        @if ($descriptionHtml)
            <div class="research-prose mt-4 break-words border-t border-gray-800 pt-4 text-sm text-gray-300">{!! $descriptionHtml !!}</div>
        @endif

        <div class="mt-4 border-t border-gray-800 pt-4">
            <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500">Tips</h3>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-xs text-gray-400">
                <li>Press Enter to send, Shift+Enter for a new line.</li>
                <li>Pin a person to keep them in the main view; switch between speaker and grid view with the layout button.</li>
                <li>If your camera or microphone does not start, check the browser’s site permissions (the lock icon in the address bar).</li>
                <li>If your connection drops, the class reconnects automatically.</li>
            </ul>
        </div>

        <a href="{{ route('learn.rooms.show', $room) }}" class="mt-4 inline-block text-sm font-medium text-indigo-300 hover:text-indigo-200">Open the class page</a>
    </section>
</aside>
