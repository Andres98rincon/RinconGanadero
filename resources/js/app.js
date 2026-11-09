import $ from 'jquery';
import select2 from 'select2';
import Swal from 'sweetalert2';
import './offline-sync';

select2(window, $);

const appLoading = document.querySelector('[data-app-loading]');
const hideAppLoading = () => {
	if (!appLoading) return;
	appLoading.classList.add('is-ready');
	window.setTimeout(() => appLoading.remove(), 280);
};

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', hideAppLoading, { once: true });
} else {
	hideAppLoading();
}
window.addEventListener('load', hideAppLoading, { once: true });

const mobileMenuToggle = document.querySelector('[data-mobile-menu-toggle]');
const mobileMenuBackdrop = document.querySelector('.mobile-menu-backdrop');
const setMobileMenu = (isOpen) => {
	document.body.classList.toggle('mobile-menu-open', isOpen);
	mobileMenuToggle?.setAttribute('aria-expanded', String(isOpen));
	if (mobileMenuBackdrop) mobileMenuBackdrop.hidden = !isOpen;
	if (isOpen) document.querySelector('#app-sidebar .nav-link')?.focus({ preventScroll: true });
};
mobileMenuToggle?.addEventListener('click', () => setMobileMenu(!document.body.classList.contains('mobile-menu-open')));
document.querySelectorAll('[data-mobile-menu-close]').forEach((element) => element.addEventListener('click', () => setMobileMenu(false)));
document.querySelectorAll('#app-sidebar a.nav-link').forEach((link) => link.addEventListener('click', () => setMobileMenu(false)));
document.addEventListener('keydown', (event) => {
	if (event.key === 'Escape' && document.body.classList.contains('mobile-menu-open')) setMobileMenu(false);
});
window.matchMedia('(min-width: 761px)').addEventListener('change', (event) => {
	if (event.matches) setMobileMenu(false);
});

