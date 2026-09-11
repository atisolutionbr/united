const body = document.body;

const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
const sidebarScrim = document.querySelector('[data-sidebar-scrim]');
if (localStorage.getItem('kflow-sidebar') === 'collapsed' && innerWidth > 820) body.classList.add('sidebar-is-collapsed');
sidebarToggle?.addEventListener('click', () => {
    if (innerWidth <= 820) return body.classList.toggle('sidebar-is-open');
    body.classList.toggle('sidebar-is-collapsed');
    localStorage.setItem('kflow-sidebar', body.classList.contains('sidebar-is-collapsed') ? 'collapsed' : 'expanded');
});
sidebarScrim?.addEventListener('click', () => body.classList.remove('sidebar-is-open'));
document.querySelectorAll('[data-sidebar-group]').forEach((toggle) => toggle.addEventListener('click', () => {
    const group = toggle.closest('.kflow-nav__group');
    const open = group?.classList.toggle('is-open') ?? false;
    toggle.setAttribute('aria-expanded', String(open));
}));

document.querySelector('[data-password-toggle]')?.addEventListener('click', () => {
    const input = document.querySelector('[data-password-input]');
    if (!(input instanceof HTMLInputElement)) return;
    input.type = input.type === 'password' ? 'text' : 'password';
});
document.querySelector('[data-login-form]')?.addEventListener('submit', (event) => {
    const form = event.currentTarget;
    if (!(form instanceof HTMLFormElement) || !form.checkValidity()) return;
    const button = form.querySelector('[data-login-submit]');
    if (!(button instanceof HTMLButtonElement) || button.disabled) return event.preventDefault();
    button.disabled = true;
    form.querySelector('[data-login-label]')?.setAttribute('hidden', 'hidden');
    form.querySelector('[data-login-loading]')?.removeAttribute('hidden');
});

