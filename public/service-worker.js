const CACHE_NAME = 'rincon-ganadero-static-v2';
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
					.filter((cacheName) => cacheName.startsWith('rincon-ganadero-static-') && cacheName !== CACHE_NAME)
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
			fetch(request).catch(async () => {
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
