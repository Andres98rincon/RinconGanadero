import Swal from 'sweetalert2';

/**
 * Modo offline de HatoSoft.
 *
 * - Los formularios marcados con data-offline-form se guardan en IndexedDB cuando no hay conexión.
 * - Cada registro recibe un UUID (offline_id) generado en el dispositivo. El servidor lo guarda con
 *   índice único, así un reintento (respuesta perdida, dos pestañas, reintento manual) nunca duplica.
 * - La cola solo se borra cuando el servidor confirma el registro.
 * - Un indicador fijo en la barra superior muestra el estado: en línea, sin conexión, sincronizando,
 *   pendientes y registros que requieren revisión.
 */

const DB_NAME = 'hato-soft-offline';
const STORE_NAME = 'operations';
const PAGES_CACHE = 'rincon-ganadero-pages-v1';
const SYNC_LOCK = 'hatosoft-offline-sync';
const BATCH_SIZE = 50;
const REQUEST_TIMEOUT_MS = 8000;
const HEARTBEAT_ONLINE_MS = 120000;
const HEARTBEAT_OFFLINE_MS = 20000;

const TYPE_LABELS = {
	animal_event: 'Evento de bitácora',
	weight: 'Pesaje',
	milk_production: 'Producción de leche',
};

const REVIEW_STATUSES = ['invalid', 'denied', 'conflict'];

const meta = (name) => document.querySelector(`meta[name="${name}"]`)?.content || null;

const config = {
	syncUrl: meta('offline-sync-url'),
	statusUrl: meta('offline-status-url'),
	userId: Number(meta('offline-user-id')) || null,
};

const state = {
	connection: navigator.onLine ? 'checking' : 'offline',
	syncing: false,
	sessionExpired: false,
	operations: [],
	retryDelayMs: 0,
	retryTimer: null,
	heartbeatTimer: null,
};

const channel = 'BroadcastChannel' in window ? new BroadcastChannel('hatosoft-offline') : null;

/* ---------------------------------------------------------------- IndexedDB */

const database = new Promise((resolve, reject) => {
	if (!('indexedDB' in window)) {
		reject(new Error('indexeddb-unavailable'));

		return;
	}

	const request = indexedDB.open(DB_NAME, 1);
	request.onupgradeneeded = () => {
		if (!request.result.objectStoreNames.contains(STORE_NAME)) {
			request.result.createObjectStore(STORE_NAME, { keyPath: 'id' });
		}
	};
	request.onsuccess = () => resolve(request.result);
	request.onerror = () => reject(request.error);
});

const withStore = async (mode, callback) => {
	const db = await database;

	return new Promise((resolve, reject) => {
		const transaction = db.transaction(STORE_NAME, mode);
		const result = callback(transaction.objectStore(STORE_NAME));
		transaction.oncomplete = () => resolve(result?.result ?? result);
		transaction.onerror = () => reject(transaction.error);
		transaction.onabort = () => reject(transaction.error);
	});
};

const readOperations = () => withStore('readonly', (store) => store.getAll());
const saveOperation = (operation) => withStore('readwrite', (store) => store.put(operation));
const deleteOperation = (id) => withStore('readwrite', (store) => store.delete(id));

/** Registros creados por la versión anterior de la cola no tenían usuario ni estado normalizado. */
const normalizeOperation = (operation) => ({
	...operation,
	user_id: operation.user_id ?? config.userId,
	status: operation.status === 'needs_review' || REVIEW_STATUSES.includes(operation.status) ? 'needs_review' : 'pending',
	attempts: operation.attempts ?? 0,
});

const refreshOperations = async () => {
	state.operations = (await readOperations().catch(() => []))
		.map(normalizeOperation)
		.sort((first, second) => String(first.created_at).localeCompare(String(second.created_at)));

	return state.operations;
};

const myOperations = () => state.operations.filter((operation) => operation.user_id === config.userId);
const pendingOperations = () => myOperations().filter((operation) => operation.status === 'pending');
const reviewOperations = () => myOperations().filter((operation) => operation.status === 'needs_review');
const foreignOperations = () => state.operations.filter((operation) => operation.user_id !== config.userId);