let deferredInstallPrompt = null;
const installButtons = document.querySelectorAll('[data-pwa-install]');
const isIos = /iphone|ipad|ipod/i.test(navigator.userAgent)
	|| (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
const isStandalone = window.matchMedia('(display-mode: standalone)').matches
	|| navigator.standalone === true;

if (isStandalone) {
	installButtons.forEach((button) => button.hidden = true);
}

const themePreference = document.querySelector('[data-theme-preference]');
const themeChoices = document.querySelectorAll('[data-theme-choice]');
const systemTheme = window.matchMedia('(prefers-color-scheme: dark)');
const applyTheme = (preference) => {
	const isDark = preference === 'dark' || (preference === 'system' && systemTheme.matches);
	document.documentElement.dataset.theme = isDark ? 'dark' : 'light';
	document.body?.setAttribute('data-theme', isDark ? 'dark' : 'light');
	document.documentElement.dataset.themePreference = preference;
	if (themePreference) themePreference.value = preference;
	themeChoices.forEach((choice) => choice.classList.toggle('is-selected', choice.dataset.themeChoice === preference));
	const themeColor = document.querySelector('meta[name="theme-color"]');
	if (themeColor) themeColor.content = isDark ? '#101a16' : '#183d32';
};

const storedTheme = localStorage.getItem('rincon-ganadero-theme');
applyTheme(['light', 'dark', 'system'].includes(storedTheme) ? storedTheme : 'light');
themePreference?.addEventListener('change', () => {
	const preference = themePreference.value;
	localStorage.setItem('rincon-ganadero-theme', preference);
	applyTheme(preference);
});
themeChoices.forEach((choice) => choice.addEventListener('click', () => {
		const preference = choice.dataset.themeChoice === 'dark' ? 'dark' : 'light';
		localStorage.setItem('rincon-ganadero-theme', preference);
		applyTheme(preference);
}));
systemTheme.addEventListener?.('change', () => {
	if ((localStorage.getItem('rincon-ganadero-theme') || 'system') === 'system') applyTheme('system');
});

const initializeFarmMaps = () => {
	if (!window.L) return;
	document.querySelectorAll('[data-farm-map]').forEach((element) => {
		if (element.dataset.mapReady === 'true') return;
		element.dataset.mapReady = 'true';
		const picker = element.dataset.mapMode === 'picker';
		const latitudeInput = document.querySelector('[data-farm-latitude]');
		const longitudeInput = document.querySelector('[data-farm-longitude]');
		const latitude = Number(picker ? latitudeInput?.value : element.dataset.lat) || Number(element.dataset.defaultLat);
		const longitude = Number(picker ? longitudeInput?.value : element.dataset.lng) || Number(element.dataset.defaultLng);
		const map = window.L.map(element).setView([latitude, longitude], picker && !latitudeInput?.value ? 5 : 13);
		window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap contributors', maxZoom: 19 }).addTo(map);
		let marker = null;
		const setLocation = (lat, lng) => {
			marker?.setLatLng([lat, lng]);
			if (!marker) marker = window.L.marker([lat, lng]).addTo(map);
			if (picker) {
				latitudeInput.value = lat.toFixed(7);
				longitudeInput.value = lng.toFixed(7);
				latitudeInput.dispatchEvent(new Event('input', { bubbles: true }));
				longitudeInput.dispatchEvent(new Event('input', { bubbles: true }));
			}
		};
		if (!picker || latitudeInput?.value || longitudeInput?.value) setLocation(latitude, longitude);
		if (picker) {
			map.on('click', (event) => setLocation(event.latlng.lat, event.latlng.lng));
			[latitudeInput, longitudeInput].forEach((input) => input?.addEventListener('change', () => {
				const lat = Number(latitudeInput.value);
				const lng = Number(longitudeInput.value);
				if (Number.isFinite(lat) && Number.isFinite(lng)) { setLocation(lat, lng); map.setView([lat, lng], 13); }
			}));
		} else if (marker) {
			marker.bindPopup(`<strong>${element.dataset.farmName || 'Finca'}</strong><br>${element.dataset.farmCode || ''}`).openPopup();
		}
		setTimeout(() => map.invalidateSize(), 100);
	});
};

initializeFarmMaps();

window.addEventListener('beforeinstallprompt', (event) => {
	event.preventDefault();
	deferredInstallPrompt = event;
});

window.addEventListener('appinstalled', () => {
	deferredInstallPrompt = null;
	installButtons.forEach((button) => button.hidden = true);
});

installButtons.forEach((button) => {
	button.addEventListener('click', async () => {
		if (deferredInstallPrompt) {
			deferredInstallPrompt.prompt();
			await deferredInstallPrompt.userChoice;
			deferredInstallPrompt = null;
			return;
		}

		const instructions = isIos
			? 'En Safari, toca Compartir y luego “Añadir a pantalla de inicio”.'
			: /android/i.test(navigator.userAgent)
				? 'Abre el menú de Chrome y selecciona “Instalar aplicación” o “Añadir a pantalla de inicio”.'
				: 'Abre el menú de instalación de tu navegador y selecciona “Instalar aplicación” o “Añadir a pantalla de inicio”.';

		Swal.fire({
			icon: 'info',
			title: 'Instala Rincón Ganadero',
			text: instructions,
			confirmButtonColor: '#183d32',
		});
	});
});

if ('serviceWorker' in navigator && window.isSecureContext) {
	window.addEventListener('load', () => {
		const manifestLink = document.querySelector('link[rel="manifest"]');
		const serviceWorkerUrl = manifestLink
			? new URL('service-worker.js', manifestLink.href)
			: null;

		if (!serviceWorkerUrl) {
			return;
		}

		navigator.serviceWorker.register(serviceWorkerUrl).catch((error) => {
			console.error('No fue posible registrar el service worker de Rincón Ganadero.', error);
		});
	});
}

const initializeSearchSelects = (root = document) => {
	root.querySelectorAll('select').forEach((select) => {
		if (select.matches('[data-no-select2]') || $(select).hasClass('select2-hidden-accessible')) {
			return;
		}

		const firstOption = select.options[0];
		const placeholder = select.dataset.placeholder || (
			firstOption?.value === '' ? firstOption.textContent.trim() : ''
		);

		$(select).select2({
			width: '100%',
			placeholder,
			allowClear: select.dataset.allowClear === 'true' || firstOption?.value === '',
			minimumResultsForSearch: 0,
			dropdownAutoWidth: true,
		});
	});
};

initializeSearchSelects();

// Voice dictation for long text fields. The textarea remains fully editable so
// browsers without SpeechRecognition still have the manual input fallback.
const speechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
const initializeVoiceInputs = (root = document) => {
	root.querySelectorAll('textarea').forEach((textarea) => {
		if (textarea.dataset.voiceReady === 'true') return;
		textarea.dataset.voiceReady = 'true';

		const wrapper = document.createElement('div');
		wrapper.className = 'voice-input-wrapper';
		textarea.parentNode.insertBefore(wrapper, textarea);
		wrapper.appendChild(textarea);

		const button = document.createElement('button');
		button.type = 'button';
		button.className = 'voice-input-button';
		button.dataset.voiceDictation = 'true';
		button.setAttribute('aria-label', 'Dictar texto');
		button.innerHTML = '<span aria-hidden="true">🎙</span>';

		const status = document.createElement('span');
		status.className = 'voice-input-status';
		status.dataset.voiceStatus = 'true';
		status.setAttribute('role', 'status');
		status.setAttribute('aria-live', 'polite');
		wrapper.append(button, status);

		if (!speechRecognition) {
			button.hidden = true;
			status.textContent = 'Dictado no disponible en este navegador; escribe manualmente.';
			return;
		}

		let recognition = null;
		let listening = false;
		let baseValue = '';
		button.addEventListener('click', () => {
			if (listening) {
				recognition?.stop();
				return;
			}

			recognition = new speechRecognition();
			recognition.lang = document.documentElement.lang || 'es-CO';
			recognition.continuous = false;
			recognition.interimResults = true;
			baseValue = textarea.value.trim();
			recognition.onstart = () => {
				listening = true;
				button.classList.add('is-listening');
				button.setAttribute('aria-label', 'Detener dictado');
				status.textContent = 'Escuchando…';
			};
			recognition.onresult = (event) => {
				const transcript = Array.from(event.results)
					.map((result) => result[0]?.transcript || '')
					.join(' ').trim();
				textarea.value = [baseValue, transcript].filter(Boolean).join(baseValue && transcript ? ' ' : '');
				textarea.dispatchEvent(new Event('input', { bubbles: true }));
			};
			recognition.onerror = (event) => {
				status.textContent = event.error === 'not-allowed'
					? 'Permiso de micrófono denegado; puedes escribir manualmente.'
					: 'No fue posible reconocer la voz; inténtalo de nuevo o escribe manualmente.';
			};
			recognition.onend = () => {
				listening = false;
				button.classList.remove('is-listening');
				button.setAttribute('aria-label', 'Dictar texto');
				if (!status.textContent || status.textContent === 'Escuchando…') status.textContent = 'Dictado listo.';
			};
			recognition.start();
		});
	});
};

initializeVoiceInputs();
window.RinconGanaderoVoice = { initialize: initializeVoiceInputs, supported: Boolean(speechRecognition) };


const pushToggle = document.querySelector('[data-push-toggle]');
const pushStatus = document.querySelector('[data-push-status]');
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
const urlBase64ToUint8Array = (value) => Uint8Array.from(atob(value.replace(/-/g, '+').replace(/_/g, '/')), (char) => char.charCodeAt(0));

pushToggle?.addEventListener('click', async () => {
	if (!('Notification' in window) || !('serviceWorker' in navigator)) {
		if (pushStatus) pushStatus.textContent = 'Este navegador no admite notificaciones PWA.';
		return;
	}
	try {
		if (Notification.permission !== 'granted') {
			const permission = await Notification.requestPermission();
			if (permission !== 'granted') throw new Error('permission-denied');
		}
		const registration = await navigator.serviceWorker.ready;
		const publicKey = pushToggle.dataset.pushPublicKey;
		if (!publicKey) {
			if (pushStatus) pushStatus.textContent = 'Autorización concedida. El administrador debe configurar la clave VAPID para activar el envío remoto.';
			pushToggle.textContent = 'Autorización concedida';
			return;
		}
		const subscription = await registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: urlBase64ToUint8Array(publicKey) });
		const payload = subscription.toJSON();
		await fetch('/notifications/push-subscriptions', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }, body: JSON.stringify({ endpoint: payload.endpoint, keys: payload.keys, contentEncoding: payload.encoding || 'aes128gcm' }) });
		pushToggle.textContent = 'Notificaciones activadas';
		if (pushStatus) pushStatus.textContent = 'Este dispositivo recibirá los recordatorios programados.';
	} catch (error) {
		if (pushStatus) pushStatus.textContent = error.message === 'permission-denied' ? 'Permiso denegado. Puedes activarlo desde la configuración del navegador.' : 'No fue posible registrar este dispositivo para notificaciones.';
	}
});

