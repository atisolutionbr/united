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

const layoutKey = (name) => `kflow-layout-${name}`;
const cards = (grid) => [...grid.querySelectorAll(':scope > [data-layout-card]')];
document.querySelectorAll('[data-layout-grid]').forEach((grid) => {
    const name = grid.dataset.layoutGrid;
    try {
        const saved = JSON.parse(localStorage.getItem(layoutKey(name)) || '{}');
        saved.order?.forEach((id) => { const card = cards(grid).find((item) => item.dataset.layoutCard === id); if (card) grid.append(card); });
        cards(grid).forEach((card) => card.classList.toggle('is-layout-hidden', saved.hidden?.includes(card.dataset.layoutCard)));
    } catch {}
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
    const editing = !button.classList.toggle('is-editing');
    grids.forEach((grid) => { grid.classList.toggle('is-layout-editing', editing); cards(grid).forEach((card) => card.draggable = editing); });
    if (!editing) grids.forEach((grid) => localStorage.setItem(layoutKey(grid.dataset.layoutGrid), JSON.stringify({order: cards(grid).map((card) => card.dataset.layoutCard), hidden: cards(grid).filter((card) => card.classList.contains('is-layout-hidden')).map((card) => card.dataset.layoutCard)})));
    button.innerHTML = editing ? '<i class="bi bi-floppy"></i>Salvar layout' : '<i class="bi bi-sliders"></i>Personalizar';
}));
document.addEventListener('click', async (event) => {
    const hide = event.target.closest('[data-layout-hide]');
    if (hide && hide.closest('[data-layout-grid]')?.classList.contains('is-layout-editing')) hide.closest('[data-layout-card]')?.classList.add('is-layout-hidden');
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
if ('serviceWorker' in navigator) addEventListener('load', () => navigator.serviceWorker.register('/kflow-sw.js'));

document.querySelector('[data-diagnostic-copy]')?.addEventListener('click', async (event) => {
    const button = event.currentTarget;
    const source = document.querySelector('[data-diagnostic-base]');
    if (!(button instanceof HTMLButtonElement) || !(source instanceof HTMLElement)) return;
    const details = [
        source.textContent?.trim() || 'Diagnóstico — KFlow360',
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
