/**
 * Resumable chunked uploader for lesson videos, renditions and recordings.
 *
 * cfg: {
 *   configUrl, initUrl,                      // GET limits / POST {purpose, filename, size} -> {token, chunk_bytes}
 *   chunkUrl, completeUrl, abortUrl,         // contain __TOKEN__
 *   purpose: 'video' | 'rendition' | 'recording',
 *   accept, autoThumbnail, captureMeta,
 *   required?: bool,                         // block the form submit until a file is uploaded
 *   maxBytes?, extensions?,                  // client checks (fetched from configUrl when absent)
 *   existing?: {token, name, size, duration_seconds, width, height}  // finished upload to show again
 * }
 *
 * Markup contract (see resources/views/studio/videos/partials/uploader.blade.php): hidden inputs bound to
 * `token` / `meta.*`, an optional <input type=file name=auto_thumbnail x-ref=autoThumb>, and an unnamed
 * <input type=file x-ref=picker>. Several instances may live on one page (even in one form).
 *
 * While an upload runs the surrounding form's submit buttons are disabled and submitting is blocked.
 * A submit button with data-requires-upload (e.g. "Publish now") is blocked until an upload finished.
 */
const MAX_RETRIES = 3;
const BUSY = ['reading', 'uploading', 'paused', 'completing'];

function csrfToken() {
    return document.querySelector('meta[name=csrf-token]')?.content ?? '';
}

/** Promise wrapper around XHR (upload progress + abort). Resolves {status, data}; rejects only on network failure/abort. */
function send(method, url, body, { onProgress, signal } = {}) {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open(method, url, true);
        xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken());
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        if (body && !(body instanceof FormData)) {
            xhr.setRequestHeader('Content-Type', 'application/json');
            body = JSON.stringify(body);
        }
        if (onProgress && xhr.upload) {
            xhr.upload.onprogress = (e) => { if (e.lengthComputable) onProgress(e.loaded); };
        }
        xhr.onload = () => {
            let data = null;
            try { data = xhr.responseText ? JSON.parse(xhr.responseText) : null; } catch (e) { data = null; }
            resolve({ status: xhr.status, data });
        };
        xhr.onerror = () => reject(Object.assign(new Error('network'), { network: true }));
        xhr.ontimeout = () => reject(Object.assign(new Error('timeout'), { network: true }));
        xhr.onabort = () => reject(Object.assign(new Error('aborted'), { aborted: true }));
        if (signal) {
            if (signal.aborted) { reject(Object.assign(new Error('aborted'), { aborted: true })); return; }
            signal.addEventListener('abort', () => xhr.abort(), { once: true });
        }
        xhr.send(body ?? null);
    });
}

/** A readable message for a failed JSON response. */
function messageFrom(res, fallback) {
    const d = res?.data;
    if (d?.errors) {
        const first = Object.values(d.errors)[0];
        if (Array.isArray(first) && first.length) return first[0];
    }
    if (res?.status === 413) return 'The server refused a piece of the file because it is too large. Ask an administrator to raise the upload limit.';
    if (res?.status === 419) return 'Your session expired. Reload the page and try again.';
    if (res?.status === 403) return d?.message || 'You are not allowed to upload this file.';
    if (res?.status === 429) return 'Too many requests — wait a moment and try again.';
    if (d?.message && res?.status < 500) return d.message;
    return fallback;
}

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

