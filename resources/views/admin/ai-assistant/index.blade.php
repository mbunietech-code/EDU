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
        <div class="mbui-card overflow-hidden lg:h-[70vh]">
            <div class="border-b border-gray-200 px-4 py-3">
                <h2 class="text-sm font-semibold text-gray-900">Historia</h2>
            </div>
            <div class="divide-y divide-gray-100 overflow-y-auto lg:max-h-[calc(70vh-2.75rem)]">
                @forelse ($conversations as $conv)
                    <a href="{{ route('admin.ai-assistant.show', $conv) }}"
                       class="block px-4 py-3 text-sm hover:bg-gray-50 {{ $conv->id === $active->id ? 'bg-indigo-50' : '' }}">
                        <p class="truncate font-medium text-gray-900">{{ $conv->displayTitle() }}</p>
                        <p class="mt-0.5 text-xs text-gray-400">{{ $conv->updated_at->diffForHumans() }}</p>
                    </a>
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
