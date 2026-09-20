<x-layouts.admin title="AI Assistant" header="AI Assistant">

    <div class="mbui-page-header">
        <div>
            <h1 class="mbui-title">AI Assistant</h1>
            <p class="mt-1 text-sm text-gray-500">Bofya swali kwa haraka, au andika lako mwenyewe. Inasoma tu takwimu za muhtasari — bure, bila API ya nje.</p>
        </div>
        <form method="POST" action="{{ route('admin.ai-assistant.new') }}">
            @csrf
            <x-mbui.button type="submit" variant="secondary">+ Mazungumzo mapya</x-mbui.button>
        </form>
    </div>

    <div class="mt-6 grid gap-4 lg:grid-cols-[240px_1fr]">

        {{-- History ------------------------------------------------------ --}}
        <div class="mbui-card overflow-hidden lg:h-[70vh]" x-data="{
            deleting: false,
            async deleteConversation(id, wasActive) {
                if (! confirm('Futa mazungumzo haya? Hatua hii haiwezi kurudishwa.')) return;
                this.deleting = true;
                try {
                    const res = await fetch('{{ url('admin/ai-assistant') }}/' + id, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                            'Accept': 'application/json',
                        },
                    });
                    if (res.ok) {
                        if (wasActive) {
                            window.location.href = '{{ route('admin.ai-assistant.index') }}';
                        } else {
                            document.getElementById('history-item-' + id)?.remove();
                        }
                    }
                } finally {
                    this.deleting = false;
                }
            },
        }">
            <div class="border-b border-gray-200 px-4 py-3">
                <h2 class="text-sm font-semibold text-gray-900">Historia</h2>
            </div>
            <div class="divide-y divide-gray-100 overflow-y-auto lg:max-h-[calc(70vh-2.75rem)]">
                @forelse ($conversations as $conv)
                    <div id="history-item-{{ $conv->id }}" class="group flex items-center gap-1 px-2 py-1 {{ $conv->id === $active->id ? 'bg-indigo-50' : '' }}">
                        <a href="{{ route('admin.ai-assistant.show', $conv) }}" class="min-w-0 flex-1 rounded px-2 py-2 text-sm hover:bg-gray-100">
                            <p class="truncate font-medium text-gray-900">{{ $conv->displayTitle() }}</p>
                            <p class="mt-0.5 text-xs text-gray-400">{{ $conv->updated_at->diffForHumans() }}</p>
                        </a>
                        <button type="button" title="Futa"
                                class="rounded p-1 text-gray-400 opacity-0 hover:bg-gray-100 hover:text-red-600 group-hover:opacity-100"
                                :disabled="deleting"
                                @click="deleteConversation({{ $conv->id }}, {{ $conv->id === $active->id ? 'true' : 'false' }})">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                            </svg>
                        </button>
                    </div>
                @empty
                    <p class="px-4 py-6 text-center text-xs text-gray-400">Bado hakuna mazungumzo.</p>
                @endforelse
            </div>
        </div>

        {{-- Chat ----------------------------------------------------------- --}}
        <div class="mbui-card flex h-[70vh] flex-col overflow-hidden p-0"
             x-data="aiAssistant({
                 sendUrl: '{{ route('admin.ai-assistant.send', $active) }}',
                 csrf: document.querySelector('meta[name=csrf-token]').content,
                 initialMessages: {{ Js::from($messages->map(fn ($m) => ['role' => $m->role, 'content' => $m->content, 'tools_used' => $m->tools_used])) }},
             })">

            <div class="flex-1 space-y-4 overflow-y-auto p-4" x-ref="scroll">
                <template x-if="messages.length === 0">
                    <p class="text-center text-sm text-gray-400">Bofya moja ya maswali chini, au andika lako mwenyewe.</p>
                </template>

                <template x-for="(m, i) in messages" :key="i">
                    <div :class="m.role === 'user' ? 'flex justify-end' : 'flex justify-start'">
                        <div :class="m.role === 'user' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-900'"
                             class="max-w-[75%] rounded-2xl px-4 py-2 text-sm whitespace-pre-wrap">
                            <span x-text="m.content"></span>
                            <template x-if="m.tools_used && m.tools_used.length">
                                <div class="mt-1 flex flex-wrap gap-1 border-t border-black/10 pt-1 opacity-70">
                                    <template x-for="t in m.tools_used" :key="t">
                                        <span class="rounded bg-black/10 px-1.5 py-0.5 text-[10px]" x-text="t"></span>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>

                <template x-if="loading">
                    <div class="flex justify-start">
                        <div class="max-w-[75%] rounded-2xl bg-gray-100 px-4 py-2 text-sm text-gray-400">…</div>
                    </div>
                </template>

                <template x-if="error">
                    <div class="flex justify-start">
                        <div class="max-w-[75%] rounded-2xl bg-red-50 px-4 py-2 text-sm text-red-700" x-text="error"></div>
                    </div>
                </template>
            </div>

            <div class="flex flex-wrap gap-2 border-t border-gray-200 p-3">
                @foreach ($quickQuestions as $q)
                    <button type="button" @click="ask('{{ $q['id'] }}', '{{ $q['label'] }}')" :disabled="loading"
                            class="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700 hover:bg-gray-200 disabled:opacity-50">
                        {{ $q['label'] }}
                    </button>
                @endforeach
            </div>

            <form @submit.prevent="send" class="flex items-center gap-2 border-t border-gray-200 p-3">
                <input type="text" x-model="draft" :disabled="loading"
                       placeholder="Andika swali lako…"
                       class="flex-1 rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                <x-mbui.button type="submit" variant="primary" x-bind:disabled="loading || !draft.trim()">Tuma</x-mbui.button>
            </form>
        </div>
    </div>

    <script>
        function aiAssistant({ sendUrl, csrf, initialMessages }) {
            return {
                messages: initialMessages || [],
                draft: '',
                loading: false,
                error: null,
                async ask(questionId, label) {
                    await this.submit(label, questionId);
                },
                async send() {
                    const text = this.draft.trim();
                    if (! text) return;
                    this.draft = '';
                    await this.submit(text, null);
                },
                async submit(text, questionId) {
                    if (this.loading) return;

                    this.messages.push({ role: 'user', content: text, tools_used: null });
                    this.loading = true;
                    this.error = null;
                    this.scrollToBottom();

                    try {
                        const res = await fetch(sendUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrf,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({ message: text, question_id: questionId }),
                        });
                        const data = await res.json();

                        if (! res.ok) {
                            this.error = data.error || 'Hitilafu isiyotarajiwa.';
                        } else {
                            this.messages.push({ role: data.role, content: data.content, tools_used: data.tools_used });
                        }
                    } catch (e) {
                        this.error = 'Imeshindikana kuwasiliana na seva.';
                    } finally {
                        this.loading = false;
                        this.scrollToBottom();
                    }
                },
                scrollToBottom() {
                    this.$nextTick(() => {
                        this.$refs.scroll.scrollTop = this.$refs.scroll.scrollHeight;
                    });
                },
            };
        }
    </script>

</x-layouts.admin>
