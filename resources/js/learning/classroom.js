/**
 * Live classroom page (learn.rooms.live): Jitsi IFrame API stage, our own
 * control bar, feed/presence polling, chat, Q&A, people and host controls.
 *
 * Used as x-data="learnClassroom(@js($config))". Config (LiveRoomController::classroomConfig):
 *   urls: { room, rooms, join, presence, leave, feed,
 *           messages: { store, answer (__ID__), destroy (__ID__) },
 *           studio?: { show, start, end, announce, remove (__ID__) } }   // managers only
 *   room: { id, title, status, status_label, started_at, scheduled_at, scheduled_label, duration_minutes,
 *           allow_participant_media, chat_enabled, questions_enabled, cancel_reason }
 *   viewer: { id, name, is_manager }
 *   pollMs, presenceMs, maxMessageLength
 *   provider: { label, supportsRecording, isDemo, demoWarning }
 *
 * Jitsi IFrame API names used here are the verified ones (commands: toggleAudio,
 * toggleVideo, toggleShareScreen, hangup, muteEveryone, kickParticipant,
 * startRecording, stopRecording, toggleParticipantsPane, toggleModeration,
 * endConference; events: videoConferenceJoined, videoConferenceLeft, readyToClose,
 * participantJoined, participantLeft, participantKickedOut, audioMuteStatusChanged,
 * videoMuteStatusChanged, screenSharingStatusChanged, recordingStatusChanged,
 * errorOccurred, cameraError, micError, suspendDetected, participantRoleChanged).
 *
 * All user-generated text is rendered with x-text by the template.
 */

const SCRIPT_TIMEOUT_MS = 20000;
const MAX_BACKOFF_MS = 60000;
const scriptLoads = {};