const notifyOtherTabs = () => channel?.postMessage({ type: 'queue-changed' });

/* ------------------------------------------------------------- Conectividad */

const fetchWithTimeout = (url, options = {}, timeout = REQUEST_TIMEOUT_MS) => {
	const controller = new AbortController();
	const timer = window.setTimeout(() => controller.abort(), timeout);

	return fetch(url, { ...options, signal: controller.signal }).finally(() => window.clearTimeout(timer));
};

const csrfToken = () => meta('csrf-token');

const updateCsrfToken = (token) => {
	if (!token) return;
	document.querySelector('meta[name="csrf-token"]')?.setAttribute('content', token);
	document.querySelectorAll('input[name="_token"]').forEach((input) => { input.value = token; });
};

/**
 * navigator.onLine solo indica que hay red, no que el servidor responda (wifi sin internet,
 * datos móviles sin señal). Por eso se confirma con una petición real al servidor.
 *
 * @returns {Promise<boolean>} true cuando el servidor responde y la sesión está activa.
 */
const checkConnectivity = async (timeout = REQUEST_TIMEOUT_MS) => {
	if (!navigator.onLine || !config.statusUrl) {
		setConnection('offline');

		return false;
	}

	try {
		const response = await fetchWithTimeout(config.statusUrl, {
			credentials: 'same-origin',
			cache: 'no-store',
			headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
		}, timeout);

		if (response.status === 401 || response.status === 419) {
			state.sessionExpired = true;
			setConnection('online');

			return false;
		}

		if (!response.ok) {
			setConnection('offline');

			return false;
		}

		const data = await response.json();
		state.sessionExpired = false;
		updateCsrfToken(data.csrf_token);

		if (data.user_id && data.user_id !== config.userId) {
			config.userId = data.user_id;
		}

		setConnection('online');

		return true;
	} catch {
		setConnection('offline');

		return false;
	}
};

function setConnection(connection) {
	const changed = state.connection !== connection;
	state.connection = connection;
	render();

	if (changed) {
		scheduleHeartbeat();
	}
}

const scheduleHeartbeat = () => {
	window.clearTimeout(state.heartbeatTimer);
	const delay = state.connection === 'online' ? HEARTBEAT_ONLINE_MS : HEARTBEAT_OFFLINE_MS;
	state.heartbeatTimer = window.setTimeout(async () => {
		const wasOffline = state.connection !== 'online';
		const isReachable = await checkConnectivity();

		if (isReachable && (wasOffline || pendingOperations().length > 0)) {
			await syncNow();
		}

		scheduleHeartbeat();
	}, delay);
};

const scheduleRetry = () => {
	window.clearTimeout(state.retryTimer);
	state.retryDelayMs = Math.min(state.retryDelayMs ? state.retryDelayMs * 2 : 15000, 600000);
	state.retryTimer = window.setTimeout(() => syncNow(), state.retryDelayMs);
};

/* -------------------------------------------------------------- Sincronizar */

const withSyncLock = async (callback) => {
	if (navigator.locks?.request) {
		return navigator.locks.request(SYNC_LOCK, { ifAvailable: true }, (lock) => (lock ? callback() : null));
	}

	return callback();
};

const sendBatch = async (batch, hasRetriedToken = false) => {
	const response = await fetchWithTimeout(config.syncUrl, {
		method: 'POST',
		credentials: 'same-origin',
		cache: 'no-store',
		headers: {
			'Content-Type': 'application/json',
			Accept: 'application/json',
			'X-Requested-With': 'XMLHttpRequest',
			'X-CSRF-TOKEN': csrfToken(),
		},
		body: JSON.stringify({
			operations: batch.map(({ id, type, animal_id: animalId, payload }) => ({ id, type, animal_id: animalId, payload })),
		}),
	}, 30000);

	if (response.status === 419 && !hasRetriedToken && await checkConnectivity()) {
		return sendBatch(batch, true);
	}

	if (response.status === 401 || response.status === 419) {
		state.sessionExpired = true;
		throw new Error('session-expired');
	}

	if (!response.ok) {
		throw new Error(`sync-failed-${response.status}`);
	}

	return (await response.json()).results || [];
};

