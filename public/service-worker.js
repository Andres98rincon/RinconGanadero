const CACHE_NAME = 'rincon-ganadero-static-v4';
// Páginas autenticadas para abrir formularios sin conexión. Se borra al cerrar sesión.
const PAGES_CACHE = 'rincon-ganadero-pages-v1';
const OFFLINE_PAGES = /\/(dashboard|animals|farms|calendar|weights|milk-production)(?:\/|$)/;
const OFFLINE_URL = new URL('./offline.html', self.location).href;
const PRECACHE_URLS = [
	OFFLINE_URL,
	new URL('./manifest.json', self.location).href,
	new URL('./favicon.ico', self.location).href,
	new URL('./images/rincon-ganadero.png', self.location).href,
	new URL('./images/pwa/apple-touch-icon.png', self.location).href,
	new URL('./images/pwa/icon-192.png', self.location).href,
	new URL('./images/pwa/icon-512.png', self.location).href,
];

self.addEventListener('install', (event) => {
	event.waitUntil(
		caches.open(CACHE_NAME)
			.then((cache) => cache.addAll(PRECACHE_URLS))
			.then(() => self.skipWaiting()),
	);
});

self.addEventListener('activate', (event) => {
	event.waitUntil(
		caches.keys()
			.then((cacheNames) => Promise.all(
				cacheNames
					.filter((cacheName) => (cacheName.startsWith('rincon-ganadero-static-') && cacheName !== CACHE_NAME)
						|| (cacheName.startsWith('rincon-ganadero-pages-') && cacheName !== PAGES_CACHE))
					.map((cacheName) => caches.delete(cacheName)),
			))
			.then(() => self.clients.claim()),
	);
});

self.addEventListener('fetch', (event) => {
	const { request } = event;
	const requestUrl = new URL(request.url);

	if (request.method !== 'GET' || requestUrl.origin !== self.location.origin) {
		return;
	}

	if (request.mode === 'navigate') {
		event.respondWith(
			fetch(request).then(async (response) => {
				if (response.ok && !response.redirected && OFFLINE_PAGES.test(requestUrl.pathname)) {
					const cache = await caches.open(PAGES_CACHE);
					await cache.put(requestUrl.pathname + requestUrl.search, response.clone());
				}
				return response;
			}).catch(async () => {
				const cachedPage = await caches.match(requestUrl.pathname + requestUrl.search, { cacheName: PAGES_CACHE })
					|| await caches.match(requestUrl.pathname, { cacheName: PAGES_CACHE, ignoreSearch: true });
				if (cachedPage) return cachedPage;
				const offlinePage = await caches.match(OFFLINE_URL);

				return offlinePage || Response.error();
			}),
		);
		return;
	}

	if (
		requestUrl.pathname.includes('/build/assets/')
		&& /\.(?:css|js|woff2?|ttf|svg)$/i.test(requestUrl.pathname)
	) {
		event.respondWith(
			fetch(request).then(async (response) => {
				if (response.ok) {
					const cache = await caches.open(CACHE_NAME);
					await cache.put(request, response.clone());
				}

				return response;
			}).catch(async () => {
				const cachedAsset = await caches.match(request);

				return cachedAsset || Response.error();
			}),
		);
	}
});

self.addEventListener('push', (event) => {
	let data = {};
	try { data = event.data ? event.data.json() : {}; } catch { data = { title: 'Rincón Ganadero', body: event.data?.text() || 'Tienes un nuevo recordatorio.' }; }
	event.waitUntil(self.registration.showNotification(data.title || 'Rincón Ganadero', {
		body: data.body || 'Tienes un nuevo recordatorio ganadero.',
		icon: '/images/pwa/icon-192.png',
		badge: '/images/pwa/icon-192.png',
		data: { url: data.url || '/notifications' },
	}));
});

self.addEventListener('notificationclick', (event) => {
	event.notification.close();
	event.waitUntil(clients.openWindow(event.notification.data?.url || '/notifications'));
});
