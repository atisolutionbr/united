// start the Stimulus application
import './stimulus_bootstrap.js';
import './styles/app.scss';
import 'highlight.js/styles/github-dark-dimmed.css';
import 'lato-font/css/lato-font.css';

// loads the Bootstrap plugins
import * as bootstrap from 'bootstrap';

// loads the code syntax highlighting library
import './js/highlight.js';

// Creates links to the Symfony documentation
import './js/doclinks.js';

import './js/flatpicker.js';

function initializeNavbarDropdowns() {
	document.querySelectorAll('.app-navbar [data-bs-toggle="dropdown"]').forEach((toggle) => {
		if (toggle.dataset.dropdownInitialized === 'true') {
			return;
		}

		bootstrap.Dropdown.getOrCreateInstance(toggle);
		toggle.addEventListener('click', (event) => {
			event.preventDefault();
			bootstrap.Dropdown.getOrCreateInstance(toggle).toggle();
		});
		toggle.dataset.dropdownInitialized = 'true';
	});
}

function initializeKFlowShell() {
	const body = document.body;
	const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
	const sidebarScrim = document.querySelector('[data-sidebar-scrim]');

	if (window.localStorage.getItem('kflow-sidebar') === 'collapsed' && window.innerWidth > 820) {
		body.classList.add('sidebar-is-collapsed');
	}

	sidebarToggle?.addEventListener('click', () => {
		if (window.innerWidth <= 820) {
			body.classList.toggle('sidebar-is-open');
			return;
		}

		body.classList.toggle('sidebar-is-collapsed');
		window.localStorage.setItem('kflow-sidebar', body.classList.contains('sidebar-is-collapsed') ? 'collapsed' : 'expanded');
	});

	sidebarScrim?.addEventListener('click', () => body.classList.remove('sidebar-is-open'));

	document.querySelectorAll('[data-sidebar-group]').forEach((toggle) => {
		toggle.addEventListener('click', () => {
			const group = toggle.closest('.kflow-nav__group');
			const isOpen = group?.classList.toggle('is-open') ?? false;
			toggle.setAttribute('aria-expanded', String(isOpen));
		});
	});

	const passwordInput = document.querySelector('[data-password-input]');
	document.querySelector('[data-password-toggle]')?.addEventListener('click', (event) => {
		if (!(passwordInput instanceof HTMLInputElement)) {
			return;
		}

		const reveal = passwordInput.type === 'password';
		passwordInput.type = reveal ? 'text' : 'password';
		const icon = event.currentTarget.querySelector('i');
		icon?.classList.toggle('bi-eye', !reveal);
		icon?.classList.toggle('bi-eye-slash', reveal);
	});

	const loginForm = document.querySelector('[data-login-form]');
	loginForm?.addEventListener('submit', (event) => {
		if (!(loginForm instanceof HTMLFormElement)) {
			return;
		}

		if (!loginForm.checkValidity()) {
			event.preventDefault();
			loginForm.reportValidity();
			return;
		}

		const submitButton = loginForm.querySelector('[data-login-submit]');
		const label = loginForm.querySelector('[data-login-label]');
		const loading = loginForm.querySelector('[data-login-loading]');
		if (!(submitButton instanceof HTMLButtonElement) || submitButton.disabled) {
			event.preventDefault();
			return;
		}

		submitButton.disabled = true;
		label?.setAttribute('hidden', 'hidden');
		loading?.removeAttribute('hidden');
		loginForm.setAttribute('aria-busy', 'true');
	});

	document.querySelectorAll('[data-product-select]').forEach((button) => {
		button.addEventListener('click', () => {
			const product = JSON.parse(button.dataset.product ?? '{}');
			document.querySelectorAll('[data-product-row]').forEach((row) => row.classList.remove('is-selected'));
			button.closest('[data-product-row]')?.classList.add('is-selected');
			document.querySelectorAll('[data-product-field]').forEach((field) => {
				if (field instanceof HTMLInputElement) {
					field.value = product[field.dataset.productField] ?? '';
				}
			});
		});
	});

	document.querySelectorAll('[data-customer-select]').forEach((button) => {
		button.addEventListener('click', () => {
			const customer = JSON.parse(button.dataset.customer ?? '{}');
			document.querySelectorAll('[data-customer-row]').forEach((row) => row.classList.remove('is-selected'));
			button.closest('[data-customer-row]')?.classList.add('is-selected');
			document.querySelectorAll('[data-customer-field]').forEach((field) => {
			field.textContent = customer[field.dataset.customerField] || 'Não informado';
		});
		});
	});

	const layoutStorageKey = (layout) => `kflow-layout-${layout}`;
	const readLayout = (layout) => {
		try {
			return JSON.parse(window.localStorage.getItem(layoutStorageKey(layout)) ?? '{"order":[],"hidden":[]}');
		} catch {
			return { order: [], hidden: [] };
		}
	};
	const saveLayout = (layout, settings) => window.localStorage.setItem(layoutStorageKey(layout), JSON.stringify(settings));
	const applyLayout = (grid) => {
		const layout = grid.dataset.layoutGrid;
		if (!layout) return;
		const settings = readLayout(layout);
		const cards = [...grid.querySelectorAll(':scope > [data-layout-card]')];
		settings.order.forEach((id) => {
			const card = cards.find((item) => item.dataset.layoutCard === id);
			if (card) grid.append(card);
		});
		cards.forEach((card) => card.classList.toggle('is-layout-hidden', settings.hidden.includes(card.dataset.layoutCard)));
		const recovery = document.querySelector(`[data-layout-recovery="${layout}"]`);
		if (recovery) recovery.hidden = settings.hidden.length === 0;
	};

	document.querySelectorAll('[data-layout-grid]').forEach((grid) => {
		applyLayout(grid);
		let draggedCard = null;
		grid.querySelectorAll(':scope > [data-layout-card]').forEach((card) => {
			card.addEventListener('dragstart', (event) => {
				if (!grid.classList.contains('is-layout-editing')) {
					event.preventDefault();
					return;
				}
				draggedCard = card;
				card.classList.add('is-dragging');
			});
			card.addEventListener('dragend', () => {
				card.classList.remove('is-dragging');
				draggedCard = null;
				const layout = grid.dataset.layoutGrid;
				if (layout) saveLayout(layout, { ...readLayout(layout), order: [...grid.querySelectorAll(':scope > [data-layout-card]')].map((item) => item.dataset.layoutCard) });
			});
		});
		grid.addEventListener('dragover', (event) => {
			if (!draggedCard || !grid.classList.contains('is-layout-editing')) return;
			event.preventDefault();
			const after = [...grid.querySelectorAll(':scope > [data-layout-card]:not(.is-dragging)')].find((card) => {
				const rect = card.getBoundingClientRect();
				return event.clientY < rect.top + rect.height / 2;
			});
			if (after) grid.insertBefore(draggedCard, after); else grid.append(draggedCard);
		});
	});

	document.querySelectorAll('[data-layout-customize]').forEach((button) => {
		button.addEventListener('click', () => {
			const grid = document.querySelector(`[data-layout-grid="${button.dataset.layoutCustomize}"]`);
			if (!grid) return;
			const editing = grid.classList.toggle('is-layout-editing');
			grid.querySelectorAll(':scope > [data-layout-card]').forEach((card) => { card.draggable = editing; });
			button.classList.toggle('active', editing);
			button.innerHTML = editing ? '<i class="bi bi-check2"></i>Concluir organização' : '<i class="bi bi-sliders"></i>Organizar tela';
		});
	});

	document.querySelectorAll('[data-layout-hide]').forEach((button) => {
		button.addEventListener('click', () => {
			const card = button.closest('[data-layout-card]');
			const grid = button.closest('[data-layout-grid]');
			const layout = grid?.dataset.layoutGrid;
			if (!card || !layout) return;
			const settings = readLayout(layout);
			if (!settings.hidden.includes(card.dataset.layoutCard)) settings.hidden.push(card.dataset.layoutCard);
			saveLayout(layout, settings);
			applyLayout(grid);
		});
	});

	document.querySelectorAll('[data-layout-reset]').forEach((button) => {
		button.addEventListener('click', () => {
			const layout = button.dataset.layoutReset;
			if (!layout) return;
			window.localStorage.removeItem(layoutStorageKey(layout));
			const grid = document.querySelector(`[data-layout-grid="${layout}"]`);
			if (grid) window.location.reload();
		});
	});

	const connectionForm = document.querySelector('[data-connection-form]');
	connectionForm?.addEventListener('submit', () => {
		const submitButton = connectionForm.querySelector('[data-connection-submit]');
		if (!(submitButton instanceof HTMLButtonElement) || submitButton.disabled) {
			return;
		}

		submitButton.disabled = true;
		connectionForm.querySelector('[data-connection-label]')?.setAttribute('hidden', 'hidden');
		connectionForm.querySelector('[data-connection-loading]')?.removeAttribute('hidden');
		connectionForm.setAttribute('aria-busy', 'true');
	});

	const webServiceSelect = document.querySelector('[data-ws-service-select]');
	webServiceSelect?.addEventListener('change', () => {
		if (!(webServiceSelect instanceof HTMLSelectElement)) {
			return;
		}

		const operationInput = document.querySelector('[data-ws-operation]');
		const selectedOption = webServiceSelect.selectedOptions[0];
		if (operationInput instanceof HTMLInputElement && selectedOption?.dataset.operation) {
			operationInput.value = selectedOption.dataset.operation;
		}
	});


	if (body.dataset.kflowAsyncBound !== 'true') {
		body.dataset.kflowAsyncBound = 'true';
		document.addEventListener('click', async (event) => {
			const panelLink = event.target.closest('.kflow-binding-steps a:not(.is-disabled)');
			if (panelLink instanceof HTMLAnchorElement) {
				event.preventDefault();
				const response = await fetch(panelLink.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
				if (!response.ok) {
					window.location.assign(panelLink.href);
					return;
				}
				const documentResponse = new DOMParser().parseFromString(await response.text(), 'text/html');
				const nextPanel = documentResponse.querySelector('.kflow-connection-panel');
				const currentPanel = document.querySelector('.kflow-connection-panel');
				if (nextPanel && currentPanel) {
					currentPanel.replaceWith(nextPanel);
					window.history.pushState({}, '', panelLink.href);
					return;
				}
				window.location.assign(panelLink.href);
				return;
			}

			const clearMappingButton = event.target.closest('[data-clear-mapping]');
			if (clearMappingButton instanceof HTMLButtonElement) {
				const target = document.getElementById(clearMappingButton.dataset.clearMapping ?? '');
				if (target instanceof HTMLInputElement || target instanceof HTMLSelectElement) {
					target.value = '';
					target.dispatchEvent(new Event('change', { bubbles: true }));
					clearMappingButton.closest('.kflow-mapping-field')?.classList.add('is-cleared');
				}
				return;
			}

			const deleteFormButton = event.target.closest('[data-form-delete]');
			if (deleteFormButton instanceof HTMLButtonElement) {
				event.preventDefault();
				if (!window.confirm('Excluir este formulário e todos os vínculos de campos dele?')) {
					return;
				}

				deleteFormButton.disabled = true;
				try {
					const data = new FormData();
					data.set('_token', deleteFormButton.dataset.token ?? '');
					data.set('method', deleteFormButton.dataset.method ?? '');
					const response = await fetch(deleteFormButton.dataset.url ?? '', { method: 'POST', body: data, headers: { Accept: 'application/json' } });
					const payload = await response.json();
					if (!response.ok || !payload.ok) {
						throw new Error(payload.message ?? 'Não foi possível excluir o formulário.');
					}

					const formId = deleteFormButton.dataset.formId ?? '';
					const activeHiddenForm = document.querySelector('input[type="hidden"][name="form"]');
					if (activeHiddenForm instanceof HTMLInputElement && activeHiddenForm.value === formId) {
						const url = new URL(window.location.href);
						url.searchParams.set('form', 'products');
						window.location.assign(url);
						return;
					}

					document.querySelectorAll(`select[name="form"] option[value="${CSS.escape(formId)}"]`).forEach((option) => option.remove());
					deleteFormButton.closest('.kflow-form-chip')?.remove();
				} catch (error) {
					window.alert(error instanceof Error ? error.message : 'Não foi possível excluir o formulário.');
					deleteFormButton.disabled = false;
				}
				return;
			}

			const createButton = event.target.closest('[data-form-create]');
			const catalog = createButton?.closest('[data-form-catalog]');
			if (!(catalog instanceof HTMLElement) || !(createButton instanceof HTMLButtonElement)) {
				return;
			}

			event.preventDefault();
			const template = catalog.querySelector('[data-form-template]');
			const label = catalog.querySelector('[data-form-label]');
			const status = catalog.querySelector('[data-form-status]');
			if (!(template instanceof HTMLSelectElement) || !(label instanceof HTMLInputElement)) {
				return;
			}

			createButton.disabled = true;
			status.textContent = 'Criando...';
			try {
				const data = new FormData();
				data.set('_token', catalog.dataset.token ?? '');
				data.set('method', catalog.dataset.method ?? '');
				data.set('template', template.value);
				data.set('label', label.value);
				const response = await fetch(catalog.dataset.url ?? '', { method: 'POST', body: data, headers: { Accept: 'application/json' } });
				const payload = await response.json();
				if (!response.ok || !payload.ok) {
					throw new Error(payload.message ?? 'Não foi possível criar o formulário.');
				}

				document.querySelectorAll('select[name="form"]').forEach((select) => {
					const option = new Option(payload.form.label, payload.form.id, false, false);
					select.add(option);
				});
				const manager = catalog.parentElement?.querySelector('[data-form-manager] .kflow-form-manager__items');
				if (manager instanceof HTMLElement && payload.delete) {
					const chip = document.createElement('button');
					chip.className = 'kflow-form-chip';
					chip.type = 'button';
					chip.dataset.formDelete = '';
					chip.dataset.formId = payload.form.id;
					chip.dataset.method = catalog.dataset.method ?? '';
					chip.dataset.token = payload.delete.token;
					chip.dataset.url = payload.delete.url;
					chip.title = 'Excluir formulário e vínculos';
					chip.setAttribute('aria-label', `Excluir formulário ${payload.form.label}`);
					chip.innerHTML = `<span></span><i class="bi bi-trash3"></i>`;
					chip.querySelector('span').textContent = payload.form.label;
					manager.append(chip);
				}
				label.value = '';
				status.textContent = 'Formulário adicionado.';
			} catch (error) {
				status.textContent = error instanceof Error ? error.message : 'Não foi possível criar o formulário.';
			} finally {
				createButton.disabled = false;
			}
		});
	}
}

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', () => {
		initializeNavbarDropdowns();
		initializeKFlowShell();
	}, { once: true });
} else {
	initializeNavbarDropdowns();
	initializeKFlowShell();
}