const layoutKey = (name) => `kflow-layout-v7-${name}`;
const legacyLayoutKeys = (name) => [
    `kflow-layout-${name}`,
    `kflow-layout-v2-${name}`,
    `kflow-layout-v3-${name}`,
    `kflow-layout-v4-${name}`,
    `kflow-layout-v5-${name}`,
    `kflow-layout-v6-${name}`,
];
const cards = (grid) => [...grid.querySelectorAll(':scope > [data-layout-card]')];
const syncLayoutRecovery = (grid) => {
    const recovery = document.querySelector(`[data-layout-recovery="${grid.dataset.layoutGrid}"]`);
    if (recovery instanceof HTMLElement) recovery.hidden = !cards(grid).some((card) => card.classList.contains('is-layout-hidden'));
};
document.querySelectorAll('[data-layout-grid]').forEach((grid) => {
    const name = grid.dataset.layoutGrid;
    legacyLayoutKeys(name).forEach((key) => localStorage.removeItem(key));
    try {
        const saved = JSON.parse(localStorage.getItem(layoutKey(name)) || '{}');
        if (Array.isArray(saved.order) || Array.isArray(saved.hidden)) grid.classList.add('has-saved-layout');
        saved.order?.forEach((id) => { const card = cards(grid).find((item) => item.dataset.layoutCard === id); if (card) grid.append(card); });
        const hidden = Array.isArray(saved.hidden) ? saved.hidden : [];
        if (hidden.length >= cards(grid).length) {
            localStorage.removeItem(layoutKey(name));
        } else {
            cards(grid).forEach((card) => card.classList.toggle('is-layout-hidden', hidden.includes(card.dataset.layoutCard)));
        }
    } catch {}
    syncLayoutRecovery(grid);
    let dragging = null;
    cards(grid).forEach((card) => {
        card.addEventListener('dragstart', (event) => { if (!grid.classList.contains('is-layout-editing')) return event.preventDefault(); dragging = card; });
        card.addEventListener('dragend', () => { dragging = null; });
    });
    grid.addEventListener('dragover', (event) => {
        if (!dragging || !grid.classList.contains('is-layout-editing')) return;
        event.preventDefault();
        const after = cards(grid).find((card) => card !== dragging && event.clientY < card.getBoundingClientRect().top + card.offsetHeight / 2);
        after ? grid.insertBefore(dragging, after) : grid.append(dragging);
    });
});
document.querySelectorAll('[data-layout-customize]').forEach((button) => button.addEventListener('click', () => {
    const scope = button.dataset.layoutCustomize;
    const grids = [...document.querySelectorAll('[data-layout-grid]')].filter((grid) => grid.dataset.layoutGrid?.startsWith(scope));
    const editing = button.classList.toggle('is-editing');
        grids.forEach((grid) => {
            grid.classList.toggle('is-layout-editing', editing);
            if (editing) {
                grid.classList.add('has-saved-layout');
                cards(grid).forEach((card) => card.classList.remove('is-layout-hidden'));
            }
            cards(grid).forEach((card) => card.draggable = editing);
        });
    if (!editing) grids.forEach((grid) => { localStorage.setItem(layoutKey(grid.dataset.layoutGrid), JSON.stringify({order: cards(grid).map((card) => card.dataset.layoutCard), hidden: cards(grid).filter((card) => card.classList.contains('is-layout-hidden')).map((card) => card.dataset.layoutCard)})); syncLayoutRecovery(grid); });
    button.innerHTML = editing ? '<i class="bi bi-floppy"></i>Salvar layout' : '<i class="bi bi-sliders"></i>Personalizar';
    document.querySelector(`[data-layout-cancel="${scope}"]`)?.toggleAttribute('hidden', !editing);
}));
document.addEventListener('click', async (event) => {
    const cancel = event.target.closest('[data-layout-cancel]');
    if (cancel instanceof HTMLButtonElement) {
        const scope = cancel.dataset.layoutCancel;
        document.querySelectorAll('[data-layout-grid]').forEach((grid) => {
            if (!grid.dataset.layoutGrid?.startsWith(scope || '')) return;
            grid.classList.remove('is-layout-editing');
            grid.classList.remove('has-saved-layout');
            localStorage.removeItem(layoutKey(grid.dataset.layoutGrid));
            cards(grid).forEach((card) => { card.draggable = false; card.classList.remove('is-layout-hidden'); });
            syncLayoutRecovery(grid);
        });
        const customize = document.querySelector(`[data-layout-customize="${scope}"]`);
        customize?.classList.remove('is-editing');
        if (customize) customize.innerHTML = '<i class="bi bi-sliders"></i>Personalizar';
        cancel.hidden = true;
        return;
    }
    const hide = event.target.closest('[data-layout-hide]');
    if (hide && hide.closest('[data-layout-grid]')?.classList.contains('is-layout-editing')) {
        const grid = hide.closest('[data-layout-grid]');
        hide.closest('[data-layout-card]')?.classList.add('is-layout-hidden');
        if (grid instanceof HTMLElement) syncLayoutRecovery(grid);
        return;
    }
    const reset = event.target.closest('[data-layout-reset]');
    if (reset instanceof HTMLButtonElement) {
        const grid = document.querySelector(`[data-layout-grid="${reset.dataset.layoutReset}"]`);
        if (grid instanceof HTMLElement) {
            localStorage.removeItem(layoutKey(grid.dataset.layoutGrid));
            cards(grid).forEach((card) => card.classList.remove('is-layout-hidden'));
            syncLayoutRecovery(grid);
        }
        return;
    }
    const link = event.target.closest('.kflow-binding-steps a:not(.is-disabled)');
    if (!(link instanceof HTMLAnchorElement)) return;
    event.preventDefault();
    const response = await fetch(link.href, {headers: {'X-Requested-With': 'XMLHttpRequest'}});
    if (!response.ok) return location.assign(link.href);
    const next = new DOMParser().parseFromString(await response.text(), 'text/html').querySelector('.kflow-connection-panel');
    const current = document.querySelector('.kflow-connection-panel');
    if (!next || !current) return location.assign(link.href);
    current.replaceWith(next); history.pushState({}, '', link.href);
});
if ('serviceWorker' in navigator) addEventListener('load', () => navigator.serviceWorker.register('/kflow-sw.js?v=57', {updateViaCache: 'none'}));

