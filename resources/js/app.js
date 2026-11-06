import $ from 'jquery';
import select2 from 'select2';
import Swal from 'sweetalert2';

select2(window, $);

let deferredInstallPrompt = null;
const installButtons = document.querySelectorAll('[data-pwa-install]');
const isIos = /iphone|ipad|ipod/i.test(navigator.userAgent)
	|| (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
const isStandalone = window.matchMedia('(display-mode: standalone)').matches
	|| navigator.standalone === true;

if (isStandalone) {
	installButtons.forEach((button) => button.hidden = true);
}

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
		if ($(select).hasClass('select2-hidden-accessible')) {
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

	const filterOptions = () => {
		relatedSelects.forEach((select) => {
			Array.from(select.options).forEach((option) => {
				option.disabled = Boolean(option.dataset.species && option.dataset.species !== speciesSelect.value);
			});

			if (select.selectedOptions[0]?.disabled) {
				select.value = '';
			}

			$(select).trigger('change.select2');
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