const applyResults = async (batch, results) => {
	const summary = { synced: 0, review: 0 };
	const resultsById = new Map(results.map((result) => [result.id, result]));

	for (const operation of batch) {
		const result = resultsById.get(operation.id);

		if (!result) continue;

		if (result.status === 'synced') {
			await deleteOperation(operation.id);
			summary.synced += 1;
			continue;
		}

		const needsReview = REVIEW_STATUSES.includes(result.status);
		await saveOperation({
			...operation,
			status: needsReview ? 'needs_review' : 'pending',
			result_status: result.status,
			last_error: result.message || null,
			errors: result.errors || null,
			attempts: (operation.attempts || 0) + 1,
			updated_at: new Date().toISOString(),
		});

		if (needsReview) summary.review += 1;
	}

	return summary;
};

/**
 * Envía la cola al servidor por lotes. Un candado (Web Locks) evita que dos pestañas
 * envíen la misma cola al mismo tiempo; aun así, el offline_id único protege en el servidor.
 */
export const syncNow = async ({ silent = false } = {}) => withSyncLock(async () => {
	if (state.syncing || !config.syncUrl) return;

	await refreshOperations();

	if (!pendingOperations().length) {
		render();

		return;
	}

	if (!await checkConnectivity()) {
		render();

		return;
	}

	state.syncing = true;
	render();

	const totals = { synced: 0, review: 0 };
	let failed = false;

	try {
		const queue = pendingOperations();

		for (let index = 0; index < queue.length; index += BATCH_SIZE) {
			const batch = queue.slice(index, index + BATCH_SIZE);
			const summary = await applyResults(batch, await sendBatch(batch));
			totals.synced += summary.synced;
			totals.review += summary.review;
		}

		state.retryDelayMs = 0;
	} catch (error) {
		failed = true;

		if (error.name === 'AbortError' || error instanceof TypeError) {
			setConnection('offline');
		}

		if (error.message !== 'session-expired') {
			scheduleRetry();
		}
	} finally {
		state.syncing = false;
		await refreshOperations();
		render();
		notifyOtherTabs();
	}

	if (silent) return;

	if (totals.synced > 0) {
		toast('success', `${totals.synced} registro${totals.synced === 1 ? '' : 's'} sincronizado${totals.synced === 1 ? '' : 's'} con el servidor.`);
	}

	if (totals.review > 0) {
		toast('warning', `${totals.review} registro${totals.review === 1 ? '' : 's'} requiere${totals.review === 1 ? '' : 'n'} revisión. Ábrelos desde el indicador de conexión.`);
	} else if (failed && state.sessionExpired) {
		toast('warning', 'Tu sesión expiró. Inicia sesión para terminar de sincronizar; los registros siguen guardados.');
	}
});

/* -------------------------------------------------------------- Formularios */

/** crypto.randomUUID solo existe en contextos seguros (https/localhost); en http de red local se genera un UUID v4 manualmente. */
const generateUuid = () => {
	if (window.crypto?.randomUUID) return window.crypto.randomUUID();

	const bytes = window.crypto.getRandomValues(new Uint8Array(16));
	bytes[6] = (bytes[6] & 0x0f) | 0x40;
	bytes[8] = (bytes[8] & 0x3f) | 0x80;
	const hex = [...bytes].map((byte) => byte.toString(16).padStart(2, '0')).join('');

	return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
};

const formPayload = (form) => Object.fromEntries(
	[...new FormData(form)]
		.filter(([key, value]) => !['_token', '_method', 'offline_id'].includes(key) && typeof value === 'string'),
);

const describeOperation = (form, type, payload) => {
	const animalSelect = form.querySelector('select[name="animal_id"]');
	const animalLabel = form.dataset.offlineAnimalLabel
		|| animalSelect?.selectedOptions?.[0]?.textContent?.trim()
		|| (payload.animal_id ? `Animal #${payload.animal_id}` : '');
	const date = payload.event_date || payload.record_date || '';
	const detail = payload.title
		|| (payload.weight_kg ? `${payload.weight_kg} kg` : '')
		|| [payload.morning_liters && `AM ${payload.morning_liters} L`, payload.evening_liters && `PM ${payload.evening_liters} L`].filter(Boolean).join(' · ');

	return [animalLabel, detail, date].filter(Boolean).join(' · ');
};