const scanQrButton = document.querySelector('[data-qr-scan-open]');
const scanQrPhotoInput = document.querySelector('[data-qr-photo-input]');
const scanQrPhotoButton = document.querySelector('[data-qr-photo-open]');
const scannerPanel = document.querySelector('[data-qr-scanner]');
const scannerVideo = document.querySelector('[data-qr-video]');
const scannerStatus = document.querySelector('[data-qr-scanner-status]');
const qrLookupForm = document.querySelector('.animal-lookup-form');
let qrScannerControls = null;
let qrScannerActive = false;
let qrReaderPromise = null;

const loadQrReader = () => {
	qrReaderPromise ??= import('@zxing/browser').then(({ BrowserQRCodeReader }) => new BrowserQRCodeReader(undefined, { delayBetweenScanAttempts: 150 }));

	return qrReaderPromise;
};

/**
 * Accepts the URL printed on the animal QR (any host or base path, e.g. /HatoSoft/public/animals/qr/CODE)
 * or the bare 16 character code, and returns the normalized code.
 */
const extractAnimalQrCode = (text) => {
	const value = String(text || '').trim();
	const fromUrl = value.match(/\/animals\/qr\/([A-Za-z0-9]{16})(?:[/?#]|$)/);

	if (fromUrl) return fromUrl[1].toUpperCase();
	if (/^[A-Za-z0-9]{16}$/.test(value)) return value.toUpperCase();

	return null;
};

const setScannerStatus = (message, tone = 'info') => {
	if (!scannerStatus) return;
	scannerStatus.textContent = message;
	scannerStatus.dataset.tone = tone;
};

const openAnimalFromQr = (text) => {
	const code = extractAnimalQrCode(text);

	if (!code) {
		setScannerStatus('Este código QR no corresponde a un animal de Rincón Ganadero.', 'error');

		return false;
	}

	setScannerStatus('Código leído. Buscando el animal…', 'success');
	navigator.vibrate?.(80);
	const lookupUrl = new URL(qrLookupForm?.action || 'animals/lookup', window.location.href);
	lookupUrl.searchParams.set('code', code);
	window.location.assign(lookupUrl.href);

	return true;
};

const stopQrScanner = () => {
	qrScannerActive = false;
	qrScannerControls?.stop();
	qrScannerControls = null;
	if (scannerVideo) {
		scannerVideo.srcObject?.getTracks?.().forEach((track) => track.stop());
		scannerVideo.srcObject = null;
	}
	if (scanQrButton) scanQrButton.disabled = false;
};

const cameraErrorMessage = (error) => {
	if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) return 'Este navegador no permite la cámara en vivo en esta conexión. Toma una foto del código QR y se buscará el animal automáticamente.';

	switch (error?.name) {
		case 'NotAllowedError':
		case 'SecurityError':
			return 'Permiso de cámara denegado. Actívalo en la configuración del navegador (ícono del candado) o toma una foto del código.';
		case 'NotFoundError':
		case 'OverconstrainedError':
			return 'No se encontró una cámara en este dispositivo. Escribe el código o toma una foto del QR.';
		case 'NotReadableError':
		case 'AbortError':
			return 'La cámara está siendo usada por otra aplicación. Ciérrala e inténtalo de nuevo.';
		default:
			return 'No se pudo abrir la cámara en vivo. Toma una foto del código QR o escribe el código.';
	}
};

document.querySelector('[data-qr-scan-close]')?.addEventListener('click', () => {
	stopQrScanner();
	if (scannerPanel) scannerPanel.hidden = true;
});

scanQrButton?.addEventListener('click', async () => {
	if (!scannerPanel || !scannerVideo || !scannerStatus) return;
	scannerPanel.hidden = false;
	scannerVideo.hidden = false;
	if (scanQrPhotoButton) scanQrPhotoButton.hidden = true;

	// Without live camera (e.g. http on the local network) open the phone camera directly to take a photo.
	if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
		scannerVideo.hidden = true;
		setScannerStatus('Toma una foto del código QR; el animal se buscará automáticamente.');
		if (scanQrPhotoButton) scanQrPhotoButton.hidden = false;
		scanQrPhotoInput?.click();

		return;
	}

	scanQrButton.disabled = true;

	qrScannerActive = true;
	setScannerStatus('Solicitando acceso a la cámara…');

	try {
		const reader = await loadQrReader();
		if (!qrScannerActive) return;

		const onDecode = (result) => {
			if (!qrScannerActive || !result) return;
			if (openAnimalFromQr(result.getText())) stopQrScanner();
		};

		try {
			qrScannerControls = await reader.decodeFromConstraints({ audio: false, video: { facingMode: { ideal: 'environment' } } }, scannerVideo, onDecode);
		} catch (error) {
			if (error?.name !== 'OverconstrainedError') throw error;
			qrScannerControls = await reader.decodeFromConstraints({ audio: false, video: true }, scannerVideo, onDecode);
		}

		if (!qrScannerActive) {
			stopQrScanner();

			return;
		}

		setScannerStatus('Apunta la cámara al código QR del animal.');
		scannerPanel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
	} catch (error) {
		console.error('No fue posible iniciar el escáner QR.', error);
		stopQrScanner();
		scannerVideo.hidden = true;
		setScannerStatus(cameraErrorMessage(error), 'error');
		if (scanQrPhotoButton && error?.name !== 'NotFoundError') scanQrPhotoButton.hidden = false;
	}
});

scanQrPhotoButton?.addEventListener('click', () => scanQrPhotoInput?.click());

scanQrPhotoInput?.addEventListener('change', async () => {
	const file = scanQrPhotoInput.files?.[0];
	if (!file) return;

	stopQrScanner();
	if (scannerPanel) scannerPanel.hidden = false;
	if (scannerVideo) scannerVideo.hidden = true;
	setScannerStatus('Leyendo el código de la foto…');
	const imageUrl = URL.createObjectURL(file);

	try {
		const reader = await loadQrReader();
		const result = await reader.decodeFromImageUrl(imageUrl);
		if (!openAnimalFromQr(result.getText()) && scanQrPhotoButton) scanQrPhotoButton.hidden = false;
	} catch {
		setScannerStatus('No se encontró un código QR en la foto. Tómala más cerca, con buena luz y el código completo.', 'error');
		if (scanQrPhotoButton) scanQrPhotoButton.hidden = false;
	} finally {
		URL.revokeObjectURL(imageUrl);
		scanQrPhotoInput.value = '';
	}
});

qrLookupForm?.addEventListener('submit', (event) => {
	const input = qrLookupForm.querySelector('[name="code"]');
	const code = extractAnimalQrCode(input?.value);

	if (input && code) input.value = code;
	if (!code && input?.value) {
		event.preventDefault();
		Swal.fire({ icon: 'warning', title: 'Código no válido', text: 'Escribe los 16 caracteres del código o pega la dirección del QR.', confirmButtonColor: '#183d32' });
	}
}, true);

window.addEventListener('pagehide', stopQrScanner);

const importFile = document.querySelector('#animal-import-file');

importFile?.addEventListener('change', () => {
	const fileName = document.querySelector('[data-import-file-name]');

	if (fileName) {
		fileName.textContent = importFile.files?.[0]?.name || 'Sin archivo seleccionado';
	}
});

const authMethodSelect = document.querySelector('[data-auth-method-select]');
const passwordField = document.querySelector('#password');

authMethodSelect?.addEventListener('change', () => {
	const userForm = authMethodSelect.closest('form');
	const accountStatusSelect = userForm?.querySelector('[data-account-status-select]');

	if (passwordField && userForm) {
		passwordField.required = authMethodSelect.value === 'password'
			&& (userForm.matches('[data-user-creation]') || userForm.dataset.passwordSet === 'false');
	}

	if (
		accountStatusSelect
		&& (userForm?.matches('[data-user-creation]') || userForm?.dataset.googleLinked === 'false')
	) {
		accountStatusSelect.value = authMethodSelect.value === 'google' ? 'pending' : 'active';
		$(accountStatusSelect).trigger('change.select2');
	}
});

document.querySelectorAll('[data-export]').forEach((link) => {
	link.addEventListener('click', () => {
		Swal.fire({
			toast: true,
			position: 'top-end',
			icon: 'info',
			title: 'Preparando la descarga.',
			showConfirmButton: false,
			timer: 3000,
			timerProgressBar: true,
		});
	});
});

const speciesSelect = document.querySelector('[data-species-select]');

if (speciesSelect) {
	const relatedSelects = document.querySelectorAll('[data-species-options]');
	const originalOptions = new Map(Array.from(relatedSelects, (select) => [
		select,
		Array.from(select.options, (option) => option.cloneNode(true)),
	]));

	const filterOptions = () => {
		relatedSelects.forEach((select) => {
			const selectedValue = select.value;
			const options = originalOptions.get(select).filter((option) => (
				!option.dataset.species || option.dataset.species === speciesSelect.value
			));
			const selectedOption = options.find((option) => option.value === selectedValue);

			select.replaceChildren(...options.map((option) => option.cloneNode(true)));
			select.value = selectedOption ? selectedValue : '';

			$(select).trigger('change');
		});
	};

	speciesSelect.addEventListener('change', filterOptions);
	filterOptions();
}

document.querySelectorAll('form').forEach((form) => {
	if (form.method.toLowerCase() === 'get') {
		return;
	}

	form.noValidate = true;
	form.addEventListener('submit', (event) => {
		const invalidFields = Array.from(form.querySelectorAll(':invalid'))
			.filter((field) => !field.disabled && !field.closest('[hidden]'));

		if (invalidFields.length === 0) {
			return;
		}

		event.preventDefault();
		const messages = invalidFields.map((field) => {
			const label = (field.id && document.querySelector(`label[for="${CSS.escape(field.id)}"]`))
				?.textContent.trim()
				|| field.name.replaceAll('_', ' ');

			return field.validity.valueMissing
				? `Completa el campo obligatorio: ${label}`
				: `Revisa el formato o valor del campo: ${label}`;
		});

		Swal.fire({
			icon: 'warning',
			title: 'Revisa el formulario',
			text: messages.join('\n'),
			confirmButtonColor: '#183d32',
		}).then(() => {
			const invalidField = invalidFields[0];
			invalidField.focus();

			if ($(invalidField).hasClass('select2-hidden-accessible')) {
				$(invalidField).select2('open');
			}
		});
	});

	if (!form.matches('[data-confirm]')) {
		return;
	}

	form.addEventListener('submit', (event) => {
		if (event.defaultPrevented) {
			return;
		}

		if (form.dataset.confirmed === 'true') {
			return;
		}

		event.preventDefault();

		Swal.fire({
			icon: 'warning',
			text: form.dataset.confirm,
			showCancelButton: true,
			confirmButtonText: 'Sí, continuar',
			cancelButtonText: 'Cancelar',
			confirmButtonColor: '#183d32',
		}).then((result) => {
			if (result.isConfirmed) {
				form.dataset.confirmed = 'true';
				form.requestSubmit(event.submitter || undefined);
			}
		});
	});

	form.addEventListener('submit', () => {
		if (form.dataset.confirmed === 'true') {
			form.dataset.confirmed = 'submitted';
		}
	});
});

document.querySelectorAll('[data-swal-toast]').forEach((element) => {
	Swal.fire({
		toast: true,
		position: 'top-end',
		icon: 'success',
		title: element.dataset.swalToast,
		showConfirmButton: false,
		timer: 3500,
		timerProgressBar: true,
	});
});

const validationErrors = Array.from(document.querySelectorAll('[data-validation-error]'))
	.map((element) => element.textContent.trim())
	.filter(Boolean);
const operationError = document.querySelector('[data-swal-error]')?.dataset.swalError;

if (operationError || validationErrors.length > 0) {
	Swal.fire({
		icon: 'error',
		title: operationError ? 'No se pudo completar' : 'Revisa el formulario',
		text: [operationError, ...validationErrors].filter(Boolean).join('\n'),
		confirmButtonColor: '#183d32',
	});
}

document.querySelectorAll('[data-image-input]').forEach((input) => {
	const upload = input.closest('[data-image-upload]');
	const preview = upload?.querySelector('[data-image-preview]');
	const placeholder = upload?.querySelector('[data-image-placeholder]');
	const clearButton = upload?.querySelector('[data-clear-image]');
	const progress = upload?.querySelector('[data-upload-progress]');
	let previewUrl = null;

	const clearSelection = () => {
		input.value = '';

		if (previewUrl) {
			URL.revokeObjectURL(previewUrl);
			previewUrl = null;
		}

		preview.src = preview.dataset.existingSrc || '';
		preview.hidden = !preview.dataset.existingSrc;
		placeholder.hidden = Boolean(preview.dataset.existingSrc);
		clearButton.hidden = true;
		progress.hidden = true;
		progress.classList.remove('is-loading');
	};

	preview.addEventListener('load', () => {
		if (!previewUrl || preview.currentSrc !== previewUrl) {
			return;
		}

		Swal.fire({
			toast: true,
			position: 'top-end',
			icon: 'success',
			title: 'Vista previa lista',
			text: 'Guarda el formulario para aplicar la imagen.',
			showConfirmButton: false,
			timer: 3000,
		});
	});

	preview.addEventListener('error', () => {
		if (!previewUrl || preview.currentSrc !== previewUrl) {
			return;
		}

		clearSelection();
		Swal.fire({
			icon: 'error',
			title: 'No se pudo leer la imagen',
			text: 'El archivo seleccionado no contiene una imagen compatible.',
			confirmButtonColor: '#183d32',
		});
	});

	input.addEventListener('change', () => {
		const file = input.files?.[0];

		if (!file) {
			return;
		}

		const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
		const allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
		const extension = file.name.split('.').pop()?.toLowerCase();
		const maxSize = Number(input.dataset.maxSize || 5120) * 1024;
		const invalidType = !allowedExtensions.includes(extension)
			|| (file.type !== '' && !allowedTypes.includes(file.type));

		if (invalidType || file.size > maxSize) {
			clearSelection();
			Swal.fire({
				icon: 'error',
				title: 'No se pudo cargar la imagen',
				text: file.size > maxSize
					? 'La imagen supera el límite de 5 MB.'
					: 'Usa una imagen JPG, PNG o WEBP.',
				confirmButtonColor: '#183d32',
			});
			return;
		}

		if (previewUrl) {
			URL.revokeObjectURL(previewUrl);
		}

		previewUrl = URL.createObjectURL(file);
		preview.src = previewUrl;
		preview.hidden = false;
		placeholder.hidden = true;
		clearButton.hidden = false;
		progress.hidden = false;
		progress.classList.remove('is-loading');
		progress.textContent = 'Imagen lista para guardar.';
	});

	clearButton?.addEventListener('click', clearSelection);

	input.form?.addEventListener('submit', (event) => {
		if (event.defaultPrevented || !input.files?.length) {
			return;
		}

		progress.hidden = false;
		progress.classList.add('is-loading');
		progress.textContent = 'Guardando imagen…';
	});

	window.addEventListener('beforeunload', () => {
		if (previewUrl) {
			URL.revokeObjectURL(previewUrl);
		}
	});
});

document.querySelectorAll('[data-dismiss]').forEach((button) => {
	button.addEventListener('click', () => button.closest('[data-flash]')?.remove());
});

document.querySelector('[data-print]')?.addEventListener('click', () => window.print());
document.querySelector('[data-history-back]')?.addEventListener('click', () => history.back());

const reproductiveType = document.querySelector('[data-reproductive-type]');

if (reproductiveType) {
	const updateReproductiveSections = () => {
		document.querySelectorAll('[data-reproductive-for]').forEach((section) => {
			const allowedTypes = section.dataset.reproductiveFor.split(' ');
			section.hidden = !allowedTypes.includes(reproductiveType.value);
		});
	};

	reproductiveType.addEventListener('change', updateReproductiveSections);
	updateReproductiveSections();
}

const movementType = document.querySelector('[data-movement-type]');

if (movementType) {
	const updateMovementSections = () => {
		document.querySelectorAll('[data-movement-for]').forEach((section) => {
			const allowedTypes = section.dataset.movementFor.split(' ');
			const isVisible = allowedTypes.includes(movementType.value);

			section.hidden = !isVisible;
			const fields = [
				...(section.matches('input, select, textarea') ? [section] : []),
				...section.querySelectorAll('input, select, textarea'),
			];
			fields.forEach((field) => {
				field.disabled = !isVisible;

				if (field.dataset.required) {
					field.required = isVisible;
				}

				if (field.dataset.requiredFor) {
					field.required = isVisible && field.dataset.requiredFor.split(' ').includes(movementType.value);
				}
			});
		});
	};

	movementType.addEventListener('change', updateMovementSections);
	updateMovementSections();

	const destinationFarm = document.querySelector('[data-paddock-farm]');
	const movementAnimal = document.querySelector('[data-movement-animal]');
	const destinationPaddock = document.querySelector('[data-destination-paddock]');
	const paddockError = document.querySelector('[data-paddock-error]');
	const paddockOptionsTemplate = document.querySelector('[data-paddock-options-template]')?.dataset.paddockOptionsTemplate;

	if (destinationPaddock && paddockOptionsTemplate) {
		const loadPaddocks = async (farmId) => {
			const selectedPaddockId = destinationPaddock.value;
			destinationPaddock.replaceChildren(new Option('Sin potrero asignado', ''));

			if (!farmId) {
				$(destinationPaddock).trigger('change');
				return;
			}

			try {
				const response = await fetch(paddockOptionsTemplate.replace('FARM_ID', encodeURIComponent(farmId)), {
					headers: { Accept: 'application/json' },
				});

				if (!response.ok) {
					throw new Error('No fue posible cargar los potreros de la finca.');
				}

				const paddocks = await response.json();
				paddocks.forEach((paddock) => {
					const option = new Option(`${paddock.code} · ${paddock.name}`, paddock.id);
					option.selected = String(paddock.id) === selectedPaddockId;
					destinationPaddock.add(option);
				});
				$(destinationPaddock).trigger('change');
				paddockError.textContent = '';
			} catch (error) {
				paddockError.textContent = error instanceof Error ? error.message : 'No fue posible cargar los potreros.';
				Swal.fire({
					icon: 'error',
					title: 'No se pudieron cargar los potreros',
					text: paddockError.textContent,
					confirmButtonColor: '#183d32',
				});
			}
		};

		const selectedFarmId = () => movementType.value === 'paddock_transfer'
			? movementAnimal?.selectedOptions[0]?.dataset.farmId
			: destinationFarm?.value;

		destinationFarm?.addEventListener('change', () => loadPaddocks(selectedFarmId()));
		movementAnimal?.addEventListener('change', () => loadPaddocks(selectedFarmId()));
		movementType.addEventListener('change', () => loadPaddocks(selectedFarmId()));
		loadPaddocks(selectedFarmId());
	}
}

const calfList = document.querySelector('[data-calf-list]');
const calfTemplate = document.querySelector('[data-calf-template]');

if (calfList && calfTemplate) {
	document.querySelector('[data-add-calf]')?.addEventListener('click', () => {
		const index = calfList.querySelectorAll('[data-calf-row]').length;
		const row = document.createElement('div');
		row.innerHTML = calfTemplate.innerHTML.replaceAll('__INDEX__', String(index));
		calfList.append(row.firstElementChild);
		initializeSearchSelects(calfList.lastElementChild);
	});

	calfList.addEventListener('click', (event) => {
		if (!event.target.closest('[data-remove-calf]') || calfList.querySelectorAll('[data-calf-row]').length < 2) {
			return;
		}

		event.target.closest('[data-calf-row]').remove();
	});
}
