const CACHE = 'united-shell-v67';
self.addEventListener('install', event => event.waitUntil(self.skipWaiting()));
self.addEventListener('activate', event => event.waitUntil(
    caches.keys().then(keys => Promise.all(keys.filter(key => /^(united|kflow)-/.test(key) && key !== CACHE).map(key => caches.delete(key)))).then(() => self.clients.claim())
));
self.addEventListener('fetch', event => {
    const request = event.request;
    if (request.method !== 'GET' || new URL(request.url).origin !== self.location.origin) return;
    // Let the browser handle navigation and connection errors. Never replace live pages with an offline shell.
    if (request.mode === 'navigate') return;
    if (!['script', 'style', 'image', 'font'].includes(request.destination)) return;
    event.respondWith((async () => {
        const immutable = new URL(request.url).pathname.startsWith('/assets/');
        let cache;
        try { cache = await caches.open(CACHE); } catch { return fetch(request); }
        if (immutable) { const cached = await cache.match(request); if (cached) return cached; }
        try { const response = await fetch(request); if (response.ok) await cache.put(request, response.clone()).catch(() => {}); return response; }
        catch (error) { const cached = await cache.match(request); if (cached) return cached; throw error; }
    })());
});