window.learnChunkUploader = (cfg = {}) => ({
    cfg,
    status: 'idle',          // idle | reading | uploading | paused | completing | done | error
    dragging: false,
    file: null,
    fileName: '',
    fileSize: 0,
    token: '',
    uploadToken: null,       // server token while uploading (becomes `token` once complete)
    chunkBytes: 0,
    nextIndex: 0,
    received: 0,
    inFlight: 0,
    percent: 0,
    speed: 0,
    retrying: false,
    canRetry: false,
    error: '',
    blockedMessage: '',
    meta: { duration: null, width: null, height: null },
    thumbUrl: null,
    limits: null,
    _controller: null,
    _pauseRequested: false,
    _speedSamples: [],
    _form: null,
    _onSubmit: null,
    _onUnload: null,

    init() {
        this._form = this.$el.closest('form');
        this.limits = (this.cfg.maxBytes && this.cfg.extensions)
            ? { max_bytes: this.cfg.maxBytes, extensions: this.cfg.extensions }
            : null;

        if (this.cfg.existing && this.cfg.existing.token) {
            const ex = this.cfg.existing;
            this.token = ex.token;
            this.fileName = ex.name || 'Uploaded file';
            this.fileSize = Number(ex.size) || 0;
            this.meta = {
                duration: ex.duration_seconds ? Number(ex.duration_seconds) : null,
                width: ex.width ? Number(ex.width) : null,
                height: ex.height ? Number(ex.height) : null,
            };
            this.percent = 100;
            this.status = 'done';
        }

        if (this._form) {
            this._onSubmit = (e) => this.guardSubmit(e);
            this._form.addEventListener('submit', this._onSubmit, true);
        }
        this._onUnload = (e) => {
            if (BUSY.includes(this.status)) { e.preventDefault(); e.returnValue = ''; }
        };
        window.addEventListener('beforeunload', this._onUnload);

        this.$watch('status', () => this.syncForm());
    },

    destroy() {
        if (this._form && this._onSubmit) this._form.removeEventListener('submit', this._onSubmit, true);
        if (this._onUnload) window.removeEventListener('beforeunload', this._onUnload);
        this.releaseForm();
        if (this.thumbUrl) URL.revokeObjectURL(this.thumbUrl);
    },

    // --- Form integration ----------------------------------------------
    guardSubmit(e) {
        this.blockedMessage = '';
        if (BUSY.includes(this.status)) {
            e.preventDefault();
            e.stopImmediatePropagation();
            this.blockedMessage = 'Please wait for the upload to finish (or cancel it) before saving.';
            return;
        }
        const needsFile = this.cfg.required || (e.submitter && e.submitter.hasAttribute('data-requires-upload'));
        if (needsFile && !this.token) {
            e.preventDefault();
            e.stopImmediatePropagation();
            this.blockedMessage = this.cfg.required
                ? 'Upload the file first.'
                : 'Upload the video before publishing — or save it as a draft for now.';
        }
    },

    syncForm() {
        if (!this._form) return;
        const set = (this._form._learnBusyUploads ??= new Set());
        if (BUSY.includes(this.status)) set.add(this); else set.delete(this);
        const busy = set.size > 0;
        this._form.querySelectorAll('button[type=submit], input[type=submit]').forEach((btn) => {
            if (busy) {
                if (!btn.disabled) { btn.disabled = true; btn.dataset.learnUploadDisabled = '1'; }
            } else if (btn.dataset.learnUploadDisabled) {
                btn.disabled = false;
                delete btn.dataset.learnUploadDisabled;
            }
        });
        if (!busy) this.blockedMessage = '';
    },

    releaseForm() {
        if (!this._form || !this._form._learnBusyUploads) return;
        this._form._learnBusyUploads.delete(this);
        this.syncForm();
    },

    // --- Choosing a file -----------------------------------------------
    async loadLimits() {
        if (this.limits) return this.limits;
        const res = await fetch(this.cfg.configUrl, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken() },
            credentials: 'same-origin',
        });
        if (!res.ok) throw new Error('config');
        const data = await res.json();
        this.chunkBytes = data.chunk_bytes || 0;
        const p = data.purposes?.[this.cfg.purpose] ?? {};
        this.limits = { max_bytes: p.max_bytes || 0, extensions: p.extensions || [] };
        return this.limits;
    },

    async choose(file) {
        if (!file) return;
        if (BUSY.includes(this.status)) return;
        this.reset(true);
        this.file = file;
        this.fileName = file.name;
        this.fileSize = file.size;
        this.status = 'reading';

        let limits;
        try {
            limits = await this.loadLimits();
        } catch (e) {
            return this.fail('Could not load the upload settings. Check your connection and try again.', true);
        }

        const ext = (file.name.split('.').pop() || '').toLowerCase();
        if (limits.extensions?.length && !limits.extensions.includes(ext)) {
            return this.fail(`This file type is not supported. Use ${limits.extensions.map((x) => x.toUpperCase()).join(', ')}.`, false);
        }
        if (file.size <= 0) {
            return this.fail('This file is empty.', false);
        }
        if (limits.max_bytes && file.size > limits.max_bytes) {
            return this.fail(`This file is ${this.sizeLabel(file.size)}; the maximum is ${this.sizeLabel(limits.max_bytes)}.`, false);
        }

        if (this.cfg.captureMeta || this.cfg.autoThumbnail) {
            this.readMetadata(file); // runs alongside the upload; failures are not fatal
        }

        this.start();
    },

    /** Duration / size from a hidden <video>; optional frame at min(2s, 10%) → JPEG → auto_thumbnail. */
    readMetadata(file) {
        let url;
        try { url = URL.createObjectURL(file); } catch (e) { return; }
        const video = document.createElement('video');
        video.preload = 'metadata';
        video.muted = true;
        video.playsInline = true;
        let finished = false;
        const done = () => {
            if (finished) return;
            finished = true;
            clearTimeout(timer);
            video.removeAttribute('src');
            try { video.load(); } catch (e) { /* ignore */ }
            URL.revokeObjectURL(url);
        };
        const timer = setTimeout(done, 15000);

        video.addEventListener('loadedmetadata', () => {
            const d = Number.isFinite(video.duration) ? Math.round(video.duration) : null;
            this.meta = {
                duration: d && d > 0 ? d : null,
                width: video.videoWidth || null,
                height: video.videoHeight || null,
            };
            if (!this.cfg.autoThumbnail || !this.$refs.autoThumb || !video.videoWidth) { done(); return; }
            const at = Math.min(2, (video.duration || 0) * 0.1);
            try { video.currentTime = at > 0 ? at : 0.1; } catch (e) { done(); }
        }, { once: true });

        video.addEventListener('seeked', () => {
            try {
                const scale = Math.min(1, 1280 / video.videoWidth);
                const canvas = document.createElement('canvas');
                canvas.width = Math.round(video.videoWidth * scale);
                canvas.height = Math.round(video.videoHeight * scale);
                canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
                canvas.toBlob((blob) => {
                    if (blob && this.$refs.autoThumb && this.file === file) {
                        try {
                            const dt = new DataTransfer();
                            dt.items.add(new File([blob], 'thumbnail.jpg', { type: 'image/jpeg' }));
                            this.$refs.autoThumb.files = dt.files;
                            if (this.thumbUrl) URL.revokeObjectURL(this.thumbUrl);
                            this.thumbUrl = URL.createObjectURL(blob);
                        } catch (e) { /* DataTransfer unsupported: no auto thumbnail */ }
                    }
                    done();
                }, 'image/jpeg', 0.85);
            } catch (e) {
                done();
            }
        }, { once: true });

        video.addEventListener('error', done, { once: true });
        video.src = url;
    },

    // --- Upload loop ---------------------------------------------------
    async start() {
        this.error = '';
        this.canRetry = false;
        this.status = 'uploading';
        this._controller = new AbortController();

        try {
            if (!this.uploadToken) {
                const res = await send('POST', this.cfg.initUrl, {
                    purpose: this.cfg.purpose,
                    filename: this.file.name,
                    size: this.file.size,
                }, { signal: this._controller.signal });
                if (res.status < 200 || res.status >= 300 || !res.data?.token) {
                    return this.fail(messageFrom(res, 'The upload could not be started. Please try again.'), res.status >= 500 || res.status === 429);
                }
                this.uploadToken = res.data.token;
                this.chunkBytes = res.data.chunk_bytes || this.chunkBytes || 5 * 1024 * 1024;
                this.nextIndex = 0;
                this.received = 0;
            }
            await this.pump();
        } catch (e) {
            if (e.aborted) return; // cancel() / pause() handled it
            this.fail('The connection was lost. Check your network and try again.', true);
        }
    },

    async pump() {
        while (this.received < this.file.size) {
            if (this._pauseRequested) {
                this._pauseRequested = false;
                this.status = 'paused';
                this.speed = 0;
                return;
            }

            const from = this.nextIndex * this.chunkBytes;
            const blob = this.file.slice(from, Math.min(from + this.chunkBytes, this.file.size));
            const res = await this.sendChunk(this.nextIndex, blob);

            if (res.status === 422 && res.data?.errors?.index) {
                // Out of order (e.g. a retried request already landed): ask the server where it is by resending
                // chunk 0, which it acknowledges without writing.
                const probe = await this.sendChunk(0, this.file.slice(0, Math.min(this.chunkBytes, this.file.size)));
                if (probe.status === 200 && probe.data) {
                    this.nextIndex = probe.data.next_index;
                    this.received = probe.data.received_bytes;
                    continue;
                }
                return this.fail(messageFrom(res, 'The upload got out of step. Please start again.'), false);
            }
            if (res.status !== 200 || !res.data) {
                return this.fail(messageFrom(res, 'A piece of the file could not be uploaded.'), res.status >= 500 || res.status === 0 || res.status === 429);
            }

            this.nextIndex = res.data.next_index;
            this.received = res.data.received_bytes;
            this.inFlight = 0;
            this.updateProgress();
        }

        this.status = 'completing';
        const res = await this.withRetries(() => send('POST', this.url(this.cfg.completeUrl), {}, { signal: this._controller.signal }));
        if (res.status !== 200 || !res.data?.token) {
            const retryable = res.status >= 500 || res.status === 0;
            if (!retryable) this.uploadToken = null; // the server discarded it (wrong content) or it expired
            return this.fail(messageFrom(res, 'The file could not be verified.'), retryable);
        }

        this.token = res.data.token;
        this.fileSize = res.data.size || this.fileSize;
        this.percent = 100;
        this.speed = 0;
        this.status = 'done';
        this.$dispatch('learn-upload-complete', { token: this.token, purpose: this.cfg.purpose, name: this.fileName, meta: this.meta });
    },

    sendChunk(index, blob) {
        return this.withRetries(() => {
            const fd = new FormData();
            fd.append('index', String(index));
            fd.append('chunk', blob, 'chunk.bin');
            return send('POST', this.url(this.cfg.chunkUrl), fd, {
                signal: this._controller.signal,
                onProgress: (loaded) => { this.inFlight = loaded; this.updateProgress(); },
            });
        });
    },

    /** Up to 3 retries with exponential backoff on network errors, 5xx and 429. Aborts propagate. */
    async withRetries(fn) {
        for (let attempt = 0; ; attempt++) {
            let res;
            try {
                res = await fn();
            } catch (e) {
                if (e.aborted) throw e;
                res = { status: 0, data: null };
            }
            const retryable = res.status === 0 || res.status >= 500 || res.status === 429;
            if (!retryable || attempt >= MAX_RETRIES) {
                this.retrying = false;
                return res;
            }
            this.retrying = true;
            this.inFlight = 0;
            await sleep(1000 * 2 ** attempt);
            if (this._controller?.signal.aborted) throw Object.assign(new Error('aborted'), { aborted: true });
        }
    },

    updateProgress() {
        const done = Math.min(this.file.size, this.received + this.inFlight);
        this.percent = this.file.size ? Math.min(99, Math.floor((done / this.file.size) * 100)) : 0;

        const now = performance.now();
        this._speedSamples.push([now, done]);
        while (this._speedSamples.length > 2 && now - this._speedSamples[0][0] > 8000) this._speedSamples.shift();
        const [t0, b0] = this._speedSamples[0];
        if (now - t0 > 500) this.speed = Math.max(0, ((done - b0) / (now - t0)) * 1000);
    },

    // --- Controls ------------------------------------------------------
    pause() {
        if (this.status !== 'uploading') return;
        this._pauseRequested = true;
        // Stop the chunk in flight right away; it is resent on resume (the server ignores partial requests).
        this._controller?.abort();
        this.status = 'paused';
        this.speed = 0;
        this.inFlight = 0;
        this.retrying = false;
        this._speedSamples = [];
    },

    resume() {
        if (this.status !== 'paused') return;
        this._pauseRequested = false;
        this.start();
    },

    retry() {
        if (!this.file) { this.reset(true); return; }
        this._speedSamples = [];
        this.start();
    },

    async cancel() {
        this._controller?.abort();
        await this.discardServerUpload();
        this.reset(false);
    },

    /** Back to the drop zone; `discard` also deletes the server copy of an unsaved upload. */
    reset(discard = false) {
        if (discard && (this.uploadToken || this.token)) this.discardServerUpload();
        this._controller?.abort();
        this._controller = null;
        this._pauseRequested = false;
        this.file = null;
        this.fileName = '';
        this.fileSize = 0;
        this.token = '';
        this.uploadToken = null;
        this.nextIndex = 0;
        this.received = 0;
        this.inFlight = 0;
        this.percent = 0;
        this.speed = 0;
        this.retrying = false;
        this.canRetry = false;
        this.error = '';
        this.blockedMessage = '';
        this.meta = { duration: null, width: null, height: null };
        this._speedSamples = [];
        if (this.thumbUrl) URL.revokeObjectURL(this.thumbUrl);
        this.thumbUrl = null;
        if (this.$refs.autoThumb) {
            try { this.$refs.autoThumb.value = ''; } catch (e) { /* ignore */ }
        }
        this.status = 'idle';
    },

    async discardServerUpload() {
        const token = this.uploadToken || this.token;
        if (!token) return;
        try {
            await send('DELETE', this.url(this.cfg.abortUrl, token));
        } catch (e) { /* the hourly cleanup removes it anyway */ }
    },

    fail(message, retryable) {
        this.error = message;
        this.canRetry = !!retryable && !!this.file;
        this.retrying = false;
        this.speed = 0;
        this.status = 'error';
    },

    // --- Helpers -------------------------------------------------------
    url(template, token = null) {
        return template.replace('__TOKEN__', encodeURIComponent(token ?? this.uploadToken ?? this.token));
    },

    sizeLabel(bytes) {
        bytes = Number(bytes) || 0;
        const units = ['B', 'KB', 'MB', 'GB', 'TB'];
        let i = 0;
        while (bytes >= 1024 && i < units.length - 1) { bytes /= 1024; i++; }
        return `${bytes.toFixed(i === 0 ? 0 : 1)} ${units[i]}`;
    },

    speedLabel() {
        return `${(this.speed / 1048576).toFixed(1)} MB/s`;
    },

    etaLabel() {
        if (!this.speed) return '…';
        const left = Math.max(0, this.file.size - this.received - this.inFlight) / this.speed;
        return this.durationLabel(left);
    },

    durationLabel(seconds) {
        seconds = Math.max(0, Math.round(Number(seconds) || 0));
        const h = Math.floor(seconds / 3600);
        const m = Math.floor((seconds % 3600) / 60);
        const s = seconds % 60;
        return h > 0
            ? `${h}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`
            : `${m}:${String(s).padStart(2, '0')}`;
    },
});