const queueForm = async (form) => {
	const type = form.dataset.offlineForm.replace('-', '_');
	const payload = formPayload(form);
	const animalId = Number(form.dataset.offlineAnimalId || payload.animal_id);

	if (!TYPE_LABELS[type] || !animalId) {
		await Swal.fire({ icon: 'error', title: 'No se pudo guardar', text: 'Selecciona el animal antes de guardar sin conexión.', confirmButtonColor: '#183d32' });

		return;
	}

	delete payload.animal_id;
	await refreshOperations();

	if (type === 'milk_production' && myOperations().some((operation) => operation.type === type
		&& operation.animal_id === animalId && operation.payload.record_date === payload.record_date)) {
		await Swal.fire({
			icon: 'warning',
			title: 'Registro repetido',
			text: 'Ya guardaste en este dispositivo la producción de esta vaca para esa fecha. Revísala en el indicador de conexión.',
			confirmButtonColor: '#183d32',
		});

		return;
	}

	const now = new Date().toISOString();

	try {
		await saveOperation({
			id: generateUuid(),
			type,
			animal_id: animalId,
			user_id: config.userId,
			summary: describeOperation(form, type, payload),
			payload,
			status: 'pending',
			attempts: 0,
			created_at: now,
			updated_at: now,
		});
	} catch {
		await Swal.fire({ icon: 'error', title: 'No se pudo guardar', text: 'Este navegador no permite guardar datos sin conexión. Revisa que no estés en modo incógnito o con el almacenamiento lleno.', confirmButtonColor: '#183d32' });

		return;
	}

	form.reset();
	form.querySelectorAll('select').forEach((select) => select.dispatchEvent(new Event('change', { bubbles: true })));
	await refreshOperations();
	render();
	notifyOtherTabs();

	toast('info', state.sessionExpired
		? 'Guardado en este dispositivo. Inicia sesión de nuevo para sincronizarlo.'
		: 'Guardado en este dispositivo. Se enviará automáticamente al volver la conexión.');
};

const handleOfflineFormSubmit = async (form) => {
	if (form.dataset.offlineBusy === 'true') return;
	form.dataset.offlineBusy = 'true';

	try {
		const isReachable = state.connection !== 'offline' && await checkConnectivity(4000);

		if (isReachable && !state.sessionExpired) {
			HTMLFormElement.prototype.submit.call(form);

			return;
		}

		await queueForm(form);
	} finally {
		window.setTimeout(() => { delete form.dataset.offlineBusy; }, 800);
	}
};

const handleLogoutSubmit = async (form) => {
	await refreshOperations();
	const pending = myOperations().length;

	if (pending > 0) {
		const result = await Swal.fire({
			icon: 'warning',
			title: 'Hay registros sin sincronizar',
			text: `Tienes ${pending} registro${pending === 1 ? '' : 's'} guardado${pending === 1 ? '' : 's'} en este dispositivo. Seguirán aquí y se enviarán cuando vuelvas a iniciar sesión con este usuario.`,
			showCancelButton: true,
			confirmButtonText: 'Cerrar sesión',
			cancelButtonText: 'Cancelar',
			confirmButtonColor: '#183d32',
		});

		if (!result.isConfirmed) return;
	}

	await window.caches?.delete(PAGES_CACHE).catch(() => {});
	HTMLFormElement.prototype.submit.call(form);
};

document.addEventListener('submit', (event) => {
	const form = event.target;

	if (!(form instanceof HTMLFormElement) || event.defaultPrevented) return;

	if (form.matches('[data-offline-form]')) {
		event.preventDefault();
		handleOfflineFormSubmit(form);

		return;
	}

	if (form.matches('[data-logout-form]')) {
		event.preventDefault();
		handleLogoutSubmit(form);
	}
});

