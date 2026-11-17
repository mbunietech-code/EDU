/*
 * MbunieEduHub call service worker: makes a support admin's computer ring for
 * video help calls even when nobody is signed in. The server sends an empty
 * Web Push; this worker asks /call-devices/check (identified by its own push
 * endpoint) what is ringing and shows a system notification.
 */
const base = self.registration.scope;
const fallbackUrl = new URL('admin/video-help', base).href;

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));

self.addEventListener('push', (event) => event.waitUntil(ring()));

async function ring() {
    let data = null;
    try {
        const sub = await self.registration.pushManager.getSubscription();
        const res = await fetch(new URL('call-devices/check', base), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            credentials: 'omit',
            body: JSON.stringify({ endpoint: sub ? sub.endpoint : '' }),
        });
        if (res.ok) data = await res.json();
    } catch (e) {
        // offline or server down: still ring, without the caller's name
    }

    const url = (data && data.url) || fallbackUrl;

    if (data && data.test) {
        return show('Test ring: MbunieEduHub', 'This device will ring when a customer calls for video help.', 'call-test', url, false);
    }

    const calls = (data && Array.isArray(data.calls)) ? data.calls : [];
    if (!calls.length) {
        return show('Video help call', data
            ? 'A customer called for video help. The call is no longer ringing.'
            : 'A customer is calling for video help. Open MbunieEduHub to answer.', 'video-help', url, !data);
    }

    return Promise.all(calls.map((c) => show(
        'Video help call',
        c.name + ' is calling for video help' + (c.reason ? ': ' + c.reason : '') + '. Sign in to answer.',
        'video-help-' + c.id, url, true,
    )));
}

function show(title, body, tag, url, ringing) {
    return self.registration.showNotification(title, {
        body,
        tag,
        renotify: true,
        requireInteraction: ringing,
        silent: false,
        vibrate: [500, 250, 500, 250, 500],
        icon: new URL('favicon.ico', base).href,
        data: { url },
    });
}

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = (event.notification.data && event.notification.data.url) || fallbackUrl;
    event.waitUntil((async () => {
        const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        for (const w of windows) {
            if (w.url.startsWith(base) && 'focus' in w) {
                if ('navigate' in w) { try { await w.navigate(url); } catch (e) {} }
                return w.focus();
            }
        }
        return self.clients.openWindow(url);
    })());
});
