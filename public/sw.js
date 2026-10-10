const CACHE_PREFIX = 'postobon-offline-';
const CACHE_NAME = `${CACHE_PREFIX}v1`;
const OFFLINE_URL = '/offline.html';

self.addEventListener('install', (event) => {
    // Only this public, self-contained page belongs in the service-worker cache.
    event.waitUntil(caches.open(CACHE_NAME).then((cache) => cache.addAll([OFFLINE_URL])));
});

self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        const names = await caches.keys();
        await Promise.all(names.filter((name) => name.startsWith(CACHE_PREFIX) && name !== CACHE_NAME)
            .map((name) => caches.delete(name)));
        await self.clients.claim();
    })());
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);
    if (request.method !== 'GET' || request.mode !== 'navigate' || url.origin !== self.location.origin
        || /^\/(api|media|storage|build)(\/|$)/.test(url.pathname)) return;

    event.respondWith((async () => {
        try {
            // Never persist authenticated pages or substitute server error responses.
            return await fetch(request, { cache: 'no-store' });
        } catch {
            const cache = await caches.open(CACHE_NAME);
            return await cache.match(OFFLINE_URL) ?? new Response('Sin conexión. Vuelve a intentarlo cuando tengas internet.', {
                status: 503, headers: { 'Content-Type': 'text/plain; charset=utf-8' },
            });
        }
    })());
});
