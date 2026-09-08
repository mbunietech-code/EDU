@props(['conversation', 'payload', 'isAdmin', 'sendUrl', 'fetchUrl', 'otherOnline' => false, 'placeholder' => null])

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('chatThread', (conversationId, viewer, sendUrl, fetchUrl, otherOnline) => ({
            conversationId: conversationId,
            messages: (window.__chatThread && window.__chatThread[conversationId]) || [],
            viewer: viewer,
            draft: '',
            recording: false,
            rec: null,
            sendUrl: sendUrl,
            fetchUrl: fetchUrl,
            otherOnline: otherOnline,
            editingId: null,
            editDraft: '',
            startEdit(m) {
                this.editingId = m.id;
                this.editDraft = m.body;
            },
            cancelEdit() {
                this.editingId = null;
                this.editDraft = '';
            },
            async saveEdit(m) {
                const body = (this.editDraft || '').trim();
                if (!body) return;
                try {
                    const res = await fetch(this.sendUrl + '/' + m.id, {
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8', 'Accept': 'application/json' },
                        body: new URLSearchParams({ _token: '{{ csrf_token() }}', body }).toString(),
                    });
                    const data = await res.json();
                    if (res.ok && data.message) {
                        const i = this.messages.findIndex((x) => x.id === m.id);
                        if (i !== -1) this.messages[i] = data.message;
                    } else if (!res.ok) {
                        alert(data.message || 'Could not edit this message.');
                    }
                } catch (e) {}
                this.cancelEdit();
            },
            async deleteMessage(m) {
                if (!confirm('Delete this message?')) return;
                try {
                    const res = await fetch(this.sendUrl + '/' + m.id, {
                        method: 'DELETE',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8', 'Accept': 'application/json' },
                        body: new URLSearchParams({ _token: '{{ csrf_token() }}' }).toString(),
                    });
                    const data = await res.json();
                    if (res.ok && data.message) {
                        const i = this.messages.findIndex((x) => x.id === m.id);
                        if (i !== -1) this.messages[i] = data.message;
                    }
                } catch (e) {}
            },
            init() {
                this.scrollBottom();
                setInterval(() => this.poll(), 5000);
            },
            scrollBottom() {
                this.$nextTick(() => {
                    const el = this.$refs.scroller;
                    if (el) el.scrollTop = el.scrollHeight;
                });
            },
            async poll() {
                const last = this.messages.length ? this.messages[this.messages.length - 1].id : 0;
                try {
                    const res = await fetch(this.fetchUrl + '?after=' + last, { headers: { 'Accept': 'application/json' } });
                    const data = await res.json();
                    if (typeof data.otherOnline === 'boolean') {
                        this.otherOnline = data.otherOnline;
                    }
                    if (data.messages && data.messages.length) {
                        this.messages = this.messages.concat(data.messages);
                        this.scrollBottom();
                    }
                } catch (e) {}
            },
            async sendText() {
                const body = (this.draft || '').trim();
                if (!body) return;
                this.draft = '';
                const fd = new URLSearchParams();
                fd.append('_token', '{{ csrf_token() }}');
                fd.append('type', 'text');
                fd.append('body', body);
                await this.post(fd, true);
            },
            attachMedia(ev) {
                const f = ev.target.files[0];
                ev.target.value = '';
                if (!f) return;
                this.sendFile(f, f.type.startsWith('video/') ? 'video' : 'image');
            },
            attachAudio(ev) {
                const f = ev.target.files[0];
                ev.target.value = '';
                if (f) this.sendFile(f, 'audio');
            },
            async sendFile(file, type) {
                const fd = new FormData();
                fd.append('_token', '{{ csrf_token() }}');
                fd.append('type', type);
                fd.append('file', file);
                await this.post(fd, false);
            },
            async post(data, urlEncoded) {
                try {
                    const res = await fetch(this.sendUrl, {
                        method: 'POST',
                        headers: urlEncoded ? { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8', 'Accept': 'application/json' } : { 'Accept': 'application/json' },
                        body: urlEncoded ? data.toString() : data,
                    });
                    const result = await res.json();
                    if (result.message) {
                        this.messages.push(result.message);
                        this.scrollBottom();
                    }
                } catch (e) {}
            },
            async toggleRecord() {
                if (this.recording) {
                    this.rec.stop();
                    this.recording = false;
                    return;
                }
                try {
                    const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                    const rec = new MediaRecorder(stream);
                    const chunks = [];
                    rec.ondataavailable = (e) => { if (e.data.size) chunks.push(e.data); };
                    rec.onstop = () => {
                        stream.getTracks().forEach((t) => t.stop());
                        const type = rec.mimeType || 'audio/webm';
                        const ext = (type.split('/')[1] || 'webm').replace(';codecs=.*', '').split(';')[0];
                        const blob = new Blob(chunks, { type });
                        this.sendFile(new File([blob], 'voice.' + ext, { type }), 'audio');
                    };
                    rec.start();
                    this.rec = rec;
                    this.recording = true;
                } catch (e) {
                    alert('Microphone is not available.');
                }
            },
        }));
    });
</script>

