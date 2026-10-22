// TheBoise app: phone notifications, and a friendly page when there's no connection.
// Pages are never kept on the phone (they're private and must always be fresh)
const CACHE = 'theboise-v1';
const OFFLINE = '/offline.html';

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE).then((cache) => cache.addAll([OFFLINE, '/icons/icon-192.png'])));
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== CACHE).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

// Only page loads: from the network, and the offline page if there's none
self.addEventListener('fetch', (event) => {
    if (event.request.mode !== 'navigate') return;
    event.respondWith(fetch(event.request).catch(() => caches.match(OFFLINE)));
});

// A notification from the server: { title, body, url, tag, image }
self.addEventListener('push', (event) => {
    let data = {};
    try {
        data = event.data ? event.data.json() : {};
    } catch {
        data = { body: event.data?.text() };
    }
    event.waitUntil(self.registration.showNotification(data.title || 'TheBoise', {
        body: data.body || '',
        icon: '/icons/icon-192.png',
        badge: '/icons/badge-96.png',
        image: data.image || undefined,
        // Same tag = replaces the previous one (e.g. several comments on one album), still buzzing
        tag: data.tag || undefined,
        renotify: !!data.tag,
        data: { url: data.url || '/' },
    }));
});

// Tapped: open that page, in the app if it's already open
self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = new URL(event.notification.data?.url || '/', self.location.origin).href;
    event.waitUntil((async () => {
        const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        const open = windows.find((client) => new URL(client.url).origin === self.location.origin);
        if (open) {
            await open.focus();
            return open.navigate(url);
        }
        return self.clients.openWindow(url);
    })());
});