function loadExternalApi(url) {
    if (window.JitsiMeetExternalAPI) {
        return Promise.resolve(window.JitsiMeetExternalAPI);
    }
    if (scriptLoads[url]) {
        return scriptLoads[url];
    }

    scriptLoads[url] = new Promise((resolve, reject) => {
        const script = document.createElement('script');
        let settled = false;
        const fail = (message) => {
            if (settled) return;
            settled = true;
            script.remove();
            delete scriptLoads[url];
            reject(new Error(message));
        };
        const timer = setTimeout(() => fail('The video service took too long to load.'), SCRIPT_TIMEOUT_MS);

        script.src = url;
        script.async = true;
        script.onload = () => {
            clearTimeout(timer);
            if (window.JitsiMeetExternalAPI) {
                settled = true;
                resolve(window.JitsiMeetExternalAPI);
            } else {
                fail('The video service did not start correctly.');
            }
        };
        script.onerror = () => {
            clearTimeout(timer);
            fail('The video service could not be reached. Check your connection or any content blocker.');
        };
        document.head.appendChild(script);
    });

    return scriptLoads[url];
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

window.learnClassroom = (cfg = {}) => {
    // The Jitsi API object stays outside Alpine's reactive proxy on purpose.
    const jitsi = { api: null };

    return {
    cfg,
    urls: cfg.urls || {},
    room: { ...(cfg.room || {}) },
    viewer: cfg.viewer || {},
    provider: cfg.provider || {},
    isManager: !!(cfg.viewer && cfg.viewer.is_manager),

    // waiting | prejoin | joining | in_call | ended | removed | error
    state: 'waiting',
    hasLeft: false,
    errorTitle: '',
    errorText: '',
    notice: '',
    noticeTimer: null,
    mediaError: '',

    // Jitsi
    hasApi: false,
    joinConfig: null,
    localJitsiId: null,
    jitsiReconnecting: false,
    intentionalHangup: false,
    moderationApplied: false,
    media: { audioMuted: true, videoMuted: true, sharing: false, recording: false },
    recordingBusy: false,

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
    panelOpen: false,
    tab: 'chat',
    unread: { chat: 0, qa: 0 },
    qaFilter: 'all',
    drafts: { chat: '', question: '' },
    announceMode: false,
    sending: { chat: false, question: false },
    composerError: { chat: '', question: '' },
    busy: { start: false, end: false, remove: null, message: null },
    confirm: { open: false, kind: '', title: '', body: '', confirmLabel: '', payload: null },
    elapsed: '',
    clockTimer: null,

    init() {
        const lg = window.matchMedia('(min-width: 1024px)');
        this.isLg = lg.matches;
        const onLg = (e) => { this.isLg = e.matches; if (e.matches) this.panelOpen = false; };
        lg.addEventListener ? lg.addEventListener('change', onLg) : lg.addListener(onLg);

        this.state = this.initialState();
        this.tick();
        this.clockTimer = setInterval(() => this.tick(), 1000);

        window.addEventListener('online', () => { this.online = true; this.pollNow(); });
        window.addEventListener('offline', () => { this.online = false; });
        window.addEventListener('pagehide', () => this.onPageHide());
        // Restored from the back/forward cache: the call was torn down on pagehide.
        window.addEventListener('pageshow', (e) => {
            if (!e.persisted) return;
            if (['in_call', 'joining'].includes(this.state)) {
                this.state = this.room.status === 'live' ? 'prejoin' : 'ended';
                this.hasLeft = this.state === 'prejoin';
            }
            this.pollNow();
        });

        this.poll();
    },

    destroy() {
        clearInterval(this.clockTimer);
        clearTimeout(this.feedTimer);
        this.stopPresence();
        this.disposeApi();
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

    get canUseMedia() {
        return this.isManager || !!this.room.allow_participant_media;
    },

    get mediaLockedText() {
        return 'The host has turned off participant microphones and cameras for this class. You can still watch, chat and ask questions.';
    },

    get connection() {
        if (!this.online) return { key: 'offline', label: 'Offline' };
        if (this.feedFailures >= 2 || this.jitsiReconnecting) return { key: 'reconnecting', label: 'Reconnecting' };
        if (this.state === 'joining' || (!this.feedLoaded && this.feedFailures === 0)) return { key: 'connecting', label: 'Connecting' };

        return { key: 'connected', label: 'Connected' };
    },

    get showConnectionBanner() {
        return !this.online || this.feedFailures >= 2;
    },

    get panelVisible() {
        return this.isLg || this.panelOpen;
    },

    get announcements() {
        return this.messages.filter((m) => m.type === 'announcement' && !m.is_deleted);
    },

    get chatMessages() {
        return this.messages.filter((m) => m.type === 'chat');
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
                this.endCall('error');
                this.fail('Your session expired', 'Reload the page and sign in again to continue.');
                return;
            }
            if (response.status === 403 || response.status === 404) {
                this.feedStopped = true;
                this.endCall('error');
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
            if (this.state === 'waiting' || (this.state === 'ended' && !jitsi.api)) {
                this.hasLeft = false;
                this.state = 'prejoin';
            }
            return;
        }

        if (['prejoin', 'joining', 'in_call'].includes(this.state)) {
            this.endCall('ended');
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
            const tabFor = bucket === 'qa' ? 'qa' : 'chat';
            if (!this.panelVisible || this.tab !== tabFor) this.unread[bucket] += 1;
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
        if (!this.isLg) this.panelOpen = true;
        if (name === 'chat') this.unread.chat = 0;
        if (name === 'qa') this.unread.qa = 0;
        this.$nextTick(() => this.scrollToBottom(true));
    },

    togglePanel(name) {
        if (!this.isLg && this.panelOpen && this.tab === name) {
            this.panelOpen = false;
            return;
        }
        this.openTab(name);
    },

    closePanel() {
        this.panelOpen = false;
    },

    // --- Joining -----------------------------------------------------------
    async join() {
        if (this.state === 'joining' || this.state === 'in_call') return;
        this.state = 'joining';
        this.hasLeft = false;
        this.mediaError = '';

        let response;
        let data;
        try {
            response = await fetch(this.urls.join, {
                method: 'POST',
                headers: jsonHeaders(),
                credentials: 'same-origin',
                body: JSON.stringify({}),
            });
            data = await readJson(response);
        } catch (e) {
            this.fail('Could not reach the classroom', 'Check your internet connection and try again.');
            return;
        }

        if (!response.ok) {
            const reason = data && data.reason;
            if (reason === 'removed') return this.enterRemoved();
            if (reason === 'not_live') {
                this.flash(errorMessage(data, 'This live session is not running right now.'));
                this.state = ['completed', 'cancelled'].includes(this.room.status) ? 'ended' : 'waiting';
                this.pollNow();
                return;
            }
            if (response.status === 419) return this.fail('Your session expired', 'Reload the page and try again.');
            if (response.status === 429) return this.fail('Too many attempts', 'Please wait a minute before trying again.');
            if (reason === 'provider_unavailable') return this.fail('Live video is unavailable', errorMessage(data, 'The live class provider is not available.'));

            return this.fail('You cannot join this session', errorMessage(data, 'Something went wrong. Please try again.'));
        }

        this.joinConfig = data.config;

        try {
            await loadExternalApi(data.config.scriptUrl);
        } catch (e) {
            this.sendLeave(false);
            this.fail('The video service did not load', e.message);
            return;
        }

        // The room may have ended while the script was loading.
        if (this.state !== 'joining') return;

        try {
            this.createApi(data.config);
        } catch (e) {
            this.sendLeave(false);
            this.fail('The video call could not start', e && e.message ? e.message : 'Please try again.');
        }
    },

    createApi(config) {
        this.disposeApi();
        const node = this.$refs.stage;
        node.innerHTML = '';

        const options = {
            roomName: config.roomName,
            parentNode: node,
            width: '100%',
            height: '100%',
            userInfo: {
                displayName: (config.user && config.user.name) || this.viewer.name,
                email: (config.user && config.user.email) || undefined,
            },
            configOverwrite: config.configOverwrite || {},
            interfaceConfigOverwrite: config.interfaceConfigOverwrite || {},
        };
        if (config.jwt) options.jwt = config.jwt;

        this.media = {
            audioMuted: !!(options.configOverwrite.startWithAudioMuted),
            videoMuted: !!(options.configOverwrite.startWithVideoMuted),
            sharing: false,
            recording: false,
        };
        this.moderationApplied = false;
        this.intentionalHangup = false;

        const api = new window.JitsiMeetExternalAPI(config.domain, options);
        jitsi.api = api;
        this.hasApi = true;

        api.addListener('videoConferenceJoined', (e) => {
            this.localJitsiId = e && e.id ? String(e.id) : null;
            this.state = 'in_call';
            this.jitsiReconnecting = false;
            this.startPresence();
            this.applyModeration();
            this.pollNow();
        });
        api.addListener('videoConferenceLeft', () => this.onConferenceLeft());
        api.addListener('readyToClose', () => this.onConferenceLeft());
        api.addListener('participantJoined', () => this.refreshSoon());
        api.addListener('participantLeft', () => this.refreshSoon());
        api.addListener('participantKickedOut', (e) => {
            if (e && e.kicked && e.kicked.local) this.enterRemoved();
            else this.refreshSoon();
        });
        api.addListener('participantRoleChanged', (e) => {
            if (e && e.role === 'moderator' && String(e.id) === String(this.localJitsiId)) {
                this.moderationApplied = false;
                this.applyModeration();
            }
        });
        api.addListener('audioMuteStatusChanged', (e) => { this.media.audioMuted = !!(e && e.muted); });
        api.addListener('videoMuteStatusChanged', (e) => { this.media.videoMuted = !!(e && e.muted); });
        api.addListener('screenSharingStatusChanged', (e) => { this.media.sharing = !!(e && e.on); });
        api.addListener('recordingStatusChanged', (e) => {
            this.recordingBusy = false;
            if (!e || (e.mode && e.mode !== 'file')) return;
            this.media.recording = !!e.on;
            if (e.error) this.flash('Recording problem: ' + e.error, 8000);
            else this.flash(e.on ? 'Recording started.' : 'Recording stopped.');
        });
        api.addListener('cameraError', (e) => { this.mediaError = this.deviceHelp('camera', e); });
        api.addListener('micError', (e) => { this.mediaError = this.deviceHelp('microphone', e); });
        api.addListener('suspendDetected', () => {
            this.jitsiReconnecting = true;
            this.flash('Your device went to sleep — reconnecting to the class…');
        });
        api.addListener('errorOccurred', (e) => {
            if (e && e.isFatal) {
                this.disposeApi();
                this.stopPresence();
                this.sendLeave(false);
                this.fail('The call was interrupted', (e && e.message) ? String(e.message) : 'The connection to the video call was lost.');
            } else if (e && e.name && String(e.name).indexOf('connection') !== -1) {
                this.jitsiReconnecting = true;
            }
        });
    },

    /** Participants cannot unmute themselves when the host disabled their media. */
    applyModeration() {
        if (!jitsi.api || !this.isManager || this.room.allow_participant_media || this.moderationApplied) return;
        try {
            jitsi.api.executeCommand('toggleModeration', true, 'audio');
            jitsi.api.executeCommand('toggleModeration', true, 'video');
            this.moderationApplied = true;
        } catch (e) {
            // Not a moderator (yet) — retried on participantRoleChanged.
        }
    },

    deviceHelp(kind, e) {
        const type = String((e && (e.type || e.name)) || '').toLowerCase();
        const noun = kind === 'camera' ? 'camera' : 'microphone';
        if (type.includes('permission')) {
            return 'Your browser blocked the ' + noun + '. Click the camera/lock icon in the address bar, allow access, then try again.';
        }
        if (type.includes('not_found') || type.includes('notfound') || type.includes('not found')) {
            return 'No ' + noun + ' was found. Connect one, or check that it is not switched off.';
        }
        if (type.includes('constraint') || type.includes('resolution')) {
            return 'Your ' + noun + ' does not support the requested quality. Try another device in the call settings.';
        }

        return 'The ' + noun + ' could not start. Close other apps that may be using it (Zoom, Teams, another tab) and try again.';
    },

    refreshTimer: null,
    refreshSoon() {
        clearTimeout(this.refreshTimer);
        this.refreshTimer = setTimeout(() => this.pollNow(), 1200);
    },

    onConferenceLeft() {
        if (!jitsi.api) return;
        const intentional = this.intentionalHangup;
        this.disposeApi();
        this.stopPresence();

        if (this.state === 'removed' || this.state === 'ended' || this.state === 'error') return;

        this.sendLeave(false);
        if (this.room.status !== 'live') {
            this.state = 'ended';
        } else {
            this.state = 'prejoin';
            this.hasLeft = true;
            if (!intentional) this.flash('You left the call.');
        }
    },

    disposeApi() {
        if (!jitsi.api) return;
        const api = jitsi.api;
        jitsi.api = null;
        this.hasApi = false;
        this.localJitsiId = null;
        this.jitsiReconnecting = false;
        try { api.dispose(); } catch (e) { /* already gone */ }
        if (this.$refs.stage) this.$refs.stage.innerHTML = '';
    },

    /** End our part of the call and show a final screen. */
    endCall(nextState) {
        this.intentionalHangup = true;
        if (jitsi.api) {
            try { jitsi.api.executeCommand('hangup'); } catch (e) { /* ignore */ }
        }
        this.disposeApi();
        this.stopPresence();
        this.state = nextState;
    },

    enterRemoved() {
        this.endCall('removed');
        this.panelOpen = false;
    },

    fail(title, text) {
        this.errorTitle = title;
        this.errorText = text || '';
        this.state = 'error';
    },

    async retry() {
        this.disposeApi();
        this.stopPresence();
        this.errorTitle = '';
        this.errorText = '';
        this.state = this.room.status === 'live' ? 'prejoin' : this.initialState();
        this.pollNow();
        if (this.room.status === 'live') await this.join();
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
            const body = {};
            if (this.localJitsiId && /^[A-Za-z0-9_-]{1,64}$/.test(this.localJitsiId)) body.jitsi_id = this.localJitsiId;
            const response = await fetch(this.urls.presence, {
                method: 'POST',
                headers: jsonHeaders(),
                credentials: 'same-origin',
                body: JSON.stringify(body),
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
        if (this.state === 'in_call' || this.state === 'joining') {
            this.sendLeave(true);
        }
        this.intentionalHangup = true;
        this.disposeApi();
        this.stopPresence();
    },

    leaveCall() {
        this.intentionalHangup = true;
        if (jitsi.api) {
            try { jitsi.api.executeCommand('hangup'); } catch (e) { /* ignore */ }
        }
        this.disposeApi();
        this.stopPresence();
        this.sendLeave(false);
        this.state = this.room.status === 'live' ? 'prejoin' : 'ended';
        this.hasLeft = this.state === 'prejoin';
    },

    // --- Controls ------------------------------------------------------
    command(name, ...args) {
        if (!jitsi.api) {
            this.flash('Join the call first.');
            return false;
        }
        try {
            jitsi.api.executeCommand(name, ...args);
            return true;
        } catch (e) {
            this.flash('That action is not available right now.');
            return false;
        }
    },

    toggleMic() {
        if (!this.canUseMedia) return this.flash(this.mediaLockedText, 7000);
        this.mediaError = '';
        this.command('toggleAudio');
    },

    toggleCamera() {
        if (!this.canUseMedia) return this.flash(this.mediaLockedText, 7000);
        this.mediaError = '';
        this.command('toggleVideo');
    },

    toggleShare() {
        if (!this.canUseMedia) return this.flash(this.mediaLockedText, 7000);
        this.command('toggleShareScreen');
    },

    toggleRecording() {
        if (!this.isManager || !this.provider.supportsRecording || this.recordingBusy) return;
        this.recordingBusy = true;
        const ok = this.media.recording
            ? this.command('stopRecording', 'file')
            : this.command('startRecording', { mode: 'file' });
        if (!ok) this.recordingBusy = false;
        setTimeout(() => { this.recordingBusy = false; }, 10000);
    },

    muteEveryone(kind) {
        if (!this.isManager) return;
        if (this.command('muteEveryone', kind)) {
            this.flash(kind === 'audio' ? 'Everyone else was muted.' : 'Everyone else’s camera was turned off.');
        }
    },

    openAdvanced() {
        if (!this.isManager) return;
        this.command('toggleParticipantsPane', true);
    },

    // --- Host: start / end ---------------------------------------------
    async startSession() {
        if (!this.isManager || !this.urls.studio || this.busy.start) return;
        this.busy.start = true;
        try {
            const response = await fetch(this.urls.studio.start, {
                method: 'POST',
                headers: jsonHeaders(),
                credentials: 'same-origin',
                body: JSON.stringify({}),
            });
            if (!response.ok) {
                const data = await readJson(response);
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
        this.openConfirm('end', 'End the session for everyone?',
            'Everyone will be disconnected and the class will be marked as completed. Attendance is saved.', 'End session');
    },

    async endSession() {
        if (!this.isManager || !this.urls.studio || this.busy.end) return;
        this.busy.end = true;
        try {
            const response = await fetch(this.urls.studio.end, {
                method: 'POST',
                headers: jsonHeaders(),
                credentials: 'same-origin',
                body: JSON.stringify({}),
            });
            if (!response.ok) {
                const data = await readJson(response);
                this.flash(errorMessage(data, 'The session could not be ended.'), 7000);
                return;
            }
            if (jitsi.api) {
                try { jitsi.api.executeCommand('endConference'); } catch (e) { /* not moderator */ }
            }
            this.room.status = 'completed';
            this.endCall('ended');
            this.pollNow();
        } catch (e) {
            this.flash('Could not reach the server. Try again.', 7000);
        } finally {
            this.busy.end = false;
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
            const response = await fetch(this.urls.messages.answer.replace('__ID__', message.id), {
                method: 'POST',
                headers: jsonHeaders(),
                credentials: 'same-origin',
                body: JSON.stringify({}),
            });
            const data = await readJson(response);
            if (!response.ok) {
                this.flash(errorMessage(data, 'The question could not be updated.'));
                return;
            }
            if (data && data.id) this.upsertMessage(data, false);
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
        } catch (e) {
            this.flash('Could not reach the server. Try again.');
        } finally {
            this.busy.message = null;
        }
    },

    // --- People ----------------------------------------------------------
    canRemove(p) {
        return this.isManager && !!this.urls.studio && !p.is_me && p.role !== 'host' && this.room.status === 'live';
    },

    askRemove(p) {
        this.openConfirm('remove', 'Remove ' + p.name + ' from the session?',
            'They will be disconnected and cannot rejoin this session. They can still watch shared recordings later.', 'Remove', p);
    },

    async removeParticipant(p) {
        if (!this.canRemove(p) || this.busy.remove) return;
        this.busy.remove = p.user_id;
        try {
            const response = await fetch(this.urls.studio.remove.replace('__ID__', p.user_id), {
                method: 'POST',
                headers: jsonHeaders(),
                credentials: 'same-origin',
                body: JSON.stringify({}),
            });
            const data = await readJson(response);
            if (!response.ok) {
                this.flash(errorMessage(data, 'The participant could not be removed.'), 7000);
                return;
            }
            const jitsiId = (data && (data.jitsi_id || data.jitsi_participant_id)) || p.jitsi_id;
            if (jitsiId && jitsi.api) {
                try { jitsi.api.executeCommand('kickParticipant', String(jitsiId)); } catch (e) { /* ignore */ }
            }
            this.participants = this.participants.filter((x) => x.user_id !== p.user_id);
            this.flash(p.name + ' was removed from the session.');
            this.refreshSoon();
        } catch (e) {
            this.flash('Could not reach the server. Try again.', 7000);
        } finally {
            this.busy.remove = null;
        }
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
    },
};
};