document.querySelector('[data-diagnostic-copy]')?.addEventListener('click', async (event) => {
    const button = event.currentTarget;
    const source = document.querySelector('[data-diagnostic-base]');
    if (!(button instanceof HTMLButtonElement) || !(source instanceof HTMLElement)) return;
    const details = [
        source.textContent?.trim() || 'Diagnóstico — United · Ati Solution',
        `Online: ${navigator.onLine ? 'sim' : 'não'}`,
        `Idioma: ${navigator.language}`,
        `Tela: ${window.screen.width}x${window.screen.height}`,
        `User-Agent: ${navigator.userAgent}`,
    ].join('\n');
    try {
        await navigator.clipboard.writeText(details);
        button.innerHTML = '<i class="bi bi-check2"></i>Informações copiadas';
        window.setTimeout(() => button.innerHTML = '<i class="bi bi-copy"></i>Copiar informações de diagnóstico', 2200);
    } catch {
        window.prompt('Copie as informações de diagnóstico:', details);
    }
});

document.querySelector('[data-company-form]')?.addEventListener('change', async (event) => {
    if (!(event.target instanceof HTMLInputElement) || !event.target.matches('[data-company-cnpj]')) return;
    const form = event.currentTarget;
    const cnpj = event.target.value.replace(/\D/g, '');
    if (cnpj.length !== 14 || !(form instanceof HTMLElement)) return;
    const status = form.querySelector('[data-cnpj-status]');
    status.textContent = 'Consultando CNPJ...';
    try {
        const response = await fetch(form.dataset.cnpjUrl.replace('CNPJ', cnpj));
        const data = await response.json();
        if (!response.ok || !data.ok) throw new Error(data.message);
        form.querySelector('[data-company-name]').value = data.name || '';
        form.querySelector('[data-company-legal]').value = data.legalName || '';
        status.textContent = [data.address, data.phone, data.email].filter(Boolean).join(' · ') || 'Dados encontrados.';
    } catch (error) { status.textContent = error instanceof Error ? error.message : 'Consulta indisponível.'; }
});

document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-database-test]');
    if (!(button instanceof HTMLButtonElement)) return;
    const form = button.closest('form');
    const status = form?.querySelector('[data-database-test-status]');
    if (!(form instanceof HTMLFormElement) || !(status instanceof HTMLElement) || button.disabled) return;
    if (!form.reportValidity()) return;

    const original = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Testando';
    status.classList.remove('is-success', 'is-error');
    status.innerHTML = '<span><i class="bi bi-arrow-repeat"></i>Conectando diretamente ao ERP...</span>';
    try {
        const payload = new FormData(form);
        payload.set('_token', button.dataset.token || '');
        const response = await fetch(button.dataset.url || '', {method: 'POST', body: payload, headers: {'X-Requested-With': 'XMLHttpRequest'}});
        const data = await response.json();
        status.classList.add(data.ok ? 'is-success' : 'is-error');
        status.innerHTML = `<span><i class="bi bi-${data.ok ? 'check-circle-fill' : 'exclamation-triangle-fill'}"></i>${data.message || 'Não foi possível concluir o teste.'}</span>`;
    } catch {
        status.classList.add('is-error');
        status.innerHTML = '<span><i class="bi bi-exclamation-triangle-fill"></i>Não foi possível concluir o teste de conexão.</span>';
    } finally {
        button.disabled = false;
        button.innerHTML = original;
    }
});

