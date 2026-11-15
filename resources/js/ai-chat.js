/*
 * Admin AI Assistant (DeepSeek) chat page. Registered before Alpine boots
 * (see app.js). Markdown + sanitizer are a lazy chunk, only downloaded on
 * this page. Replies stream by polling: send() stores the message and an
 * empty reply, run() generates it on the server while we poll the reply
 * every ~600 ms and redraw the text as it grows.
 */
import Alpine from 'alpinejs';

let mdLib = null;
async function markdownLib() {
    if (!mdLib) {
        const [{ Marked }, purify] = await Promise.all([import('marked'), import('dompurify')]);
        const DOMPurify = purify.default || purify;
        mdLib = { Marked, DOMPurify };
    }
    return mdLib;
}

const escapeHtml = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

let svgSeq = 0;

function buildRenderer(Marked, DOMPurify, streaming) {
    const marked = new Marked({ gfm: true, breaks: true });
    marked.use({
        renderer: {
            code(token) {
                const text = typeof token === 'object' ? token.text : token;
                const lang = String((typeof token === 'object' ? token.lang : arguments[1]) || '').trim().toLowerCase();
                const isSvg = lang === 'svg' || lang === 'xml' && /^\s*<svg[\s>]/i.test(text) || (!lang && /^\s*<svg[\s>]/i.test(text));
                if (isSvg) {
                    const done = /<\/svg>\s*$/i.test(text.trim());
                    if (!done) {
                        return `<div class="ai-design ai-design-pending"><span class="ai-spinner"></span> ${streaming ? 'Designing...' : 'The design was cut off. Ask again or say "continue".'}</div>`;
                    }
                    // Ids inside each design are made unique so two designs on the page never share a gradient.
                    const prefix = 'd' + (++svgSeq) + '-';
                    const scoped = text
                        .replace(/\sid="([^"]+)"/g, (m, id) => ` id="${prefix}${id}"`)
                        .replace(/url\(\s*#([^)\s]+)\s*\)/g, (m, id) => `url(#${prefix}${id})`);
                    const clean = DOMPurify.sanitize(scoped, { USE_PROFILES: { svg: true, svgFilters: true }, FORBID_TAGS: ['script', 'foreignObject', 'image', 'use'], FORBID_ATTR: ['href', 'xlink:href'] });
                    const id = 'ai-svg-' + svgSeq;
                    const size = (clean.match(/\bwidth="(\d+)/) || [])[1] && (clean.match(/\bheight="(\d+)/) || [])[1]
                        ? `${clean.match(/\bwidth="(\d+)/)[1]} x ${clean.match(/\bheight="(\d+)/)[1]}` : '';
                    return `<figure class="ai-design" id="${id}">
                        <div class="ai-design-canvas">${clean}</div>
                        <figcaption class="ai-design-bar">
                            <span class="ai-design-size">${size}</span>
                            <span class="ai-design-actions">
                                <button type="button" data-ai-action="png" data-target="${id}">Download PNG</button>
                                <button type="button" data-ai-action="svg" data-target="${id}">SVG</button>
                                <button type="button" data-ai-action="copy-svg" data-target="${id}">Copy code</button>
                            </span>
                        </figcaption>
                    </figure>`;
                }
                const label = lang ? escapeHtml(lang) : 'code';
                return `<div class="ai-code"><div class="ai-code-bar"><span>${label}</span><button type="button" data-ai-action="copy-code">Copy</button></div><pre><code>${escapeHtml(text)}</code></pre></div>`;
            },
            link(token) {
                const href = token.href || '';
                const text = token.text || href;
                const external = /^https?:\/\//i.test(href) && !href.startsWith(window.location.origin);
                return `<a href="${escapeHtml(href)}"${external ? ' target="_blank" rel="noopener noreferrer"' : ''}>${escapeHtml(text)}</a>`;
            },
        },
    });
    return (text) => DOMPurify.sanitize(marked.parse(text || ''), {
        ADD_TAGS: ['svg', 'figure', 'figcaption'],
        ADD_ATTR: ['data-ai-action', 'data-target', 'target', 'viewBox', 'xmlns'],
        USE_PROFILES: { html: true, svg: true, svgFilters: true },
        FORBID_TAGS: ['script', 'style', 'iframe', 'form', 'input', 'foreignObject'],
    });
}

function plainText(markdown) {
    return String(markdown || '')
        .replace(/```[\s\S]*?```/g, ' ')
        .replace(/`([^`]+)`/g, '$1')
        .replace(/!\[[^\]]*\]\([^)]*\)/g, ' ')
        .replace(/\[([^\]]+)\]\([^)]*\)/g, '$1')
        .replace(/[#*_>|~-]+/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
}

function download(blob, name) {
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = name;
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 2000);
}

function svgSource(figure) {
    const svg = figure.querySelector('svg');
    if (!svg) return null;
    const clone = svg.cloneNode(true);
    clone.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
    return new XMLSerializer().serializeToString(clone);
}

function svgSize(figure) {
    const svg = figure.querySelector('svg');
    const vb = (svg.getAttribute('viewBox') || '').split(/[\s,]+/).map(Number);
    const w = parseFloat(svg.getAttribute('width')) || vb[2] || 1080;
    const h = parseFloat(svg.getAttribute('height')) || vb[3] || 1080;
    return { w, h };
}

async function svgToPng(figure) {
    const source = svgSource(figure);
    const { w, h } = svgSize(figure);
    const img = new Image();
    const url = URL.createObjectURL(new Blob([source], { type: 'image/svg+xml;charset=utf-8' }));
    await new Promise((resolve, reject) => { img.onload = resolve; img.onerror = reject; img.src = url; });
    const scale = Math.max(w, h) < 1400 ? 2 : 1;
    const canvas = document.createElement('canvas');
    canvas.width = Math.round(w * scale);
    canvas.height = Math.round(h * scale);
    const ctx = canvas.getContext('2d');
    ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
    URL.revokeObjectURL(url);
    return await new Promise((resolve) => canvas.toBlob(resolve, 'image/png'));
}

function slug(text) {
    return (String(text || 'design').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 40)) || 'design';
}

function groupLabel(iso) {
    if (!iso) return 'Older';
    const d = new Date(iso);
    const today = new Date(); today.setHours(0, 0, 0, 0);
    const diff = (today - new Date(d.getFullYear(), d.getMonth(), d.getDate())) / 86400000;
    if (diff <= 0) return 'Today';
    if (diff <= 1) return 'Yesterday';
    if (diff <= 7) return 'Previous 7 days';
    if (diff <= 30) return 'Previous 30 days';
    return 'Older';
}

Alpine.data('aiChat', (cfg) => ({
    conversations: cfg.conversations || [],
    activeId: cfg.activeId,
    title: cfg.title,
    messages: cfg.messages || [],
    draft: cfg.ask || '',
    busy: false,
    error: '',
    search: '',
    sidebar: false,
    createOpen: false,
    speakingId: null,
    render: null,
    renderStreaming: null,
    stickToBottom: true,
    pollTimer: null,

    async init() {
        try {
            const { Marked, DOMPurify } = await markdownLib();
            this.render = buildRenderer(Marked, DOMPurify, false);
            this.renderStreaming = buildRenderer(Marked, DOMPurify, true);
        } catch (e) {
            this.render = this.renderStreaming = (t) => escapeHtml(t).replace(/\n/g, '<br>');
        }
        this.$nextTick(() => { this.scrollDown(true); this.autosize(); this.$refs.input?.focus(); });

        // A reply still being written when the page opened: keep following it.
        const open = this.messages.find((m) => m.status === 'streaming');
        if (open) this.follow(open, cfg.pollBase + '/' + open.id);
    },

    // ---------------------------------------------------------------- sidebar

    get groups() {
        const q = this.search.trim().toLowerCase();
        const list = this.conversations.filter((c) => !q || (c.title || '').toLowerCase().includes(q));
        const order = ['Today', 'Yesterday', 'Previous 7 days', 'Previous 30 days', 'Older'];
        const out = {};
        list.forEach((c) => { const g = groupLabel(c.updated_at); (out[g] = out[g] || []).push(c); });
        return order.filter((g) => out[g]).map((g) => ({ label: g, items: out[g] }));
    },

    async renameConversation(c) {
        const title = window.prompt('Rename chat', c.title || '');
        if (!title || !title.trim()) return;
        const res = await this.request(cfg.base + '/' + c.id, 'PATCH', { title: title.trim() });
        if (res.ok) {
            c.title = res.data.title;
            if (c.id === this.activeId) this.title = c.title;
        }
    },

    async deleteConversation(c) {
        if (!window.confirm('Delete this chat? This cannot be undone.')) return;
        const res = await this.request(cfg.base + '/' + c.id, 'DELETE');
        if (!res.ok) return;
        this.conversations = this.conversations.filter((x) => x.id !== c.id);
        if (c.id === this.activeId) window.location.href = cfg.indexUrl;
    },

    // ---------------------------------------------------------------- messages

    html(m) {
        if (!this.render) return escapeHtml(m.content || '');
        return (m.status === 'streaming' ? this.renderStreaming : this.render)(m.content || '');
    },

    get lastAssistantId() {
        const last = this.messages[this.messages.length - 1];
        return last && last.role === 'assistant' ? last.id : null;
    },

    get streaming() {
        return this.messages.some((m) => m.status === 'streaming');
    },

    useSuggestion(text, send = true) {
        this.draft = text;
        this.createOpen = false;
        this.$nextTick(() => {
            this.autosize();
            if (send) this.send();
            else { this.$refs.input.focus(); this.$refs.input.setSelectionRange(this.draft.length, this.draft.length); }
        });
    },

    onKey(e) {
        if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) {
            e.preventDefault();
            this.send();
        }
    },

    autosize() {
        const el = this.$refs.input;
        if (!el) return;
        el.style.height = 'auto';
        el.style.height = Math.min(el.scrollHeight, 240) + 'px';
    },

    async send() {
        const text = this.draft.trim();
        if (!text || this.busy || this.streaming) return;
        this.error = '';
        this.busy = true;
        this.draft = '';
        this.$nextTick(() => this.autosize());

        const temp = { id: 'tmp-' + Date.now(), role: 'user', content: text, status: 'done' };
        this.messages.push(temp);
        this.scrollDown(true);

        const res = await this.request(cfg.sendUrl, 'POST', { message: text, wait: false });
        this.busy = false;
        if (!res.ok) {
            this.messages = this.messages.filter((m) => m !== temp);
            this.draft = text;
            this.error = res.data.message || 'Could not send. Check your connection and try again.';
            return;
        }

        Object.assign(temp, res.data.user_message);
        const reply = res.data.reply;
        this.messages.push(reply);
        this.touchConversation(res.data.conversation_title);
        this.generate(reply, res.data.run_url, res.data.poll_url);
    },

    async regenerate() {
        if (this.busy || this.streaming) return;
        this.busy = true;
        const res = await this.request(cfg.regenerateUrl, 'POST');
        this.busy = false;
        if (!res.ok) { this.error = res.data.message || 'Could not answer again.'; return; }
        const last = this.messages[this.messages.length - 1];
        if (last && last.role === 'assistant') this.messages.pop();
        this.messages.push(res.data.reply);
        this.generate(res.data.reply, res.data.run_url, res.data.poll_url);
    },

    generate(reply, runUrl, pollUrl) {
        const target = this.messages.find((m) => m.id === reply.id);
        // The long request; its answer is the finished reply.
        this.request(runUrl, 'POST').then((res) => {
            if (res.ok && res.data && res.data.id) this.update(target, res.data);
        });
        this.follow(target, pollUrl);
    },

    follow(target, pollUrl) {
        clearTimeout(this.pollTimer);
        const tick = async () => {
            if (!target || target.status !== 'streaming') return;
            const res = await this.request(pollUrl, 'GET');
            if (res.ok && res.data && res.data.id) this.update(target, res.data);
            if (target.status === 'streaming') this.pollTimer = setTimeout(tick, res.ok ? 600 : 2000);
        };
        this.pollTimer = setTimeout(tick, 400);
    },

    update(target, data) {
        if (!target) return;
        // Never go back to a shorter text (an older poll arriving late).
        if (target.status !== 'streaming' && data.status === 'streaming') return;
        if (data.status === 'streaming' && (data.content || '').length < (target.content || '').length) return;
        Object.assign(target, data);
        this.scrollDown();
    },

    async stop() {
        const m = this.messages.find((x) => x.status === 'streaming');
        if (!m) return;
        const res = await this.request(cfg.messageBase + '/' + m.id + '/stop', 'POST');
        if (res.ok) this.update(m, res.data);
    },

    touchConversation(title) {
        const c = this.conversations.find((x) => x.id === this.activeId);
        const now = new Date().toISOString();
        if (c) { c.title = title; c.updated_at = now; }
        else this.conversations.unshift({ id: this.activeId, title, updated_at: now, url: window.location.href });
        this.title = title;
    },

    // ---------------------------------------------------------------- actions

    async copy(text, btn) {
        try {
            await navigator.clipboard.writeText(text);
            if (btn) { const old = btn.textContent; btn.textContent = 'Copied'; setTimeout(() => (btn.textContent = old), 1200); }
        } catch (e) {}
    },

    speak(m) {
        if (!('speechSynthesis' in window)) { this.error = 'This browser cannot read text aloud.'; return; }
        window.speechSynthesis.cancel();
        if (this.speakingId === m.id) { this.speakingId = null; return; }
        const text = plainText(m.content);
        if (!text) return;
        const u = new SpeechSynthesisUtterance(text);
        const swahili = /\b(na|ya|wa|kwa|ni|za|hii|kuna|habari|asante|tafadhali|mteja|malipo)\b/gi;
        const looksSwahili = (text.match(swahili) || []).length > text.split(' ').length * 0.08;
        const voices = window.speechSynthesis.getVoices();
        const voice = looksSwahili ? voices.find((v) => /^sw/i.test(v.lang)) : voices.find((v) => /^en/i.test(v.lang));
        if (voice) u.voice = voice;
        u.lang = voice ? voice.lang : (looksSwahili ? 'sw-TZ' : 'en-US');
        u.rate = 1;
        u.onend = u.onerror = () => { this.speakingId = null; };
        this.speakingId = m.id;
        window.speechSynthesis.speak(u);
    },

    async onContentClick(e) {
        const btn = e.target.closest('[data-ai-action]');
        if (!btn) return;
        const action = btn.dataset.aiAction;
        if (action === 'copy-code') {
            this.copy(btn.closest('.ai-code').querySelector('code').textContent, btn);
            return;
        }
        const figure = document.getElementById(btn.dataset.target);
        if (!figure) return;
        const name = slug(this.title) + '-' + Date.now().toString().slice(-5);
        if (action === 'svg') download(new Blob([svgSource(figure)], { type: 'image/svg+xml' }), name + '.svg');
        if (action === 'copy-svg') this.copy(svgSource(figure), btn);
        if (action === 'png') {
            const old = btn.textContent;
            btn.textContent = 'Preparing...';
            try { download(await svgToPng(figure), name + '.png'); }
            catch (err) { this.error = 'Could not make the PNG. Try the SVG download.'; }
            btn.textContent = old;
        }
    },

    // ---------------------------------------------------------------- helpers

    onScroll() {
        const el = this.$refs.scroll;
        this.stickToBottom = el.scrollHeight - el.scrollTop - el.clientHeight < 120;
    },

    scrollDown(force = false) {
        this.$nextTick(() => {
            const el = this.$refs.scroll;
            if (el && (force || this.stickToBottom)) el.scrollTop = el.scrollHeight;
        });
    },

    async request(url, method, body) {
        try {
            const res = await fetch(url, {
                method,
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: body ? JSON.stringify(body) : undefined,
            });
            let data = {};
            try { data = await res.json(); } catch (e) {}
            return { ok: res.ok, status: res.status, data };
        } catch (e) {
            return { ok: false, status: 0, data: {} };
        }
    },
}));
