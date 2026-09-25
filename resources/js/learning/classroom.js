/**
 * Live classroom page (learn.rooms.live) — our own WebRTC classroom on the
 * self-hosted LiveKit SFU. No third-party video service or iframe: the
 * browser captures camera / microphone / screen with getUserMedia() and
 * getDisplayMedia() (through livekit-client) and exchanges media with our
 * SFU over WSS + WebRTC, with our coturn server as TURN fallback.
 *
 * Laravel stays the authority. It issues a short-lived token carrying this
 * user's exact publish rights; host actions (mute, rights, remove, lock,
 * record, end) are POSTed to Laravel, which executes them on the SFU. Chat /
 * Q&A are stored by Laravel; data messages on the SFU only nudge clients to
 * refresh the feed instantly (the feed is also polled as a fallback).
 *
 * Used as x-data="learnClassroom(@js($config))". Config (LiveRoomController::classroomConfig):
 *   urls: { room, rooms, join, token, presence, leave, feed,
 *           messages: { store, answer (__ID__), destroy (__ID__) },
 *           studio?: { show, start, end, announce, lock, media, muteAll, recordingStart, recordingStop,
 *                      materials, materialDestroy (__ID__), remove / permissions / mute (__ID__ = user id) } }
 *   room: { id, title, status, …, allow_participant_media, allow_screen_share, is_locked, is_recording }
 *   viewer: { id, name, is_manager, identity }
 *   provider: { label, configured, supportsRecording, setupWarning }
 *   materials: [...], pollMs, presenceMs, maxMessageLength, maxMaterialMb, materialExtensions
 *
 * All user-generated text is rendered with x-text by the templates.
 */

const MAX_BACKOFF_MS = 60000;
const MAX_REJOIN_ATTEMPTS = 5;
const CONNECT_TIMEOUT_MS = 20000;
const DATA_TOPIC = 'classroom';

/** livekit-client is only downloaded when someone actually joins a call. */
let livekitModule = null;
function loadLiveKit() {
    if (!livekitModule) {
        livekitModule = import('livekit-client').catch((e) => {
            livekitModule = null;
            throw e;
        });
    }
    return livekitModule;
}

function csrfToken() {
    return document.querySelector('meta[name=csrf-token]')?.content || '';
}

function jsonHeaders() {
    return {
        'X-CSRF-TOKEN': csrfToken(),
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };
}

/** First human-readable error from a Laravel JSON error body. */
function errorMessage(data, fallback) {
    if (data && data.errors && typeof data.errors === 'object') {
        const first = Object.values(data.errors)[0];
        if (Array.isArray(first) && first.length) return String(first[0]);
    }
    if (data && typeof data.message === 'string' && data.message) return data.message;

    return fallback;
}

async function readJson(response) {
    try {
        return await response.json();
    } catch (e) {
        return null;
    }
}

function formatBytes(bytes) {
    const n = Number(bytes) || 0;
    if (n < 1024) return n + ' B';
    if (n < 1024 * 1024) return (n / 1024).toFixed(0) + ' KB';
    return (n / (1024 * 1024)).toFixed(1) + ' MB';
}