document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-form-create]');
    if (!(button instanceof HTMLButtonElement) || button.disabled) return;
    const catalog = button.closest('[data-form-catalog]');
    if (!(catalog instanceof HTMLElement)) return;
    const template = catalog.querySelector('[data-form-template]');
    const label = catalog.querySelector('[data-form-label]');
    const status = catalog.querySelector('[data-form-status]');
    if (!(template instanceof HTMLSelectElement) || !(label instanceof HTMLInputElement) || !(status instanceof HTMLElement)) return;

    const original = button.innerHTML;
    button.disabled = true;
    status.textContent = 'Criando formulário...';
    try {
        const payload = new FormData();
        payload.set('_token', catalog.dataset.token || '');
        payload.set('method', catalog.dataset.method || 'database');
        payload.set('template', template.value);
        payload.set('label', label.value.trim());
        const response = await fetch(catalog.dataset.url || '', {method: 'POST', body: payload, headers: {'X-Requested-With': 'XMLHttpRequest'}});
        const data = await response.json();
        if (!response.ok || !data.ok || !data.form?.id) throw new Error(data.message || 'Não foi possível criar o formulário.');
        const query = new URLSearchParams(location.search);
        query.set('method', catalog.dataset.method || 'database');
        query.set('step', (catalog.dataset.method || 'database') === 'database' ? 'table' : 'mapping');
        query.set('form', data.form.id);
        location.assign(`${location.pathname}?${query.toString()}`);
    } catch (error) {
        status.textContent = error instanceof Error ? error.message : 'Não foi possível criar o formulário.';
    } finally {
        button.disabled = false;
        button.innerHTML = original;
    }
});

document.querySelectorAll('[data-company-picker]').forEach((picker) => {
    const filter = picker.querySelector('[data-company-filter]');
    const options = [...picker.querySelectorAll('[data-company-option]')];
    const count = picker.querySelector('[data-company-selected-count]');
    const selectVisible = picker.querySelector('[data-company-select-visible]');
    const sync = () => {
        const selected = options.filter((option) => option.querySelector('input')?.checked).length;
        if (count) count.textContent = String(selected);
    };
    const applyFilter = () => {
        const term = filter instanceof HTMLInputElement ? filter.value.trim().toLocaleLowerCase('pt-BR') : '';
        options.forEach((option) => option.hidden = !option.textContent.toLocaleLowerCase('pt-BR').includes(term));
    };
    filter?.addEventListener('input', applyFilter);
    picker.addEventListener('change', (event) => { if (event.target instanceof HTMLInputElement && event.target.type === 'checkbox') sync(); });
    selectVisible?.addEventListener('click', () => { options.filter((option) => !option.hidden).forEach((option) => { const input = option.querySelector('input'); if (input instanceof HTMLInputElement) input.checked = true; }); sync(); });
    sync();
});

document.querySelectorAll('[data-party-select]').forEach((button) => {
    button.addEventListener('click', () => {
        const party = JSON.parse(button.dataset.party ?? '{}');
        document.querySelectorAll('[data-party-row]').forEach((row) => row.classList.remove('is-selected'));
        button.closest('[data-party-row]')?.classList.add('is-selected');
        document.querySelectorAll('[data-party-field]').forEach((field) => field.textContent = party[field.dataset.partyField] || 'Não informado');
    });
});

const registrationText = (value) => String(value ?? '').trim();
const normalizedRegistrationText = (value) => registrationText(value)
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^A-Za-z0-9]/g, '')
    .toUpperCase();
const registrationMatches = (erpValue, registryValue) => {
    const erp = normalizedRegistrationText(erpValue);
    const registry = normalizedRegistrationText(registryValue);
    return '' !== erp && '' !== registry && erp === registry;
};