/* ------------------------------------------------------------------ Interfaz */

const elements = {
	indicator: document.querySelector('[data-offline-indicator]'),
	label: document.querySelector('[data-offline-indicator-label]'),
	count: document.querySelector('[data-offline-indicator-count]'),
	panel: document.querySelector('[data-offline-panel]'),
	panelState: document.querySelector('[data-offline-panel-state]'),
	list: document.querySelector('[data-offline-list]'),
	syncButton: document.querySelector('[data-offline-sync-now]'),
	banner: document.querySelector('[data-offline-status]'),
};

const plural = (count, singular, pluralText) => `${count} ${count === 1 ? singular : pluralText}`;

const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char]);

const formatDate = (value) => {
	try {
		return new Intl.DateTimeFormat('es-CO', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value));
	} catch {
		return value;
	}
};

const indicatorState = () => {
	const pending = pendingOperations().length;
	const review = reviewOperations().length;

	if (state.syncing) return { key: 'syncing', label: `Sincronizando ${pending}…`, detail: 'Enviando registros guardados en este dispositivo.' };
	if (state.connection === 'offline') return { key: 'offline', label: 'Sin conexión', detail: pending ? `${plural(pending, 'registro guardado', 'registros guardados')} en este dispositivo. Se enviarán al volver la conexión.` : 'Puedes seguir registrando; los datos se guardarán en este dispositivo.' };
	if (state.sessionExpired) return { key: 'session', label: 'Sesión expirada', detail: 'Hay conexión, pero debes iniciar sesión de nuevo para sincronizar.' };
	if (state.connection === 'checking') return { key: 'checking', label: 'Comprobando…', detail: 'Verificando la conexión con el servidor.' };
	if (review) return { key: 'review', label: 'En línea', detail: `${plural(review, 'registro requiere', 'registros requieren')} revisión.` };
	if (pending) return { key: 'pending', label: 'En línea', detail: `${plural(pending, 'registro pendiente', 'registros pendientes')} de sincronizar.` };

	return { key: 'online', label: 'En línea', detail: 'Todo está sincronizado.' };
};

const renderList = () => {
	if (!elements.list) return;

	const items = myOperations();
	const foreign = foreignOperations().length;

	if (!items.length) {
		elements.list.innerHTML = `<li class="offline-queue-empty">No hay registros pendientes en este dispositivo.</li>${foreign ? `<li class="offline-queue-note">${plural(foreign, 'registro pertenece', 'registros pertenecen')} a otro usuario y se enviará${foreign === 1 ? '' : 'n'} cuando ese usuario inicie sesión.</li>` : ''}`;

		return;
	}

	elements.list.innerHTML = items.map((operation) => {
		const isReview = operation.status === 'needs_review';

		return `<li class="offline-queue-item ${isReview ? 'is-review' : ''}" data-operation-id="${escapeHtml(operation.id)}">
			<div class="offline-queue-head">
				<strong>${escapeHtml(TYPE_LABELS[operation.type] || operation.type)}</strong>
				<span class="offline-queue-badge">${isReview ? 'Requiere revisión' : 'Pendiente'}</span>
			</div>
			<p>${escapeHtml(operation.summary || '')}</p>
			<small>Guardado ${escapeHtml(formatDate(operation.created_at))}${operation.attempts ? ` · ${plural(operation.attempts, 'intento', 'intentos')}` : ''}</small>
			${operation.last_error ? `<p class="offline-queue-error">${escapeHtml(operation.last_error)}</p>` : ''}
			<div class="offline-queue-actions">
				${isReview ? '<button type="button" class="button button-secondary button-small" data-offline-retry>Reintentar</button>' : ''}
				<button type="button" class="button button-secondary button-small" data-offline-discard>Descartar</button>
			</div>
		</li>`;
	}).join('') + (foreign ? `<li class="offline-queue-note">Además hay ${plural(foreign, 'registro', 'registros')} de otro usuario en este dispositivo.</li>` : '');
};