<div class="flex min-h-0 flex-1 flex-col">
        <div x-ref="scroller" class="min-h-0 flex-1 space-y-3 overflow-y-auto rounded-lg bg-gray-50 p-3">
        <template x-for="m in messages" :key="m.id">
            <div class="flex flex-col" :class="m.fromAdmin === viewer ? 'items-end' : 'items-start'">
                <div class="max-w-[80%] rounded-2xl px-3 py-2 text-sm"
                    :class="[m.fromAdmin === viewer ? 'rounded-br-sm bg-indigo-600 text-white' : 'rounded-bl-sm border border-gray-200 bg-white text-gray-900', m.deleted ? 'opacity-70' : '']">
                    <template x-if="m.deleted">
                        <p class="italic" :class="m.fromAdmin === viewer ? 'text-indigo-100' : 'text-gray-400'">This message was deleted</p>
                    </template>
                    <template x-if="!m.deleted && editingId === m.id">
                        <div class="min-w-[14rem]">
                            <textarea x-model="editDraft" rows="2" class="w-full rounded-md border-0 p-1.5 text-sm text-gray-900 focus:ring-1 focus:ring-indigo-500" @keydown.enter.prevent="saveEdit(m)"></textarea>
                            <div class="mt-1 flex justify-end gap-2 text-xs">
                                <button type="button" class="opacity-80 hover:opacity-100" :class="m.fromAdmin === viewer ? 'text-white' : 'text-gray-500'" @click="cancelEdit()">Cancel</button>
                                <button type="button" class="font-semibold opacity-90 hover:opacity-100" :class="m.fromAdmin === viewer ? 'text-white' : 'text-indigo-600'" @click="saveEdit(m)">Save</button>
                            </div>
                        </div>
                    </template>
                    <template x-if="!m.deleted && editingId !== m.id">
                        <div>
                            <template x-if="m.type === 'text'">
                                <p class="whitespace-pre-wrap break-words" x-text="m.body"></p>
                            </template>
                            <template x-if="m.type === 'image'">
                                <a :href="m.file" target="_blank" class="block">
                                    <img :src="m.file" :alt="m.filename" class="max-h-64 rounded-lg object-cover">
                                </a>
                            </template>
                            <template x-if="m.type === 'video'">
                                <video :src="m.file" controls :title="m.filename" class="max-h-64 rounded-lg"></video>
                            </template>
                            <template x-if="m.type === 'audio'">
                                <div class="flex items-center gap-2">
                                    <audio :src="m.file" controls class="h-9 w-56" :title="m.filename"></audio>
                                </div>
                            </template>
                        </div>
                    </template>
                    <p class="mt-1 text-right text-[10px] leading-none opacity-60">
                        <span x-show="m.edited && !m.deleted">edited &middot; </span><span x-text="m.time"></span>
                    </p>
                </div>
                <div class="mt-0.5 flex gap-2 px-1 text-[11px] text-gray-400" x-show="m.mine && !m.deleted && editingId !== m.id">
                    <button type="button" class="hover:text-indigo-600" x-show="m.editable" @click="startEdit(m)">Edit</button>
                    <button type="button" class="hover:text-red-600" @click="deleteMessage(m)">Delete</button>
                </div>
            </div>
        </template>
    </div>

    <div class="flex shrink-0 items-end gap-2 border-t border-gray-100 bg-white/90 pb-2 pt-3 backdrop-blur">
        <label class="cursor-pointer rounded-lg p-2 text-gray-500 hover:bg-gray-100 hover:text-indigo-600" title="Attach image / video">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13" />
            </svg>
            <input type="file" accept="image/*,video/*" class="hidden" @change="attachMedia($event)">
        </label>
        <label class="cursor-pointer rounded-lg p-2 text-gray-500 hover:bg-gray-100 hover:text-indigo-600" title="Attach audio">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 18.75a6 6 0 006-6v-1.5m-6 7.5a6 6 0 01-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 01-3-3V4.5a3 3 0 116 0v8.25a3 3 0 01-3 3z" />
            </svg>
            <input type="file" accept="audio/*" class="hidden" @change="attachAudio($event)">
        </label>
        <button type="button" @click="toggleRecord()"
            class="rounded-lg p-2 text-gray-500 hover:bg-gray-100"
            :class="recording ? 'bg-red-100 text-red-600' : 'hover:text-indigo-600'" title="Record voice note">
            <template x-if="!recording">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 18.75a6 6 0 006-6v-1.5m-6 7.5a6 6 0 01-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 01-3-3V4.5a3 3 0 116 0v8.25a3 3 0 01-3 3z" />
                </svg>
            </template>
            <template x-if="recording">
                <span class="text-xs font-semibold text-red-600">Stop</span>
            </template>
        </button>
        <textarea x-model="draft" rows="1" class="mbui-input flex-1 resize-none" placeholder="{{ $placeholder ?? ($isAdmin ? 'Reply to ' . $conversation->user->name . '...' : 'Type a message...') }}" @keydown.enter.prevent="sendText()"></textarea>
        <button type="button" @click="sendText()" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
            Send
        </button>
    </div>
</div>

<script>window.__chatThread = window.__chatThread || {}; window.__chatThread[{{ $conversation->id }}] = @json($payload);</script>