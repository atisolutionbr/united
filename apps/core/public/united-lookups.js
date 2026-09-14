document.querySelectorAll('[data-lookup]').forEach(root => {
    const input = root.querySelector('[data-lookup-search]');
    const value = root.querySelector('[data-lookup-value]');
    const token = root.querySelector('[data-lookup-token]');
    const results = root.querySelector('[data-lookup-results]');
    const status = root.querySelector('[data-lookup-status]');
    let timer, controller, currentQuery = '', active = -1, revision = 0;
    const close = () => { results.hidden = true; input.setAttribute('aria-expanded', 'false'); input.removeAttribute('aria-activedescendant'); };
    const cancel = () => { clearTimeout(timer); controller?.abort(); revision++; };
    const search = async (page = 1) => {
        cancel(); const requestRevision = revision; controller = new AbortController(); active = -1; close(); results.replaceChildren();
        const url = new URL(root.dataset.url, location.origin); url.searchParams.set('q', currentQuery); url.searchParams.set('page', String(page));
        status.textContent = 'Consultando a lista vinculada…'; root.setAttribute('aria-busy','true');
        try {
            const response = await fetch(url, {signal: controller.signal}); const data = await response.json();
            if (requestRevision !== revision) return;
            if (!response.ok || data.error) throw new Error(data.error || 'Consulta indisponível.');
            for (const [index, item] of data.items.entries()) {
                const option = document.createElement('button'); option.type = 'button'; option.setAttribute('role','option'); option.id = `${input.id}-option-${index}`;
                option.textContent = `${item.value} — ${item.label}${item.company ? ' · Empresa '+item.company : ''}${item.barcode ? ' · '+item.barcode : ''}`;
                option.addEventListener('click', () => { cancel(); value.value = item.value; token.value = item.token; input.value = `${item.value} — ${item.label}`; input.setCustomValidity(''); status.textContent = 'Item selecionado da origem vinculada.'; close(); root.removeAttribute('aria-busy'); });
                results.append(option);
            }
            if (page > 1) { const previous = document.createElement('button'); previous.type='button';previous.textContent='Página anterior';previous.addEventListener('click',()=>search(page-1));results.append(previous); }
            if (data.more) { const next = document.createElement('button'); next.type='button';next.textContent='Próxima página';next.addEventListener('click',()=>search(page+1));results.append(next); }
            results.hidden=false; input.setAttribute('aria-expanded','true');status.textContent=data.items.length ? `Página ${page} · ${data.items.length} opções. Selecione um item.` : 'Nenhum item encontrado para esta pesquisa.';
        } catch(error) { if (requestRevision === revision && error.name !== 'AbortError') { close();status.textContent=error.message || 'Consulta indisponível.'; } }
        finally { if (requestRevision === revision) root.removeAttribute('aria-busy'); }
    };
    const find = () => { currentQuery = value.value ? '' : input.value.trim(); search(); };
    input.addEventListener('focus', find);
    root.querySelector('[data-lookup-list]')?.addEventListener('click', () => { currentQuery='';search(); });
    root.querySelector('[data-lookup-find]')?.addEventListener('click', find);
    input.addEventListener('input', () => { cancel();close();value.value='';token.value='';currentQuery=input.value.trim();input.setCustomValidity(input.value ? 'Selecione um item da lista pesquisada.' : '');timer=setTimeout(()=>search(),300); });
    input.addEventListener('keydown', event => {
        const options=[...results.querySelectorAll('[role="option"]')];
        if (event.key==='Escape') {cancel();close();root.removeAttribute('aria-busy');}
        if (['ArrowDown','ArrowUp'].includes(event.key) && !results.hidden && options.length) { event.preventDefault();active=(active+(event.key==='ArrowDown'?1:-1)+options.length)%options.length;options.forEach((option,i)=>option.setAttribute('aria-selected',String(i===active)));input.setAttribute('aria-activedescendant',options[active].id);options[active].scrollIntoView({block:'nearest'}); }
        if (event.key==='Enter') { event.preventDefault();if(!results.hidden && options[active]) options[active].click();else find(); }
    });
    document.addEventListener('click', event=>{if(!root.contains(event.target)){cancel();close();root.removeAttribute('aria-busy');}});
});
