document.querySelectorAll('[data-lookup]').forEach(root => {
    const trigger = root.querySelector('[data-lookup-list]');
    const display = root.querySelector('[data-lookup-display]');
    const input = root.querySelector('[data-lookup-search]');
    const value = root.querySelector('[data-lookup-value]');
    const token = root.querySelector('[data-lookup-token]');
    const panel = root.querySelector('[data-lookup-panel]');
    const results = root.querySelector('[data-lookup-results]');
    const status = root.querySelector('[data-lookup-status]');
    const pagination = root.querySelector('[data-lookup-pagination]');
    if (!trigger || !panel || !input || !results) return;
    let timer, controller, revision = 0, active = -1;
    const cancel = () => { clearTimeout(timer); controller?.abort(); revision++; };
    const close = (restoreFocus = false) => {
        cancel(); panel.hidden = true; root.classList.remove('is-open');
        trigger.setAttribute('aria-expanded', 'false'); input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant'); results.removeAttribute('aria-busy');
        if (restoreFocus) trigger.focus();
    };
    const select = item => {
        value.value = item.value; token.value = item.token;
        display.textContent = `${item.value} — ${item.label}`;
        trigger.removeAttribute('aria-invalid'); close(true);
    };
    const search = async (page = 1) => {
        cancel(); const currentRevision = revision;
        controller = new AbortController(); const currentController = controller;
        const timeout = setTimeout(() => currentController.abort(), 12000);
        active = -1; results.replaceChildren(); pagination.replaceChildren(); pagination.hidden = true;
        input.removeAttribute('aria-activedescendant'); results.setAttribute('aria-busy', 'true');
        status.textContent = 'Carregando itens da origem vinculada…'; status.classList.remove('is-error');
        const url = new URL(root.dataset.url, location.origin);
        url.searchParams.set('q', input.value.trim()); url.searchParams.set('page', String(page));
        try {
            const response = await fetch(url, {signal: currentController.signal, headers: {'Accept': 'application/json'}});
            if (response.redirected && new URL(response.url).pathname === '/login') throw new Error('Sua sessão expirou. Entre novamente para consultar a lista.');
            const data = await response.json();
            if (currentRevision !== revision || panel.hidden) return;
            if (!response.ok || data.error) throw new Error(data.error || 'Não foi possível consultar esta lista. Confira o vínculo e tente novamente.');
            if (!Array.isArray(data.items)) throw new Error('A origem não retornou uma lista válida. Confira o vínculo.');
            data.items.forEach((item, index) => {
                const option = document.createElement('button'); option.type = 'button'; option.tabIndex = -1;
                option.setAttribute('role', 'option'); option.setAttribute('aria-selected', 'false');
                option.id = `${trigger.id}-option-${index}`;
                const name = document.createElement('strong'); name.textContent = item.label || item.value;
                const detail = document.createElement('small');
                detail.textContent = [`Cód: ${item.value}`, item.barcode ? `Barras: ${item.barcode}` : '', item.company ? `Empresa: ${item.company}` : ''].filter(Boolean).join(' · ');
                option.append(name, detail); option.addEventListener('click', () => select(item)); results.append(option);
            });
            const addPage = (label, target) => {
                const button = document.createElement('button'); button.type = 'button'; button.textContent = label;
                button.addEventListener('click', () => { input.focus(); search(target); }); pagination.append(button);
            };
            if (page > 1) addPage('Anterior', page - 1);
            const pageLabel = document.createElement('span'); pageLabel.textContent = `Página ${page}`; pagination.append(pageLabel);
            if (data.more) addPage('Próxima', page + 1);
            pagination.hidden = page === 1 && !data.more;
            status.textContent = data.items.length ? `${data.items.length} opções. Selecione um item ou pesquise acima.` : 'Nenhum item encontrado. Altere a pesquisa ou confira o vínculo.';
        } catch (error) {
            if (currentRevision !== revision || panel.hidden) return;
            status.classList.add('is-error');
            status.textContent = error.name === 'AbortError' ? 'A consulta demorou além do esperado. Confira a conexão do ERP e tente novamente.' : (error.message || 'Consulta indisponível. Confira a conexão do ERP.');
            const retry = document.createElement('button'); retry.type = 'button'; retry.textContent = 'Tentar novamente';
            retry.addEventListener('click', () => { input.focus(); search(page); }); pagination.replaceChildren(retry); pagination.hidden = false;
        } finally {
            clearTimeout(timeout);
            if (currentRevision === revision) results.removeAttribute('aria-busy');
        }
    };
    const open = () => {
        document.dispatchEvent(new CustomEvent('united:lookup-open', {detail: root}));
        panel.hidden = false; root.classList.add('is-open');
        trigger.setAttribute('aria-expanded', 'true'); input.setAttribute('aria-expanded', 'true');
        input.value = ''; input.focus(); search();
    };
    trigger.addEventListener('click', () => panel.hidden ? open() : close());
    trigger.addEventListener('keydown', event => {
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') { event.preventDefault(); open(); }
        if (event.key === 'Escape') close();
    });
    input.addEventListener('input', () => {
        cancel(); active = -1; results.replaceChildren(); pagination.hidden = true;
        input.removeAttribute('aria-activedescendant'); status.textContent = 'Pesquisando…';
        timer = setTimeout(() => search(), 300);
    });
    input.addEventListener('keydown', event => {
        const options = [...results.querySelectorAll('[role="option"]')];
        if (event.key === 'Escape') { event.preventDefault(); close(true); }
        if (['ArrowDown', 'ArrowUp'].includes(event.key) && options.length) {
            event.preventDefault(); active = (active + (event.key === 'ArrowDown' ? 1 : -1) + options.length) % options.length;
            options.forEach((option, index) => option.setAttribute('aria-selected', String(index === active)));
            input.setAttribute('aria-activedescendant', options[active].id); options[active].scrollIntoView({block: 'nearest'});
        }
        if (event.key === 'Enter') { event.preventDefault(); if (options[active]) options[active].click(); else search(); }
    });
    root.querySelector('[data-lookup-clear]').addEventListener('click', () => {
        value.value = ''; token.value = ''; display.textContent = display.dataset.placeholder;
        trigger.removeAttribute('aria-invalid'); close(true);
    });
    document.addEventListener('united:lookup-open', event => { if (event.detail !== root) close(); });
    document.addEventListener('click', event => { if (!event.composedPath().includes(root)) close(); });
    root.addEventListener('focusout', () => setTimeout(() => { if (!root.contains(document.activeElement)) close(); }, 0));
    root.closest('form')?.addEventListener('submit', event => {
        if (event.defaultPrevented) return;
        if ((root.dataset.required === 'true' && !value.value) || (value.value && !token.value)) {
            event.preventDefault(); trigger.setAttribute('aria-invalid', 'true'); open();
        }
    });
});