window.learnClassroom = (cfg = {}) => {
    // LiveKit objects stay outside Alpine's reactive proxy on purpose.
    const lk = { mod: null, room: null, intentional: false, refreshFrame: null };

    return {
    cfg,
    urls: cfg.urls || {},
    room: { ...(cfg.room || {}) },
    viewer: cfg.viewer || {},
    provider: cfg.provider || {},
    isManager: !!(cfg.viewer && cfg.viewer.is_manager),
    materials: Array.isArray(cfg.materials) ? cfg.materials : [],

    // waiting | prejoin | joining | in_call | reconnecting | ended | removed | error
    state: 'waiting',
    hasLeft: false,
    errorTitle: '',
    errorText: '',
    notice: '',
    noticeTimer: null,
    mediaError: '',

    // Call
    lkState: 'disconnected', // disconnected | connecting | connected | reconnecting
    rejoinAttempts: 0,
    rejoinTimer: null,
    permissions: { audio: false, video: false, screen: false },
    media: { mic: false, cam: false, screen: false },
    mediaBusy: { mic: false, cam: false, screen: false },
    recordingBusy: false,
    audioBlocked: false,
    myQuality: 'unknown',
    tiles: [],
    remoteState: {}, // identity → { mic, cam, screen, speaking, quality }
    layout: 'speaker', // speaker | grid
    pinnedId: null,
    isFullscreen: false,
    devices: { open: false, cams: [], mics: [], speakers: [], cam: '', mic: '', speaker: '' },

    // Feed
    messages: [],
    lastId: 0,
    cursor: null,
    participants: [],
    counts: { participants: 0, questions_open: 0 },
    feedLoaded: false,
    feedFailures: 0,
    feedTimer: null,
    feedBusy: false,
    feedStopped: false,
    presenceTimer: null,
    online: typeof navigator === 'undefined' ? true : navigator.onLine !== false,

    // UI
    isLg: false,
    canHover: false, // mouse / trackpad (auto-hiding bars only make sense there)
    panelOpen: false,
    // Immersive mode on desktop during a call: the control bar and the side
    // panel hide and slide back in when the mouse reaches the bottom / right edge.
    chrome: { bar: true, panel: false },
    barHover: false,
    panelHover: false,
    panelPinned: false,
    barTimer: null,
    panelTimer: null,
    tab: 'chat',
    unread: { chat: 0, qa: 0 },
    qaFilter: 'all',
    drafts: { chat: '', question: '' },
    announceMode: false,
    sending: { chat: false, question: false },
    composerError: { chat: '', question: '' },
    busy: { start: false, end: false, remove: null, message: null, mod: null, upload: false },
    upload: { title: '', error: '' },
    confirm: { open: false, kind: '', title: '', body: '', confirmLabel: '', payload: null },
    elapsed: '',
    clockTimer: null,

    init() {
        const lg = window.matchMedia('(min-width: 1024px)');
        this.isLg = lg.matches;
        const onLg = (e) => { this.isLg = e.matches; if (e.matches) this.panelOpen = false; };
        lg.addEventListener ? lg.addEventListener('change', onLg) : lg.addListener(onLg);

        const hover = window.matchMedia('(hover: hover) and (pointer: fine)');
        this.canHover = hover.matches;
        const onHover = (e) => { this.canHover = e.matches; };
        hover.addEventListener ? hover.addEventListener('change', onHover) : hover.addListener(onHover);

        this.state = this.initialState();
        this.tick();
        this.clockTimer = setInterval(() => this.tick(), 1000);

        window.addEventListener('online', () => { this.online = true; this.pollNow(); });
        window.addEventListener('offline', () => { this.online = false; });
        window.addEventListener('pagehide', () => this.onPageHide());
        // Restored from the back/forward cache: the call was torn down on pagehide.
        window.addEventListener('pageshow', (e) => {
            if (!e.persisted) return;
            if (['in_call', 'joining', 'reconnecting'].includes(this.state)) {
                this.state = this.room.status === 'live' ? 'prejoin' : 'ended';
                this.hasLeft = this.state === 'prejoin';
            }
            this.pollNow();
        });
        document.addEventListener('fullscreenchange', () => { this.isFullscreen = !!document.fullscreenElement; });

        this.poll();
    },

    destroy() {
        clearInterval(this.clockTimer);
        clearTimeout(this.feedTimer);
        clearTimeout(this.rejoinTimer);
        this.stopPresence();
        this.disconnect(true);
    },

    initialState() {
        if (this.room.status === 'live') return 'prejoin';
        if (this.room.status === 'completed' || this.room.status === 'cancelled') return 'ended';

        return 'waiting';
    },

    // --- Derived --------------------------------------------------------
    get inCall() {
        return this.state === 'in_call';
    },

    get canUseMic() { return this.isManager || !!this.permissions.audio; },
    get canUseCam() { return this.isManager || !!this.permissions.video; },
    get canShare() { return this.isManager || !!this.permissions.screen; },

    get screenShareSupported() {
        return !!(navigator.mediaDevices && navigator.mediaDevices.getDisplayMedia);
    },

    get fullscreenSupported() {
        return !!document.fullscreenEnabled;
    },

    get mediaLockedText() {
        return 'The host has not allowed participants to use this. You can still watch, chat and ask questions.';
    },

    get connection() {
        if (!this.online) return { key: 'offline', label: 'Offline' };
        if (this.state === 'reconnecting' || this.lkState === 'reconnecting') return { key: 'reconnecting', label: 'Reconnecting…' };
        if (this.state === 'joining' || this.lkState === 'connecting') return { key: 'connecting', label: 'Connecting…' };
        if (this.feedFailures >= 2 && !this.inCall) return { key: 'reconnecting', label: 'Reconnecting…' };
        if (this.inCall && this.lkState === 'connected') return { key: 'connected', label: 'Connected' };
        if (!this.feedLoaded && this.feedFailures === 0) return { key: 'connecting', label: 'Connecting…' };

        return { key: 'connected', label: this.inCall ? 'Connected' : 'Online' };
    },

    get showConnectionBanner() {
        return !this.online || this.feedFailures >= 2 || this.state === 'reconnecting';
    },

    get connectionBannerText() {
        if (!this.online) return 'You are offline — we will reconnect when your connection returns.';
        if (this.state === 'reconnecting') return 'Connection lost — reconnecting to the class…';

        return 'Connection lost — retrying…';
    },

    get panelVisible() {
        if (this.autoHide) return this.chrome.panel;

        return this.isLg || this.panelOpen;
    },

    /** Desktop with a mouse, in the call: bars hide until the mouse reaches the edge. */
    get autoHide() {
        return this.canHover && this.isLg && (this.state === 'in_call' || this.state === 'reconnecting');
    },

    get announcements() {
        return this.messages.filter((m) => m.type === 'announcement' && !m.is_deleted);
    },

    get chatMessages() {
        return this.messages.filter((m) => m.type === 'chat');
    },

    /** My own chat message (shown on the right). */
    isMine(m) {
        return !!(m && m.user && Number(m.user.id) === Number(this.viewer.id));
    },

    /** Same sender within 3 minutes → one visual group (name/avatar shown once). */
    sameGroup(a, b) {
        if (!a || !b || !a.user || !b.user || Number(a.user.id) !== Number(b.user.id)) return false;
        const gap = Math.abs(Date.parse(b.created_at) - Date.parse(a.created_at));

        return Number.isNaN(gap) || gap <= 3 * 60 * 1000;
    },

    startsGroup(i) {
        const list = this.chatMessages;
        return i === 0 || !this.sameGroup(list[i - 1], list[i]);
    },

    endsGroup(i) {
        const list = this.chatMessages;
        return i === list.length - 1 || !this.sameGroup(list[i], list[i + 1]);
    },

    get questions() {
        const list = this.messages.filter((m) => m.type === 'question');
        if (this.qaFilter === 'open') return list.filter((m) => !m.is_answered && !m.is_deleted);
        if (this.qaFilter === 'answered') return list.filter((m) => m.is_answered);

        return list;
    },

    get canChat() {
        return this.room.status === 'live' && (this.isManager || !!this.room.chat_enabled) && this.state !== 'removed';
    },

    get canAsk() {
        return this.room.status === 'live' && (this.isManager || !!this.room.questions_enabled) && this.state !== 'removed';
    },

    get chatDisabledText() {
        if (this.state === 'removed') return 'You were removed from this session.';
        if (this.room.status !== 'live') return 'Chat opens when the session is live.';
        if (!this.room.chat_enabled) return 'The host has turned chat off.';

        return '';
    },

    get askDisabledText() {
        if (this.state === 'removed') return 'You were removed from this session.';
        if (this.room.status !== 'live') return 'Questions open when the session is live.';
        if (!this.room.questions_enabled) return 'The host has turned questions off.';

        return '';
    },

    get sortedParticipants() {
        return [...this.participants].sort((a, b) => {
            if (a.is_me !== b.is_me) return a.is_me ? -1 : 1;
            if ((a.role === 'host') !== (b.role === 'host')) return a.role === 'host' ? -1 : 1;

            return String(a.name).localeCompare(String(b.name));
        });
    },

    /** The big tile in speaker layout: pinned → screen share → active speaker → host → first. */
    get stageTile() {
        if (!this.tiles.length) return null;
        const pinned = this.pinnedId && this.tiles.find((t) => t.id === this.pinnedId);
        if (pinned) return pinned;
        const screen = this.tiles.find((t) => t.source === 'screen');
        if (screen) return screen;
        const speaking = this.tiles.find((t) => t.speaking && !t.isLocal && t.source === 'camera');
        if (speaking) return speaking;
        const host = this.tiles.find((t) => t.isHost && !t.isLocal && t.source === 'camera');
        if (host) return host;

        return this.tiles.find((t) => !t.isLocal) || this.tiles[0];
    },

    get filmstripTiles() {
        const main = this.stageTile;
        return this.tiles.filter((t) => !main || t.id !== main.id);
    },

    get gridClass() {
        const n = this.tiles.length;
        if (n <= 1) return 'grid-cols-1';
        if (n === 2) return 'grid-cols-1 sm:grid-cols-2';
        if (n <= 4) return 'grid-cols-2';
        if (n <= 9) return 'grid-cols-2 md:grid-cols-3';

        return 'grid-cols-3 md:grid-cols-4';
    },

    // --- Clock ----------------------------------------------------------
    tick() {
        if (this.room.status !== 'live' || !this.room.started_at) {
            this.elapsed = '';
            return;
        }
        const start = Date.parse(this.room.started_at);
        if (Number.isNaN(start)) {
            this.elapsed = '';
            return;
        }
        const total = Math.max(0, Math.floor((Date.now() - start) / 1000));
        const h = Math.floor(total / 3600);
        const m = Math.floor((total % 3600) / 60);
        const s = total % 60;
        const pad = (n) => String(n).padStart(2, '0');
        this.elapsed = (h > 0 ? h + ':' + pad(m) : pad(m)) + ':' + pad(s);
    },

    formatTime(iso) {
        if (!iso) return '';
        const d = new Date(iso);
        if (Number.isNaN(d.getTime())) return '';

        return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    },

    formatBytes,

    flash(text, ms = 5000) {
        this.notice = text;
        clearTimeout(this.noticeTimer);
        this.noticeTimer = setTimeout(() => { this.notice = ''; }, ms);
    },

    // --- Feed polling ----------------------------------------------------
    pollNow() {
        clearTimeout(this.feedTimer);
        this.poll();
    },

    async poll() {
        if (this.feedBusy) return;
        this.feedBusy = true;
        let delay = this.cfg.pollMs || 4000;

        try {
            const params = new URLSearchParams();
            if (this.lastId > 0) params.set('after', String(this.lastId));
            if (this.cursor && this.lastId > 0) params.set('since', this.cursor);
            const url = this.urls.feed + (params.toString() ? '?' + params.toString() : '');

            const response = await fetch(url, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                cache: 'no-store',
            });

            if (response.status === 401 || response.status === 419) {
                this.feedStopped = true;
                this.disconnect(true);
                this.fail('Your session expired', 'Reload the page and sign in again to continue.');
                return;
            }
            if (response.status === 403 || response.status === 404) {
                this.feedStopped = true;
                this.disconnect(true);
                this.fail('This class is no longer available', 'You no longer have access to this class.');
                return;
            }
            if (!response.ok) throw new Error('feed ' + response.status);

            const data = await response.json();
            this.applyFeed(data);
            this.feedFailures = 0;
        } catch (e) {
            this.feedFailures += 1;
            delay = Math.min(MAX_BACKOFF_MS, (this.cfg.pollMs || 4000) * Math.pow(2, this.feedFailures));
        } finally {
            this.feedBusy = false;
            if (!this.feedStopped) {
                clearTimeout(this.feedTimer);
                this.feedTimer = setTimeout(() => this.poll(), document.hidden ? Math.max(delay, 15000) : delay);
            }
        }
    },

    applyFeed(data) {
        if (!data || typeof data !== 'object') return;
        const firstLoad = !this.feedLoaded;

        if (data.cursor) this.cursor = data.cursor;

        if (data.room) {
            const wasLive = this.room.status === 'live';
            Object.assign(this.room, data.room);
            if (data.room.status === 'live' && !wasLive) this.tick();
        }

        (data.messages || []).forEach((m) => this.upsertMessage(m, !firstLoad));
        (data.updates || []).forEach((m) => this.upsertMessage(m, false));

        if (Array.isArray(data.participants)) this.participants = data.participants;
        if (data.counts) this.counts = data.counts;
        if (Array.isArray(data.materials)) this.materials = data.materials;
        if (data.me && data.me.permissions) this.applyPermissions(data.me.permissions);
        this.feedLoaded = true;

        if (data.me && data.me.removed && this.state !== 'removed') {
            this.enterRemoved();
            return;
        }

        this.syncState();

        if (firstLoad) this.$nextTick(() => this.scrollToBottom(true));
    },

    /** Move the page between screens as the room status changes. */
    syncState() {
        const status = this.room.status;

        if (status === 'live') {
            if (this.state === 'waiting' || (this.state === 'ended' && !lk.room)) {
                this.hasLeft = false;
                this.state = 'prejoin';
            }
            return;
        }

        if (['prejoin', 'joining', 'in_call', 'reconnecting'].includes(this.state)) {
            this.disconnect(true);
            this.state = 'ended';
        } else if (this.state === 'waiting' && (status === 'completed' || status === 'cancelled')) {
            this.state = 'ended';
        } else if (this.state === 'ended' && (status === 'scheduled' || status === 'draft')) {
            this.state = 'waiting';
        }
    },

    upsertMessage(m, countUnread) {
        if (!m || !m.id) return;
        const index = this.messages.findIndex((x) => x.id === m.id);

        if (index >= 0) {
            this.messages.splice(index, 1, m);
            return;
        }

        const nearBottom = this.isNearBottom();
        let pos = this.messages.length;
        while (pos > 0 && this.messages[pos - 1].id > m.id) pos -= 1;
        this.messages.splice(pos, 0, m);
        if (m.id > this.lastId) this.lastId = m.id;

        const mine = m.user && Number(m.user.id) === Number(this.viewer.id);
        if (countUnread && !mine) {
            const bucket = m.type === 'question' ? 'qa' : 'chat';
            if (!this.panelVisible || this.tab !== bucket) this.unread[bucket] += 1;
            if (m.type === 'announcement') this.flash('Announcement from the host: ' + m.body, 8000);
        }

        if (nearBottom || mine) this.$nextTick(() => this.scrollToBottom());
    },

    isNearBottom() {
        const list = this.tab === 'qa' ? this.$refs.qaList : this.$refs.chatList;
        if (!list) return true;

        return list.scrollHeight - list.scrollTop - list.clientHeight < 80;
    },

    scrollToBottom(force = false) {
        ['chatList', 'qaList'].forEach((ref) => {
            const el = this.$refs[ref];
            if (el && (force || el.offsetParent !== null)) el.scrollTop = el.scrollHeight;
        });
    },

    // --- Panel -------------------------------------------------------------
    openTab(name) {
        this.tab = name;
        if (this.autoHide) {
            // Opened on purpose (button / keyboard): stays until closed.
            this.chrome.panel = true;
            this.panelPinned = true;
        } else if (!this.isLg) {
            this.panelOpen = true;
        }
        if (name === 'chat') this.unread.chat = 0;
        if (name === 'qa') this.unread.qa = 0;
        this.$nextTick(() => this.scrollToBottom(true));
    },

    togglePanel(name) {
        if (this.autoHide && this.chrome.panel && this.tab === name) {
            this.closePanel();
            return;
        }
        if (!this.autoHide && !this.isLg && this.panelOpen && this.tab === name) {
            this.panelOpen = false;
            return;
        }
        this.openTab(name);
    },

    closePanel() {
        this.panelOpen = false;
        this.chrome.panel = false;
        this.panelPinned = false;
    },

    // --- Auto-hiding control bar & side panel (desktop, in the call) ------------
    onPointerMove(e) {
        if (!this.autoHide) return;
        if (e.clientY >= window.innerHeight - 110) this.showBar();
        else this.scheduleBarHide();
        if (e.clientX >= window.innerWidth - 28) this.showPanel();
    },

    showBar() {
        clearTimeout(this.barTimer);
        this.barTimer = null;
        this.chrome.bar = true;
    },

    scheduleBarHide(delay = 2000) {
        if (!this.autoHide || this.barHover || this.barTimer) return;
        this.barTimer = setTimeout(() => {
            this.barTimer = null;
            // Keep it while the mouse is on it or a control inside it has keyboard focus.
            const focusInside = this.$refs.controlBar && this.$refs.controlBar.contains(document.activeElement);
            if (this.autoHide && !this.barHover && !focusInside) this.chrome.bar = false;
        }, delay);
    },

    showPanel() {
        clearTimeout(this.panelTimer);
        this.chrome.panel = true;
    },

    onPanelLeave() {
        this.panelHover = false;
        if (!this.autoHide || this.panelPinned) return;
        clearTimeout(this.panelTimer);
        this.panelTimer = setTimeout(() => {
            const typing = this.$refs.panel && this.$refs.panel.contains(document.activeElement)
                && ['TEXTAREA', 'INPUT', 'SELECT'].includes(document.activeElement.tagName);
            if (!this.panelHover && !this.panelPinned && !typing && !this.confirm.open) this.chrome.panel = false;
        }, 600);
    },

    /** Show both bars briefly (entering the call), then let them tuck away. */
    revealChrome() {
        this.showBar();
        this.$nextTick(() => this.scheduleBarHide(3500));
    },

    // --- Joining -----------------------------------------------------------
    /** Ask Laravel for a short-lived token (join and reconnect use the same checks). */
    async requestToken(url) {
        let response;
        let data;
        try {
            response = await fetch(url, {
                method: 'POST',
                headers: jsonHeaders(),
                credentials: 'same-origin',
                body: JSON.stringify({}),
            });
            data = await readJson(response);
        } catch (e) {
            return { ok: false, network: true };
        }

        return { ok: response.ok, status: response.status, data };
    },

    async join() {
        if (['joining', 'in_call', 'reconnecting'].includes(this.state)) return;
        this.state = 'joining';
        this.hasLeft = false;
        this.mediaError = '';
        this.rejoinAttempts = 0;

        const result = await this.requestToken(this.urls.join);
        if (!(await this.handleTokenResult(result))) return;

        await this.connect(result.data.config, this.isManager);
    },

    /** Maps token errors to screens. Returns true when a config is available. */
    async handleTokenResult(result) {
        if (result.network) {
            this.fail('Could not reach the classroom', 'Check your internet connection and try again.');
            return false;
        }
        if (result.ok && result.data && result.data.config) return true;

        const data = result.data;
        const reason = data && data.reason;
        if (reason === 'removed') { this.enterRemoved(); return false; }
        if (reason === 'not_live') {
            this.flash(errorMessage(data, 'This live session is not running right now.'));
            this.state = ['completed', 'cancelled'].includes(this.room.status) ? 'ended' : 'waiting';
            this.pollNow();
            return false;
        }
        if (reason === 'locked') {
            this.state = 'prejoin';
            this.flash(errorMessage(data, 'The host has locked this class.'), 8000);
            return false;
        }
        if (result.status === 419) { this.fail('Your session expired', 'Reload the page and try again.'); return false; }
        if (result.status === 429) { this.fail('Too many attempts', 'Please wait a minute before trying again.'); return false; }
        if (reason === 'provider_unavailable') {
            this.fail('Live video is unavailable', errorMessage(data, 'The live video server is not available.'));
            return false;
        }

        this.fail('You cannot join this session', errorMessage(data, 'Something went wrong. Please try again.'));
        return false;
    },

    /** Open the WebRTC connection to our SFU with a config from Laravel. */
    async connect(config, publishOnJoin) {
        let mod;
        try {
            mod = await loadLiveKit();
        } catch (e) {
            this.sendLeave(false);
            this.fail('The classroom could not load', 'Part of the page failed to download. Check your connection and try again.');
            return;
        }
        lk.mod = mod;

        if (!mod.isBrowserSupported()) {
            this.sendLeave(false);
            this.fail('This browser cannot join live classes',
                'Use a recent version of Chrome, Edge, Firefox or Safari (on iPhone/iPad use Safari 14.5 or newer).');
            return;
        }

        this.applyPermissions(config.permissions || {});
        this.disconnect(true);

        const room = new mod.Room({
            adaptiveStream: true, // subscribe to the quality each tile actually needs
            dynacast: true, // stop encoding layers nobody watches
            disconnectOnPageLeave: false, // pagehide is handled below (leave beacon first)
            videoCaptureDefaults: { resolution: mod.VideoPresets.h720.resolution },
            audioCaptureDefaults: { echoCancellation: true, noiseSuppression: true, autoGainControl: true },
            publishDefaults: {
                simulcast: true,
                videoSimulcastLayers: [mod.VideoPresets.h180, mod.VideoPresets.h360],
                screenShareEncoding: mod.ScreenSharePresets.h1080fps15.encoding,
                dtx: true,
            },
        });
        lk.room = room;
        lk.intentional = false;
        this.wireRoom(room, mod);

        this.lkState = 'connecting';
        const rtcConfig = { iceTransportPolicy: config.ice_transport_policy === 'relay' ? 'relay' : 'all' };
        if (Array.isArray(config.ice_servers) && config.ice_servers.length) rtcConfig.iceServers = config.ice_servers;

        let timer;
        try {
            // Never spin forever: an unreachable server fails after CONNECT_TIMEOUT_MS.
            const timeout = new Promise((resolve, reject) => {
                timer = setTimeout(() => reject(new Error('timeout')), CONNECT_TIMEOUT_MS);
            });
            await Promise.race([room.connect(config.server_url, config.token, { autoSubscribe: true, rtcConfig }), timeout]);
        } catch (e) {
            if (lk.room !== room) return; // replaced meanwhile
            this.disconnect(true);
            this.sendLeave(false);
            const timedOut = e && e.message === 'timeout';
            this.fail('Could not connect to the live classroom',
                (timedOut ? 'The video server (' + config.server_url + ') did not answer within ' + (CONNECT_TIMEOUT_MS / 1000) + ' seconds. '
                    : 'The video server did not answer. ')
                + 'Check your connection; on a work or school network video calls may be blocked — try another network, then Retry.');
            return;
        } finally {
            clearTimeout(timer);
        }
        if (lk.room !== room) return;

        this.lkState = 'connected';
        this.state = 'in_call';
        this.rejoinAttempts = 0;
        this.revealChrome();
        this.startPresence();
        this.pollNow();
        this.refreshTiles();

        if (!room.canPlaybackAudio) this.audioBlocked = true;

        if (publishOnJoin) {
            if (this.canUseMic) await this.setMic(true, true);
            if (this.canUseCam) await this.setCam(true, true);
        }
    },

    wireRoom(room, mod) {
        const E = mod.RoomEvent;
        const refresh = () => this.scheduleRefresh();

        room
            .on(E.ParticipantConnected, refresh)
            .on(E.ParticipantDisconnected, (p) => {
                if (this.pinnedId && this.pinnedId.startsWith(p.identity + ':')) this.pinnedId = null;
                refresh();
            })
            .on(E.TrackSubscribed, (track) => {
                if (track.kind === 'audio') {
                    const el = track.attach();
                    el.dataset.lkAudio = '1';
                    this.$refs.audioSink && this.$refs.audioSink.appendChild(el);
                }
                refresh();
            })
            .on(E.TrackUnsubscribed, (track) => {
                track.detach().forEach((el) => { if (el.dataset && el.dataset.lkAudio) el.remove(); });
                refresh();
            })
            .on(E.TrackSubscriptionFailed, () => this.flash('A participant’s video could not be received. It will retry automatically.'))
            .on(E.TrackMuted, refresh)
            .on(E.TrackUnmuted, refresh)
            .on(E.LocalTrackPublished, refresh)
            .on(E.LocalTrackUnpublished, refresh)
            .on(E.ActiveSpeakersChanged, refresh)
            .on(E.ConnectionQualityChanged, refresh)
            .on(E.ParticipantPermissionsChanged, (prev, participant) => {
                if (participant && participant.isLocal) this.onLocalPermissionsChanged(participant);
            })
            .on(E.AudioPlaybackStatusChanged, () => { this.audioBlocked = !room.canPlaybackAudio; })
            .on(E.MediaDevicesError, (e) => { this.mediaError = this.deviceHelp('camera', e); })
            .on(E.MediaDevicesChanged, () => this.onDevicesChanged())
            .on(E.DataReceived, (payload, participant, kind, topic) => this.onData(payload, topic))
            .on(E.Reconnecting, () => { this.lkState = 'reconnecting'; })
            .on(E.SignalReconnecting, () => { this.lkState = 'reconnecting'; })
            .on(E.Reconnected, () => { this.lkState = 'connected'; this.pollNow(); refresh(); })
            .on(E.Disconnected, (reason) => this.onDisconnected(room, reason));
    },

    /** Coalesce bursts of SFU events into one re-render per frame. */
    scheduleRefresh() {
        if (lk.refreshFrame) return;
        lk.refreshFrame = requestAnimationFrame(() => {
            lk.refreshFrame = null;
            this.refreshTiles();
        });
    },

    /** Rebuild the plain tile list from the SFU room state, then attach video elements. */
    refreshTiles() {
        const room = lk.room;
        const mod = lk.mod;
        if (!room || !mod) {
            this.tiles = [];
            return;
        }

        const S = mod.Track.Source;
        const people = [room.localParticipant, ...room.remoteParticipants.values()];
        const tiles = [];
        const remoteState = {};

        people.forEach((p) => {
            let meta = {};
            try { meta = p.metadata ? JSON.parse(p.metadata) : {}; } catch (e) { meta = {}; }
            const camPub = p.getTrackPublication(S.Camera);
            const micPub = p.getTrackPublication(S.Microphone);
            const screenPub = p.getTrackPublication(S.ScreenShare);
            const cam = !!(camPub && !camPub.isMuted && camPub.track);
            const mic = !!(micPub && !micPub.isMuted);
            const screen = !!(screenPub && !screenPub.isMuted && screenPub.track);
            const base = {
                identity: p.identity,
                name: p.name || p.identity,
                isLocal: !!p.isLocal,
                isHost: meta.role === 'host',
                mic,
                speaking: !!p.isSpeaking,
                quality: String(p.connectionQuality || 'unknown'),
            };

            remoteState[p.identity] = { mic, cam, screen, speaking: base.speaking, quality: base.quality };
            tiles.push({ ...base, id: p.identity + ':camera', source: 'camera', hasVideo: cam });
            if (screen) tiles.push({ ...base, id: p.identity + ':screen', source: 'screen', hasVideo: true });
        });

        const local = room.localParticipant;
        this.media.mic = !!local.isMicrophoneEnabled;
        this.media.cam = !!local.isCameraEnabled;
        this.media.screen = !!local.isScreenShareEnabled;
        this.myQuality = String(local.connectionQuality || 'unknown');
        if (this.pinnedId && !tiles.some((t) => t.id === this.pinnedId)) this.pinnedId = null;

        this.remoteState = remoteState;
        this.tiles = tiles;
        this.$nextTick(() => this.attachVideos());
    },

    /** Attach each tile's video track to its <video data-tile-id>. */
    attachVideos() {
        const room = lk.room;
        const mod = lk.mod;
        if (!room || !mod) return;
        const S = mod.Track.Source;

        this.$root.querySelectorAll('video[data-tile-id]').forEach((el) => {
            const [identity, source] = String(el.dataset.tileId).split(':');
            const p = identity === room.localParticipant.identity ? room.localParticipant : room.remoteParticipants.get(identity);
            const pub = p && p.getTrackPublication(source === 'screen' ? S.ScreenShare : S.Camera);
            const track = pub && !pub.isMuted ? pub.track : null;

            if (!track) {
                if (el.srcObject) el.srcObject = null;
                el._lkTrack = null;
                return;
            }
            if (el._lkTrack !== track) {
                if (el._lkTrack) el._lkTrack.detach(el);
                track.attach(el);
                el._lkTrack = track;
            }
        });
    },

    tileState(identity) {
        return this.remoteState[identity] || null;
    },

    qualityLabel(q) {
        return { excellent: 'Excellent connection', good: 'Good connection', poor: 'Poor connection', lost: 'Connection lost' }[q] || 'Measuring connection…';
    },

    qualityBars(q) {
        return { excellent: 3, good: 2, poor: 1, lost: 0 }[q] ?? 0;
    },

    pin(tile) {
        this.pinnedId = this.pinnedId === tile.id ? null : tile.id;
        if (this.pinnedId) this.layout = 'speaker';
        this.$nextTick(() => this.attachVideos());
    },

    setLayout(layout) {
        this.layout = layout === 'grid' ? 'grid' : 'speaker';
        this.$nextTick(() => this.attachVideos());
    },

    toggleFullscreen() {
        const el = this.$refs.stageWrap;
        if (!el || !this.fullscreenSupported) return;
        if (document.fullscreenElement) document.exitFullscreen().catch(() => {});
        else el.requestFullscreen().catch(() => this.flash('Full screen is not available here.'));
    },

    async enableAudio() {
        try {
            await lk.room.startAudio();
            this.audioBlocked = false;
        } catch (e) {
            this.flash('Your browser still blocks sound. Click anywhere on the page and try again.');
        }
    },

    onData(payload, topic) {
        if (topic !== DATA_TOPIC) return;
        try {
            const msg = JSON.parse(new TextDecoder().decode(payload));
            // Signals only: the content always comes from Laravel.
            if (msg && (msg.t === 'feed' || msg.t === 'rights')) this.pollNow();
        } catch (e) { /* ignore malformed packets */ }
    },

    /** Tell everyone to refresh the feed now (a nudge — Laravel holds the data). */
    nudge(type = 'feed') {
        const room = lk.room;
        if (!room || this.lkState !== 'connected') return;
        try {
            const bytes = new TextEncoder().encode(JSON.stringify({ t: type }));
            room.localParticipant.publishData(bytes, { reliable: true, topic: DATA_TOPIC }).catch(() => {});
        } catch (e) { /* not connected */ }
    },

    applyPermissions(p) {
        this.permissions = { audio: !!p.audio, video: !!p.video, screen: !!p.screen };
    },

    /** The host changed our rights on the SFU: follow at once (Laravel is the source of truth). */
    onLocalPermissionsChanged(local) {
        const mod = lk.mod;
        const perms = local.permissions;
        if (!perms || !mod) return;

        const allowed = new Set((perms.canPublishSources || []).map(Number));
        const any = !!perms.canPublish;
        // TrackSource numbers: CAMERA 1, MICROPHONE 2, SCREEN_SHARE 3 (empty list = all sources).
        const can = (n) => any && (allowed.size === 0 || allowed.has(n));
        const before = { ...this.permissions };
        this.applyPermissions({ audio: can(2), video: can(1), screen: can(3) });

        if (!this.permissions.audio && local.isMicrophoneEnabled) local.setMicrophoneEnabled(false).catch(() => {});
        if (!this.permissions.video && local.isCameraEnabled) local.setCameraEnabled(false).catch(() => {});
        if (!this.permissions.screen && local.isScreenShareEnabled) local.setScreenShareEnabled(false).catch(() => {});

        if (!this.isManager) {
            if (!before.audio && this.permissions.audio) this.flash('The host allowed you to use your microphone.');
            else if (before.audio && !this.permissions.audio) this.flash('The host turned off your microphone.');
            else if (!before.screen && this.permissions.screen) this.flash('The host allowed you to share your screen.');
            else if (!before.video && this.permissions.video) this.flash('The host allowed you to use your camera.');
        }
        this.scheduleRefresh();
    },

    onDisconnected(room, reason) {
        if (lk.room !== room) return; // an old connection
        const mod = lk.mod;
        const R = mod ? mod.DisconnectReason : {};
        const intentional = lk.intentional;
        this.cleanupRoom();
        this.lkState = 'disconnected';
        this.stopPresence();

        if (intentional || ['removed', 'ended', 'error'].includes(this.state)) return;

        if (reason === R.PARTICIPANT_REMOVED) {
            this.enterRemoved();
            this.pollNow();
            return;
        }
        if (reason === R.ROOM_DELETED) {
            // The host ended the class (or a new session is starting) — the feed decides.
            this.state = 'prejoin';
            this.hasLeft = true;
            this.pollNow();
            return;
        }
        if (reason === R.DUPLICATE_IDENTITY) {
            this.state = 'prejoin';
            this.hasLeft = true;
            this.flash('You joined this class from another tab or device, so this window was disconnected.', 9000);
            return;
        }

        this.scheduleRejoin();
    },

    /** Network / server failure: fetch a fresh token and reconnect with backoff. */
    scheduleRejoin() {
        if (this.rejoinAttempts >= MAX_REJOIN_ATTEMPTS) {
            this.sendLeave(false);
            this.fail('Connection lost', 'We could not reconnect you to the class. Check your connection and press Retry.');
            return;
        }
        this.state = 'reconnecting';
        const wait = Math.min(15000, 1000 * Math.pow(2, this.rejoinAttempts));
        this.rejoinAttempts += 1;

        clearTimeout(this.rejoinTimer);
        this.rejoinTimer = setTimeout(async () => {
            if (this.state !== 'reconnecting') return;
            if (!this.online) { this.scheduleRejoin(); return; }

            const result = await this.requestToken(this.urls.token || this.urls.join);
            if (this.state !== 'reconnecting') return;
            if (result.network || (!result.ok && result.status >= 500)) { this.scheduleRejoin(); return; }
            if (!(await this.handleTokenResult(result))) return;

            const wasPublishing = { mic: this.media.mic, cam: this.media.cam };
            await this.connect(result.data.config, false);
            if (this.state === 'in_call') {
                this.flash('Reconnected to the class.');
                if (wasPublishing.mic && this.canUseMic) this.setMic(true, true);
                if (wasPublishing.cam && this.canUseCam) this.setCam(true, true);
            }
        }, wait);
    },

    cleanupRoom() {
        const room = lk.room;
        lk.room = null;
        if (lk.refreshFrame) cancelAnimationFrame(lk.refreshFrame);
        lk.refreshFrame = null;
        if (room) room.removeAllListeners();
        if (this.$refs.audioSink) this.$refs.audioSink.innerHTML = '';
        this.$root.querySelectorAll('video[data-tile-id]').forEach((el) => { el.srcObject = null; el._lkTrack = null; });
        this.tiles = [];
        this.remoteState = {};
        this.media = { mic: false, cam: false, screen: false };
        this.audioBlocked = false;
    },

    /** Close our SFU connection (intentional = no auto-reconnect). */
    disconnect(intentional = true) {
        const room = lk.room;
        if (!room) return;
        lk.intentional = intentional;
        this.cleanupRoom();
        room.disconnect(true).catch(() => {});
    },

    fail(title, text) {
        this.errorTitle = title;
        this.errorText = text || '';
        this.state = 'error';
    },

    async retry() {
        clearTimeout(this.rejoinTimer);
        this.disconnect(true);
        this.stopPresence();
        this.errorTitle = '';
        this.errorText = '';
        this.state = this.room.status === 'live' ? 'prejoin' : this.initialState();
        this.pollNow();
        if (this.room.status === 'live') await this.join();
    },

    enterRemoved() {
        clearTimeout(this.rejoinTimer);
        this.disconnect(true);
        this.stopPresence();
        this.state = 'removed';
        this.panelOpen = false;
    },

    // --- Devices -----------------------------------------------------------
    /** Friendly text for getUserMedia / getDisplayMedia failures. */
    deviceHelp(kind, e) {
        const mod = lk.mod;
        const failure = mod && mod.MediaDeviceFailure ? mod.MediaDeviceFailure.getFailure(e) : null;
        const name = String((e && (e.name || e.message)) || '');
        const noun = kind === 'camera' ? 'camera' : kind === 'screen' ? 'screen' : 'microphone';

        if (!window.isSecureContext || !navigator.mediaDevices) {
            return 'Camera and microphone need a secure (https://) connection. Open the class from its https:// address.';
        }
        if (failure === 'PermissionDenied' || /NotAllowed|Permission/i.test(name)) {
            return 'Your browser blocked the ' + noun + '. Click the camera/lock icon in the address bar, allow access, then try again.';
        }
        if (failure === 'NotFound' || /NotFound|DevicesNotFound/i.test(name)) {
            return 'No ' + noun + ' was found. Connect one, or check that it is switched on.';
        }
        if (failure === 'DeviceInUse' || /NotReadable|TrackStart|in use/i.test(name)) {
            return 'Your ' + noun + ' is being used by another app (Zoom, Teams, another tab). Close it and try again.';
        }
        if (/Overconstrained/i.test(name)) {
            return 'Your ' + noun + ' does not support the requested quality. Choose another device in Settings.';
        }
        if (/insufficient permissions|not allowed to publish/i.test(name)) {
            return 'The host has not allowed you to use your ' + noun + '.';
        }

        return 'The ' + noun + ' could not start. Close other apps that may be using it and try again.';
    },

    async setMic(on, quiet = false) {
        const room = lk.room;
        if (!room || this.mediaBusy.mic) return;
        if (on && !this.canUseMic) { if (!quiet) this.flash(this.mediaLockedText, 7000); return; }
        this.mediaBusy.mic = true;
        this.mediaError = '';
        try {
            await room.localParticipant.setMicrophoneEnabled(on);
        } catch (e) {
            this.mediaError = this.deviceHelp('microphone', e);
        } finally {
            this.mediaBusy.mic = false;
            this.scheduleRefresh();
        }
    },

    async setCam(on, quiet = false) {
        const room = lk.room;
        if (!room || this.mediaBusy.cam) return;
        if (on && !this.canUseCam) { if (!quiet) this.flash(this.mediaLockedText, 7000); return; }
        this.mediaBusy.cam = true;
        this.mediaError = '';
        try {
            await room.localParticipant.setCameraEnabled(on);
        } catch (e) {
            this.mediaError = this.deviceHelp('camera', e);
        } finally {
            this.mediaBusy.cam = false;
            this.scheduleRefresh();
        }
    },

    toggleMic() {
        if (!this.inCall) return;
        this.setMic(!this.media.mic);
    },

    toggleCamera() {
        if (!this.inCall) return;
        this.setCam(!this.media.cam);
    },

    async toggleShare() {
        const room = lk.room;
        if (!room || !this.inCall || this.mediaBusy.screen) return;
        if (!this.media.screen && !this.canShare) {
            this.flash('The host has not allowed participants to share their screen.', 7000);
            return;
        }
        if (!this.media.screen && !this.screenShareSupported) {
            this.flash('This browser cannot share the screen (most phones and tablets cannot). Use a computer.', 7000);
            return;
        }
        this.mediaBusy.screen = true;
        try {
            await room.localParticipant.setScreenShareEnabled(!this.media.screen, {
                audio: true, // tab / system audio where the browser supports it
                selfBrowserSurface: 'exclude',
                surfaceSwitching: 'include',
                systemAudio: 'include',
                contentHint: 'detail',
            });
        } catch (e) {
            // Closing the browser's "choose what to share" dialog is not an error.
            if (!/NotAllowed|Permission denied by user|AbortError/i.test(String(e && (e.name || e.message)))) {
                this.mediaError = this.deviceHelp('screen', e);
            }
        } finally {
            this.mediaBusy.screen = false;
            this.scheduleRefresh();
        }
    },

    async openDevices() {
        const mod = lk.mod || (await loadLiveKit().catch(() => null));
        if (!mod) return;
        this.devices.open = true;
        await this.loadDevices(mod);
    },

    async loadDevices(mod = lk.mod) {
        if (!mod) return;
        try {
            const [cams, mics, speakers] = await Promise.all([
                mod.Room.getLocalDevices('videoinput', false),
                mod.Room.getLocalDevices('audioinput', false),
                mod.Room.getLocalDevices('audiooutput', false).catch(() => []),
            ]);
            const plain = (list) => list.map((d, i) => ({ id: d.deviceId, label: d.label || ('Device ' + (i + 1)) }));
            this.devices.cams = plain(cams);
            this.devices.mics = plain(mics);
            this.devices.speakers = plain(speakers);
            const room = lk.room;
            if (room) {
                this.devices.cam = room.getActiveDevice('videoinput') || '';
                this.devices.mic = room.getActiveDevice('audioinput') || '';
                this.devices.speaker = room.getActiveDevice('audiooutput') || '';
            }
        } catch (e) {
            this.mediaError = this.deviceHelp('camera', e);
        }
    },

    async switchDevice(kind, id) {
        const room = lk.room;
        if (!room || !id) return;
        try {
            await room.switchActiveDevice(kind, id);
        } catch (e) {
            this.mediaError = this.deviceHelp(kind === 'videoinput' ? 'camera' : 'microphone', e);
        }
    },

    async onDevicesChanged() {
        const before = { cams: this.devices.cams.length, mics: this.devices.mics.length };
        await this.loadDevices();
        if (this.devices.mics.length < before.mics || this.devices.cams.length < before.cams) {
            this.flash('A camera or microphone was disconnected. Check Settings if your audio or video stopped.', 8000);
        }
    },

    // --- Presence & leave ----------------------------------------------------
    startPresence() {
        this.stopPresence();
        this.sendPresence();
        this.presenceTimer = setInterval(() => this.sendPresence(), this.cfg.presenceMs || 15000);
    },

    stopPresence() {
        clearInterval(this.presenceTimer);
        this.presenceTimer = null;
    },

    async sendPresence() {
        try {
            const response = await fetch(this.urls.presence, {
                method: 'POST',
                headers: jsonHeaders(),
                credentials: 'same-origin',
                body: JSON.stringify({}),
            });
            if (!response.ok) return;
            const data = await response.json();
            if (data.removed) {
                this.enterRemoved();
            } else if (data.status && data.status !== 'live') {
                this.room.status = data.status;
                this.syncState();
            }
        } catch (e) {
            // The feed poll reports connection problems.
        }
    },

    /** POST leave; with beacon=true use sendBeacon (page is going away). */
    sendLeave(beacon) {
        const form = new FormData();
        form.append('_token', csrfToken());

        if (beacon && navigator.sendBeacon) {
            navigator.sendBeacon(this.urls.leave, form);
            return;
        }

        fetch(this.urls.leave, {
            method: 'POST',
            body: form,
            credentials: 'same-origin',
            keepalive: true,
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        }).catch(() => {});
    },

    onPageHide() {
        if (['in_call', 'joining', 'reconnecting'].includes(this.state)) {
            this.sendLeave(true);
        }
        clearTimeout(this.rejoinTimer);
        this.disconnect(true);
        this.stopPresence();
    },

    leaveCall() {
        clearTimeout(this.rejoinTimer);
        this.disconnect(true);
        this.stopPresence();
        this.sendLeave(false);
        if (document.fullscreenElement) document.exitFullscreen().catch(() => {});
        this.state = this.room.status === 'live' ? 'prejoin' : 'ended';
        this.hasLeft = this.state === 'prejoin';
    },

    // --- Host: start / end ---------------------------------------------
    async post(url, body = {}) {
        const response = await fetch(url, {
            method: 'POST',
            headers: jsonHeaders(),
            credentials: 'same-origin',
            body: JSON.stringify(body),
        });
        const data = await readJson(response);

        return { ok: response.ok, status: response.status, data };
    },

    async startSession() {
        if (!this.isManager || !this.urls.studio || this.busy.start) return;
        this.busy.start = true;
        try {
            const { ok, data } = await this.post(this.urls.studio.start);
            if (!ok) {
                this.flash(errorMessage(data, 'The session could not be started.'), 7000);
                return;
            }
            this.room.status = 'live';
            this.room.started_at = this.room.started_at || new Date().toISOString();
            this.hasLeft = false;
            this.state = 'prejoin';
            this.pollNow();
            await this.join();
        } catch (e) {
            this.flash('Could not reach the server. Try again.', 7000);
        } finally {
            this.busy.start = false;
        }
    },

    askEnd() {
        this.openConfirm('end', 'End the class for everyone?',
            'Everyone will be disconnected and the class will be marked as completed. Attendance is saved.', 'End class');
    },

    async endSession() {
        if (!this.isManager || !this.urls.studio || this.busy.end) return;
        this.busy.end = true;
        try {
            const { ok, data } = await this.post(this.urls.studio.end);
            if (!ok) {
                this.flash(errorMessage(data, 'The session could not be ended.'), 7000);
                return;
            }
            this.room.status = 'completed';
            this.disconnect(true);
            this.stopPresence();
            this.state = 'ended';
            this.pollNow();
        } catch (e) {
            this.flash('Could not reach the server. Try again.', 7000);
        } finally {
            this.busy.end = false;
        }
    },

    // --- Host: moderation (Laravel decides, the SFU executes) -----------------
    async moderate(key, url, body, success) {
        if (!this.isManager || !url || this.busy.mod) return null;
        this.busy.mod = key;
        try {
            const { ok, data } = await this.post(url, body);
            if (!ok) {
                this.flash(errorMessage(data, 'That action could not be completed.'), 7000);
                return null;
            }
            if (success) this.flash(typeof success === 'function' ? success(data) : success);
            this.nudge('rights');
            this.pollNow();
            return data || {};
        } catch (e) {
            this.flash('Could not reach the server. Try again.', 7000);
            return null;
        } finally {
            this.busy.mod = null;
        }
    },

    async toggleLock() {
        const data = await this.moderate('lock', this.urls.studio && this.urls.studio.lock, { locked: !this.room.is_locked },
            (d) => d.message || '');
        if (data) this.room.is_locked = !!data.is_locked;
    },

    async setRoomMedia(key, value) {
        const data = await this.moderate('media-' + key, this.urls.studio && this.urls.studio.media, { [key]: !!value },
            'Participant settings updated.');
        if (data) {
            this.room.allow_participant_media = !!data.allow_participant_media;
            this.room.allow_screen_share = !!data.allow_screen_share;
        }
    },

    muteEveryone(kind) {
        return this.moderate('mute-all', this.urls.studio && this.urls.studio.muteAll, { kind }, (d) => d.message || 'Done.');
    },

    muteParticipant(p, kind) {
        const url = this.urls.studio && this.urls.studio.mute.replace('__ID__', p.user_id);
        return this.moderate('mute-' + p.user_id, url, { kind }, p.name + ' was muted.');
    },

    setParticipantRight(p, key, value) {
        const url = this.urls.studio && this.urls.studio.permissions.replace('__ID__', p.user_id);
        return this.moderate('rights-' + p.user_id, url, { [key]: value }, 'Updated what ' + p.name + ' may use.');
    },

    /** Back to the room-wide switches (null = no personal override). */
    resetParticipantRights(p) {
        const url = this.urls.studio && this.urls.studio.permissions.replace('__ID__', p.user_id);
        return this.moderate('rights-' + p.user_id, url, { audio: null, video: null, screen: null },
            p.name + ' now follows the room settings.');
    },

    async toggleRecording() {
        if (!this.isManager || !this.provider.supportsRecording || this.recordingBusy) return;
        this.recordingBusy = true;
        const url = this.room.is_recording ? this.urls.studio.recordingStop : this.urls.studio.recordingStart;
        const data = await this.moderate('recording', url, {}, (d) => d.message || '');
        if (data) this.room.is_recording = !!data.is_recording;
        this.recordingBusy = false;
    },

    // --- Materials -------------------------------------------------------
    async uploadMaterial(event) {
        const input = event.target.querySelector('input[type=file]');
        const file = input && input.files && input.files[0];
        this.upload.error = '';
        if (!file || !this.urls.studio || this.busy.upload) return;

        const maxBytes = (this.cfg.maxMaterialMb || 50) * 1024 * 1024;
        if (file.size > maxBytes) {
            this.upload.error = 'Files can be at most ' + (this.cfg.maxMaterialMb || 50) + ' MB.';
            return;
        }

        const form = new FormData();
        form.append('file', file);
        if (this.upload.title.trim()) form.append('title', this.upload.title.trim());

        this.busy.upload = true;
        try {
            const response = await fetch(this.urls.studio.materials, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                body: form,
            });
            const data = await readJson(response);
            if (!response.ok) {
                this.upload.error = errorMessage(data, 'The file could not be uploaded.');
                return;
            }
            if (data && data.material) this.materials = [data.material, ...this.materials.filter((m) => m.id !== data.material.id)];
            this.upload.title = '';
            input.value = '';
            this.flash('File shared with the class.');
            this.nudge('feed');
        } catch (e) {
            this.upload.error = 'Could not reach the server. Try again.';
        } finally {
            this.busy.upload = false;
        }
    },

    async deleteMaterial(material) {
        if (!this.urls.studio || !material) return;
        try {
            const response = await fetch(this.urls.studio.materialDestroy.replace('__ID__', material.id), {
                method: 'DELETE',
                headers: jsonHeaders(),
                credentials: 'same-origin',
            });
            if (!response.ok) {
                this.flash(errorMessage(await readJson(response), 'The file could not be removed.'));
                return;
            }
            this.materials = this.materials.filter((m) => m.id !== material.id);
            this.nudge('feed');
        } catch (e) {
            this.flash('Could not reach the server. Try again.');
        }
    },

    // --- Messages ------------------------------------------------------
    async send(kind) {
        const draftKey = kind === 'question' ? 'question' : 'chat';
        const body = (this.drafts[draftKey] || '').trim();
        this.composerError[draftKey] = '';
        if (!body || this.sending[draftKey]) return;

        const max = this.cfg.maxMessageLength || 2000;
        if (body.length > max) {
            this.composerError[draftKey] = 'Messages may not be longer than ' + max + ' characters.';
            return;
        }

        const announce = draftKey === 'chat' && this.announceMode && this.isManager && this.urls.studio;
        this.sending[draftKey] = true;

        try {
            const response = await fetch(announce ? this.urls.studio.announce : this.urls.messages.store, {
                method: 'POST',
                headers: jsonHeaders(),
                credentials: 'same-origin',
                body: JSON.stringify(announce ? { body } : { type: draftKey, body }),
            });
            const data = await readJson(response);

            if (!response.ok) {
                this.composerError[draftKey] = response.status === 429
                    ? 'You are sending messages too quickly. Wait a moment.'
                    : errorMessage(data, 'Your message could not be sent.');
                return;
            }

            this.drafts[draftKey] = '';
            if (data && data.id && data.type) {
                this.upsertMessage(data, false);
            } else if (data && data.message && data.message.id) {
                this.upsertMessage(data.message, false);
            }
            if (announce) this.announceMode = false;
            this.nudge('feed'); // everyone else fetches it right away
            this.pollNow();
            this.$nextTick(() => this.scrollToBottom(true));
        } catch (e) {
            this.composerError[draftKey] = 'Could not reach the server. Check your connection and try again.';
        } finally {
            this.sending[draftKey] = false;
        }
    },

    onComposerKey(e, kind) {
        if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) {
            e.preventDefault();
            this.send(kind);
        }
    },

    async answer(message) {
        if (!message.can_answer || this.busy.message) return;
        this.busy.message = message.id;
        try {
            const { ok, data } = await this.post(this.urls.messages.answer.replace('__ID__', message.id));
            if (!ok) {
                this.flash(errorMessage(data, 'The question could not be updated.'));
                return;
            }
            if (data && data.id) this.upsertMessage(data, false);
            this.nudge('feed');
        } catch (e) {
            this.flash('Could not reach the server. Try again.');
        } finally {
            this.busy.message = null;
        }
    },

    askDelete(message) {
        this.openConfirm('delete', 'Delete this message?',
            'It will be replaced with “Message removed” for everyone in the class.', 'Delete', message);
    },

    async deleteMessage(message) {
        if (!message || !message.can_delete || this.busy.message) return;
        this.busy.message = message.id;
        try {
            const response = await fetch(this.urls.messages.destroy.replace('__ID__', message.id), {
                method: 'DELETE',
                headers: jsonHeaders(),
                credentials: 'same-origin',
            });
            const data = await readJson(response);
            if (!response.ok) {
                this.flash(errorMessage(data, 'The message could not be deleted.'));
                return;
            }
            if (data && data.id) this.upsertMessage(data, false);
            this.nudge('feed');
        } catch (e) {
            this.flash('Could not reach the server. Try again.');
        } finally {
            this.busy.message = null;
        }
    },

    // --- People ----------------------------------------------------------
    /** Rights and removal: participants only (hosts and room managers keep full control). */
    canModerate(p) {
        return this.canMute(p) && p.role !== 'host';
    },

    /** Server-side mute of someone's mic / camera / screen: anyone but yourself, co-hosts included. */
    canMute(p) {
        return this.isManager && !!this.urls.studio && !p.is_me && this.room.status === 'live';
    },

    askRemove(p) {
        this.openConfirm('remove', 'Remove ' + p.name + ' from the class?',
            'They will be disconnected and cannot rejoin this session. They can still watch shared recordings later.', 'Remove', p);
    },

    async removeParticipant(p) {
        if (!this.canModerate(p) || this.busy.remove) return;
        this.busy.remove = p.user_id;
        try {
            const { ok, data } = await this.post(this.urls.studio.remove.replace('__ID__', p.user_id));
            if (!ok) {
                this.flash(errorMessage(data, 'The participant could not be removed.'), 7000);
                return;
            }
            this.participants = this.participants.filter((x) => x.user_id !== p.user_id);
            this.flash(p.name + ' was removed from the class.');
            this.pollNow();
        } catch (e) {
            this.flash('Could not reach the server. Try again.', 7000);
        } finally {
            this.busy.remove = null;
        }
    },

    pinParticipant(p) {
        const tile = this.tiles.find((t) => t.identity === p.identity && t.source === 'camera');
        if (tile) this.pin(tile);
        else this.flash(p.name + ' is not connected to the video yet.');
    },

    // --- Confirm dialog --------------------------------------------------
    openConfirm(kind, title, body, confirmLabel, payload = null) {
        this.confirm = { open: true, kind, title, body, confirmLabel, payload };
        this.$nextTick(() => this.$refs.confirmCancel && this.$refs.confirmCancel.focus());
    },

    closeConfirm() {
        this.confirm.open = false;
    },

    runConfirm() {
        const { kind, payload } = this.confirm;
        this.confirm.open = false;
        if (kind === 'end') this.endSession();
        else if (kind === 'delete') this.deleteMessage(payload);
        else if (kind === 'remove') this.removeParticipant(payload);
        else if (kind === 'material') this.deleteMaterial(payload);
    },
};
};
