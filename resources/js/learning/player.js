/**
 * Lesson player (learn.videos.show).
 *
 * Usage: <div x-data="learnVideoPlayer(@js($config))"> … <video x-ref="video"> … </div>
 * cfg: { progressUrl, completeUrl, sources: [{ id, label, url, height, type }], resumeAt, completed,
 *        durationHint, nextUrl, nextTitle?, percent?, csrf }
 *
 * - playback speed menu (0.5–2x, remembered per browser)
 * - quality switching that keeps the time and play state
 * - resume prompt ("Continue from 12:34" / "Start over")
 * - progress beacons: start (once per page load), tick every 15 s with the watched delta,
 *   pause / seek / ended, and navigator.sendBeacon (FormData + _token) on pagehide
 * - Mark as completed / not completed, restart, next lesson + "Up next" on ended
 * - loading spinner (loadstart / waiting) and an error overlay with Retry
 * - keyboard: space / k play-pause, ← / → 5 s, f fullscreen (ignored while typing)
 */
const SPEEDS = [0.5, 0.75, 1, 1.25, 1.5, 1.75, 2];
const TICK_MS = 15000;
const MAX_SECONDS = 86400;
const MAX_WATCHED = 600;
const SPEED_KEY = 'learn.player.speed';
const QUALITY_KEY = 'learn.player.quality';

function storageGet(key) {
    try {
        return window.localStorage.getItem(key);
    } catch (e) {
        return null;
    }
}

function storageSet(key, value) {
    try {
        window.localStorage.setItem(key, value);
    } catch (e) {
        // Private mode / blocked storage: preferences simply are not remembered.
    }
}

function formatTime(total) {
    const s = Math.max(0, Math.floor(Number(total) || 0));
    const h = Math.floor(s / 3600);
    const m = Math.floor((s % 3600) / 60);
    const sec = String(s % 60).padStart(2, '0');

    return h > 0 ? `${h}:${String(m).padStart(2, '0')}:${sec}` : `${m}:${sec}`;
}

function isTypingTarget(el) {
    if (!el || el === document.body) return false;
    if (el.isContentEditable) return true;

    return ['INPUT', 'TEXTAREA', 'SELECT'].includes(el.tagName);
}

