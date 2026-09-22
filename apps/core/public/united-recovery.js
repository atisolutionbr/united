const status = document.querySelector('[data-erp-recovery]');
if (status) {
    let delay = 15000;
    let edited = false;
    const company = document.querySelector('.kflow-main')?.dataset.companyId;
    document.addEventListener('input', () => {
        edited = true;
        status.textContent = 'Atualização automática pausada para preservar sua edição. Reabra a consulta após salvar.';
    }, { once: true });
    async function retry() {
        if (edited) return;
        if (document.hidden || !navigator.onLine) { setTimeout(retry, delay); return; }
        try {
            // Only GET reads are retried. ERP writes must never be replayed here.
            const response = await fetch(location.href, { credentials: 'same-origin', cache: 'no-store', signal: AbortSignal.timeout(12000) });
            if (response.redirected) return;
            if (response.ok) {
                const page = new DOMParser().parseFromString(await response.text(), 'text/html');
                const main = page.querySelector('.kflow-main');
                if (main && main.dataset.companyId !== company) return;
                if (!edited && main && !page.querySelector('[data-erp-recovery]')) { location.reload(); return; }
            }
        } catch { /* A failed read leaves saved settings and the current page intact. */ }
        delay = Math.min(60000, delay * 2);
        setTimeout(retry, delay);
    }
    setTimeout(retry, delay);
}
