const CACHE = 'united-shell-v64';
const SHELL = [ '/manifest.webmanifest', '/images/united-logo.png'];
self.addEventListener('install', (event) => event.waitUntil(caches.open(CACHE).then((cache) => cache.addAll(SHELL)).then(() => self.skipWaiting())));
self.addEventListener('activate', (event) => event.waitUntil(
  caches.keys().then((keys) => Promise.all(keys.filter((key) => key !== CACHE && /^(united|kflow)-/.test(key)).map((key) => caches.delete(key))))
    .then(() => self.clients.claim())
));
self.addEventListener('fetch', (event) => {
  if (event.request.method !== 'GET' || new URL(event.request.url).origin !== self.location.origin) return;
  if (event.request.mode === 'navigate') {
    event.respondWith(fetch(event.request).catch(() => new Response('<!doctype html><html lang="pt-BR"><meta charset="utf-8"><title>United Ati indisponível</title><body style="font:18px system-ui;padding:3rem;max-width:700px"><h1>United Ati indisponível</h1><p>O serviço local não respondeu. Inicie o ambiente United e tente novamente.</p><a href="/unitedati">Tentar novamente</a></body></html>', {status:503,headers:{'Content-Type':'text/html; charset=utf-8'}})));
    return;
  }
  event.respondWith(fetch(event.request).then((response) => {
    if (response.ok && ['script', 'style', 'image', 'font'].includes(event.request.destination)) caches.open(CACHE).then((cache) => cache.put(event.request, response.clone()));
    return response;
  }).catch(() => caches.match(event.request)));
});