const renderRegistrationComparison = (review, record, registry) => {
    const results = review.querySelector('[data-registration-results]');
    if (!(results instanceof HTMLElement)) return;
    const registrations = Array.isArray(registry.stateRegistrations) ? registry.stateRegistrations : [];
    const activeRegistration = registrations.find((item) => item?.active && item?.state === (record.SigUfs || record.State)) || registrations.find((item) => item?.active) || registrations[0];
    const fields = [
        ['Razão social', record.NomCli || record.Name, registry.legalName],
        ['E-mail', record.EmlCli || record.Email, registry.email],
        ['Endereço', record.EndCli || record.Address, registry.address],
        ['Cidade', record.CidCli || record.City, registry.city],
        ['UF', record.SigUfs || record.State, registry.state],
        ['Inscrição estadual', record.InsEst || record.StateRegistration, activeRegistration?.number || 'Não encontrada'],
    ];
    const fragment = document.createDocumentFragment();
    const heading = document.createElement('strong');
    heading.textContent = `Base cadastral: ${registry.legalName || 'CNPJ consultado'}`;
    fragment.append(heading);
    fields.forEach(([label, erpValue, registryValue]) => {
        const row = document.createElement('div');
        row.className = `kflow-registration-result${registrationMatches(erpValue, registryValue) ? ' is-match' : ' is-different'}`;
        const field = document.createElement('small');
        field.textContent = label;
        const values = document.createElement('span');
        values.textContent = `ERP: ${registrationText(erpValue) || 'Não informado'} | Base: ${registrationText(registryValue) || 'Não informado'}`;
        row.append(field, values);
        fragment.append(row);
    });
    if (registrations.length > 1) {
        const note = document.createElement('small');
        note.textContent = `Foram encontradas ${registrations.length} inscrições estaduais para este CNPJ.`;
        fragment.append(note);
    }
    results.replaceChildren(fragment);
    results.hidden = false;
};

document.addEventListener('click', async (event) => {
    if (!(event.target instanceof Element)) return;
    const selected = event.target.closest('[data-customer-select], [data-party-select]');
    if (selected instanceof HTMLElement) {
        const record = JSON.parse(selected.dataset.customer || selected.dataset.party || '{}');
        document.querySelectorAll('[data-registration-compare]').forEach((button) => {
            if (!(button instanceof HTMLElement)) return;
            button.dataset.registrationDocument = record.CgcCpf || record.Document || '';
            button.dataset.registrationRecord = JSON.stringify(record);
        });
        document.querySelectorAll('[data-registration-results]').forEach((result) => { result.hidden = true; result.replaceChildren(); });
        document.querySelectorAll('[data-registration-status]').forEach((status) => status.textContent = 'Consulte o CNPJ selecionado para comparar o cadastro do ERP com a base pública.');
        return;
    }

    const button = event.target.closest('[data-registration-compare]');
    if (!(button instanceof HTMLButtonElement) || button.disabled) return;
    const review = button.closest('[data-registration-review]');
    const status = review?.querySelector('[data-registration-status]');
    const documentNumber = (button.dataset.registrationDocument || '').replace(/\D/g, '');
    if (!(review instanceof HTMLElement) || !(status instanceof HTMLElement)) return;
    if (documentNumber.length !== 14) {
        status.textContent = documentNumber.length === 11 ? 'A comparação externa está disponível somente para CNPJ.' : 'Informe um CNPJ válido no cadastro do ERP para fazer a comparação.';
        return;
    }
    const original = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Consultando';
    status.textContent = 'Consultando base cadastral...';
    try {
        const response = await fetch((review.dataset.registrationUrl || '').replace('__DOCUMENT__', documentNumber), {headers: {'Accept': 'application/json'}});
        const data = await response.json();
        if (!response.ok || !data.ok || !data.registry) throw new Error(data.message || 'Não foi possível consultar o CNPJ.');
        renderRegistrationComparison(review, JSON.parse(button.dataset.registrationRecord || '{}'), data.registry);
        status.textContent = `Comparação concluída com dados do ${data.source || 'cadastro público'}.`;
    } catch (error) {
        status.textContent = error instanceof Error ? error.message : 'Não foi possível consultar o CNPJ.';
    } finally {
        button.disabled = false;
        button.innerHTML = original;
    }
});