window.learnVideoPlayer = (cfg = {}) => ({
    cfg,
    sources: Array.isArray(cfg.sources) ? cfg.sources : [],
    speeds: SPEEDS,
    currentSource: null,
    speed: 1,
    speedOpen: false,
    qualityOpen: false,

    loading: true,
    errorMessage: null,
    playing: false,
    ended: false,
    started: false,
    switching: false,

    completed: Boolean(cfg.completed),
    percent: Number(cfg.percent) || 0,
    resumeAt: Math.max(0, Math.floor(Number(cfg.resumeAt) || 0)),
    resumePrompt: false,
    completing: false,
    notice: null,
    noticeError: false,

    // Non-reactive bookkeeping lives in a plain closure object (see init).
    _s: null,

    init() {
        const video = this.$refs.video;
        if (!video) return;

        const state = {
            lastTime: 0,
            watched: 0,
            lastSentPosition: -1,
            tickTimer: null,
            seekTimer: null,
            noticeTimer: null,
            ignoreSeek: false,
            handlers: [],
        };
        this._s = state;

        // Preferences.
        const savedSpeed = parseFloat(storageGet(SPEED_KEY));
        this.speed = SPEEDS.includes(savedSpeed) ? savedSpeed : 1;

        const savedQuality = storageGet(QUALITY_KEY);
        const preferred = this.sources.find((s) => String(s.id) === savedQuality) || this.sources[0] || null;
        this.currentSource = preferred ? preferred.id : null;
        if (preferred && video.currentSrc !== preferred.url && preferred !== this.sources[0]) {
            video.src = preferred.url;
            video.load();
        }

        this.resumePrompt = this.resumeAt > 0 && !this.completed;

        const on = (target, event, handler, options) => {
            target.addEventListener(event, handler, options);
            state.handlers.push(() => target.removeEventListener(event, handler, options));
        };

        on(video, 'loadstart', () => {
            this.loading = true;
            this.errorMessage = null;
        });
        on(video, 'loadedmetadata', () => {
            this.loading = false;
            video.playbackRate = this.speed;
            const d = this.duration();
            if (this.resumePrompt && d && this.resumeAt >= d - 5) this.resumePrompt = false;
        });
        on(video, 'loadeddata', () => { this.loading = false; });
        on(video, 'canplay', () => { this.loading = false; });
        on(video, 'waiting', () => { this.loading = true; });
        on(video, 'playing', () => {
            this.loading = false;
            this.playing = true;
            this.ended = false;
            this.resumePrompt = false;
        });
        on(video, 'play', () => {
            this.ended = false;
            this.resumePrompt = false;
            state.lastTime = video.currentTime;
            if (!this.started) {
                this.started = true;
                this.send('start');
            }
            this.startTicking();
        });
        on(video, 'pause', () => {
            this.playing = false;
            this.stopTicking();
            // "ended" reports the final position itself; a quality switch is not a real pause.
            if (!video.ended && !this.switching) this.send('pause');
        });
        on(video, 'timeupdate', () => {
            const t = video.currentTime;
            const delta = t - state.lastTime;
            // Only count continuous playback; jumps (seeks) and rewinds add nothing.
            if (!video.paused && delta > 0 && delta < 3) state.watched += delta;
            state.lastTime = t;
        });
        on(video, 'seeking', () => { state.lastTime = video.currentTime; });
        on(video, 'seeked', () => {
            state.lastTime = video.currentTime;
            if (state.ignoreSeek || this.switching) {
                state.ignoreSeek = false;
                return;
            }
            clearTimeout(state.seekTimer);
            state.seekTimer = setTimeout(() => this.send('seek'), 800); // scrubbing fires many seeks
        });
        on(video, 'ended', () => {
            this.playing = false;
            this.ended = true;
            this.stopTicking();
            this.send('ended');
            this.completed = true;
        });
        on(video, 'ratechange', () => {
            if (SPEEDS.includes(video.playbackRate)) this.speed = video.playbackRate;
        });
        on(video, 'error', () => {
            this.loading = false;
            this.playing = false;
            this.stopTicking();
            this.errorMessage = this.describeError(video.error);
        });

        on(window, 'pagehide', () => this.flush());
        on(document, 'visibilitychange', () => {
            if (document.visibilityState === 'hidden') this.flush();
        });
        on(window, 'keydown', (e) => this.onKey(e));
    },

    destroy() {
        if (!this._s) return;
        this.stopTicking();
        clearTimeout(this._s.seekTimer);
        clearTimeout(this._s.noticeTimer);
        this._s.handlers.forEach((off) => off());
        this._s.handlers = [];
    },

    // --- Helpers ---------------------------------------------------------
    video() {
        return this.$refs.video;
    },

    duration() {
        const d = this.video()?.duration;
        if (Number.isFinite(d) && d >= 1) return Math.min(MAX_SECONDS, Math.round(d));
        const hint = Number(this.cfg.durationHint);

        return Number.isFinite(hint) && hint >= 1 ? Math.min(MAX_SECONDS, Math.round(hint)) : null;
    },

    formatTime,

    get resumeLabel() {
        return formatTime(this.resumeAt);
    },

    get currentLabel() {
        const s = this.sources.find((x) => x.id === this.currentSource);

        return s ? s.label : 'Auto';
    },

    describeError(err) {
        switch (err?.code) {
            case 1: return 'Playback was interrupted.';
            case 2: return 'A network problem stopped the video from loading. Check your connection and try again.';
            case 3: return 'Your browser could not decode this video.';
            case 4: return 'This video could not be loaded. It may be unavailable, or in a format your browser cannot play.';
            default: return 'Something went wrong while playing this video.';
        }
    },

    flash(message, isError = false) {
        this.notice = message;
        this.noticeError = isError;
        clearTimeout(this._s?.noticeTimer);
        if (this._s) this._s.noticeTimer = setTimeout(() => { this.notice = null; }, 4000);
    },

    headers() {
        return {
            'X-CSRF-TOKEN': this.cfg.csrf || document.querySelector('meta[name=csrf-token]')?.content || '',
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        };
    },

    /** Build a beacon body and take the watched seconds collected so far. */
    payload(event) {
        const video = this.video();
        const s = this._s;
        const watched = Math.min(MAX_WATCHED, Math.max(0, Math.round(s.watched)));
        s.watched = Math.max(0, s.watched - watched);

        const body = {
            event,
            position: Math.min(MAX_SECONDS, Math.max(0, Math.floor(video?.currentTime || 0))),
            watched,
        };
        const duration = this.duration();
        if (duration) body.duration = duration;
        s.lastSentPosition = body.position;

        return body;
    },

    // --- Progress beacons --------------------------------------------------
    async send(event) {
        if (!this.cfg.progressUrl || !this._s) return;
        const body = this.payload(event);

        try {
            const res = await fetch(this.cfg.progressUrl, {
                method: 'POST',
                headers: this.headers(),
                credentials: 'same-origin',
                body: JSON.stringify(body),
            });
            if (!res.ok) throw new Error(String(res.status));
            const data = await res.json();
            if (typeof data.percent === 'number') this.percent = data.percent;
            if (data.completed && !this.completed) {
                this.completed = true;
                this.flash('Lesson completed. Nice work!');
            }
        } catch (e) {
            // Keep the unsent watch time for the next beacon; playback is never interrupted.
            this._s.watched += body.watched;
        }
    },

    /** Last-chance save when the page is hidden or unloaded (the answer cannot be read). */
    flush() {
        const s = this._s;
        const video = this.video();
        if (!s || !this.started || !this.cfg.progressUrl || !video) return;
        if (s.watched < 1 && Math.floor(video.currentTime) === s.lastSentPosition) return;

        const body = this.payload(video.ended ? 'ended' : 'pause');
        const form = new FormData();
        form.append('_token', this.cfg.csrf || document.querySelector('meta[name=csrf-token]')?.content || '');
        Object.entries(body).forEach(([k, v]) => form.append(k, String(v)));

        if (navigator.sendBeacon && navigator.sendBeacon(this.cfg.progressUrl, form)) return;

        fetch(this.cfg.progressUrl, { method: 'POST', body: form, credentials: 'same-origin', keepalive: true }).catch(() => {});
    },

    startTicking() {
        this.stopTicking();
        this._s.tickTimer = setInterval(() => {
            if (this.playing) this.send('tick');
        }, TICK_MS);
    },

    stopTicking() {
        if (this._s?.tickTimer) {
            clearInterval(this._s.tickTimer);
            this._s.tickTimer = null;
        }
    },

    // --- Playback controls -------------------------------------------------
    play() {
        const p = this.video()?.play();
        if (p && typeof p.catch === 'function') p.catch(() => {});
    },

    togglePlay() {
        const video = this.video();
        if (!video) return;
        if (this.resumePrompt) this.resumePrompt = false;
        video.paused || video.ended ? this.play() : video.pause();
    },

    seekTo(seconds, { silent = false } = {}) {
        const video = this.video();
        if (!video) return;
        const apply = () => {
            const d = Number.isFinite(video.duration) ? video.duration : null;
            if (silent) this._s.ignoreSeek = true;
            video.currentTime = Math.max(0, d ? Math.min(seconds, d - 0.1) : seconds);
        };
        video.readyState >= 1 ? apply() : video.addEventListener('loadedmetadata', apply, { once: true });
    },

    skip(delta) {
        const video = this.video();
        if (!video) return;
        this.seekTo((video.currentTime || 0) + delta);
    },

    resume() {
        this.resumePrompt = false;
        this.seekTo(this.resumeAt, { silent: true });
        this.play();
    },

    startOver() {
        this.resumePrompt = false;
        this.seekTo(0, { silent: true });
        this.play();
    },

    restart() {
        this.ended = false;
        this.resumePrompt = false;
        this.seekTo(0);
        this.play();
    },

    setSpeed(rate) {
        if (!SPEEDS.includes(rate)) return;
        this.speed = rate;
        const video = this.video();
        if (video) video.playbackRate = rate;
        storageSet(SPEED_KEY, String(rate));
        this.speedOpen = false;
    },

    setQuality(id) {
        this.qualityOpen = false;
        const source = this.sources.find((s) => s.id === id);
        const video = this.video();
        if (!source || !video || id === this.currentSource) return;

        const time = video.currentTime || 0;
        const wasPlaying = !video.paused && !video.ended;

        this.switching = true;
        this.errorMessage = null;
        this.currentSource = id;
        storageSet(QUALITY_KEY, String(id));

        video.addEventListener('loadedmetadata', () => {
            video.playbackRate = this.speed;
            this._s.ignoreSeek = true;
            video.currentTime = Math.min(time, Number.isFinite(video.duration) ? Math.max(0, video.duration - 0.1) : time);
            this._s.lastTime = video.currentTime;
            this.switching = false;
            if (wasPlaying) this.play();
        }, { once: true });

        video.src = source.url;
        video.load();
    },

    retry() {
        const video = this.video();
        if (!video) return;
        const time = video.currentTime || this._s.lastTime || 0;
        this.errorMessage = null;
        this.loading = true;
        const source = this.sources.find((s) => s.id === this.currentSource) || this.sources[0];
        video.addEventListener('loadedmetadata', () => {
            video.playbackRate = this.speed;
            if (time > 0) this.seekTo(time, { silent: true });
        }, { once: true });
        if (source) video.src = source.url;
        video.load();
    },

    toggleFullscreen() {
        const stage = this.$refs.stage;
        const video = this.video();
        if (document.fullscreenElement) {
            document.exitFullscreen?.().catch(() => {});
            return;
        }
        if (stage?.requestFullscreen) {
            stage.requestFullscreen().catch(() => {});
        } else if (video?.webkitEnterFullscreen) {
            video.webkitEnterFullscreen(); // iOS Safari
        }
    },

    onKey(e) {
        if (e.defaultPrevented || e.ctrlKey || e.metaKey || e.altKey) return;
        if (isTypingTarget(e.target)) return;

        const video = this.video();
        // A focused <video> with native controls already handles space and arrows.
        const nativeHandles = e.target === video;
        const onControl = e.target && ['BUTTON', 'A'].includes(e.target.tagName);

        switch (e.key) {
            case ' ':
            case 'Spacebar':
                if (nativeHandles || onControl) return;
                e.preventDefault();
                this.togglePlay();
                break;
            case 'k':
            case 'K':
                e.preventDefault();
                this.togglePlay();
                break;
            case 'ArrowLeft':
                if (nativeHandles) return;
                e.preventDefault();
                this.skip(-5);
                break;
            case 'ArrowRight':
                if (nativeHandles) return;
                e.preventDefault();
                this.skip(5);
                break;
            case 'f':
            case 'F':
                e.preventDefault();
                this.toggleFullscreen();
                break;
            default:
        }
    },

    // --- Completion ----------------------------------------------------------
    async toggleCompleted() {
        if (this.completing || !this.cfg.completeUrl) return;
        this.completing = true;
        const target = !this.completed;

        try {
            const res = await fetch(this.cfg.completeUrl, {
                method: 'POST',
                headers: this.headers(),
                credentials: 'same-origin',
                body: JSON.stringify({ completed: target }),
            });
            if (!res.ok) throw new Error(String(res.status));
            const data = await res.json();
            this.completed = Boolean(data.completed);
            if (typeof data.percent === 'number') this.percent = data.percent;
            if (!this.completed) this.resumeAt = 0;
            this.flash(this.completed ? 'Lesson marked as completed.' : 'Lesson marked as not completed.');
        } catch (e) {
            this.flash('Could not update the lesson. Please try again.', true);
        } finally {
            this.completing = false;
        }
    },
});