function render() {
	const current = indicatorState();
	const pending = pendingOperations().length;
	const review = reviewOperations().length;

	if (elements.indicator) {
		elements.indicator.dataset.state = current.key;
		elements.indicator.title = current.detail;
		elements.indicator.setAttribute('aria-label', `Estado de conexión: ${current.label}. ${current.detail}`);
	}

	if (elements.label) elements.label.textContent = current.label;

	if (elements.count) {
		const total = pending + review;
		elements.count.hidden = total === 0;
		elements.count.textContent = String(total);
		elements.count.classList.toggle('is-review', review > 0);
	}

	if (elements.panelState) elements.panelState.textContent = current.detail;

	if (elements.syncButton) {
		elements.syncButton.disabled = state.syncing || pending === 0 || state.connection === 'offline';
		elements.syncButton.textContent = state.syncing ? 'Sincronizando…' : 'Sincronizar ahora';
	}

	if (elements.banner) {
		const showBanner = state.connection === 'offline' || state.sessionExpired;
		elements.banner.hidden = !showBanner;
		elements.banner.classList.toggle('is-offline', state.connection === 'offline');
		elements.banner.classList.toggle('is-session', state.sessionExpired && state.connection !== 'offline');
		elements.banner.textContent = state.connection === 'offline'
			? `Sin conexión. Puedes seguir registrando eventos, pesajes y producción de leche: se guardan en este dispositivo${pending ? ` (${pending} pendiente${pending === 1 ? '' : 's'})` : ''} y se sincronizan solos al volver la conexión.`
			: 'Tu sesión expiró. Inicia sesión de nuevo para sincronizar los registros guardados en este dispositivo; no se perderán.';
	}

	renderList();
}

function toast(icon, title) {
	Swal.fire({ toast: true, position: 'top-end', icon, title, showConfirmButton: false, timer: 4500, timerProgressBar: true });
}

const togglePanel = (open = elements.panel?.hidden) => {
	if (!elements.panel || !elements.indicator) return;
	elements.panel.hidden = !open;
	elements.indicator.setAttribute('aria-expanded', String(open));
};

elements.indicator?.addEventListener('click', (event) => {
	event.stopPropagation();
	togglePanel();
});

document.addEventListener('click', (event) => {
	if (!elements.panel || elements.panel.hidden) return;
	if (elements.panel.contains(event.target) || event.target.closest?.('.swal2-container')) return;
	togglePanel(false);
});

document.addEventListener('keydown', (event) => {
	if (event.key === 'Escape') togglePanel(false);
});

elements.syncButton?.addEventListener('click', () => syncNow());

elements.list?.addEventListener('click', async (event) => {
	const item = event.target.closest('[data-operation-id]');
	const operation = item && state.operations.find((candidate) => candidate.id === item.dataset.operationId);

	if (!operation) return;

	if (event.target.closest('[data-offline-retry]')) {
		await saveOperation({ ...operation, status: 'pending', updated_at: new Date().toISOString() });
		await refreshOperations();
		render();
		syncNow();

		return;
	}

	if (event.target.closest('[data-offline-discard]')) {
		event.stopPropagation();
		const result = await Swal.fire({
			icon: 'warning',
			title: 'Descartar registro',
			text: 'Este registro no se ha enviado al servidor. Si lo descartas se perderá definitivamente.',
			showCancelButton: true,
			confirmButtonText: 'Descartar',
			cancelButtonText: 'Cancelar',
			confirmButtonColor: '#a44c43',
		});

		if (!result.isConfirmed) return;

		await deleteOperation(operation.id);
		await refreshOperations();
		render();
		notifyOtherTabs();
	}
});

/* -------------------------------------------------------------------- Inicio */

const start = async () => {
	if (!config.syncUrl) return;

	await refreshOperations().catch(() => []);
	render();

	window.addEventListener('online', () => { setConnection('checking'); syncNow(); });
	window.addEventListener('offline', () => setConnection('offline'));
	document.addEventListener('visibilitychange', () => {
		if (document.visibilityState === 'visible') syncNow({ silent: pendingOperations().length === 0 });
	});
	channel?.addEventListener('message', async () => { await refreshOperations(); render(); });

	await checkConnectivity();
	scheduleHeartbeat();
	await syncNow();
};

start();
