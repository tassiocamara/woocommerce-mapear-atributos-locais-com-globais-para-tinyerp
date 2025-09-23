/* global Local2GlobalSettings */
(function (wp, settings) {
    if (!settings || !settings.productId) {
        return;
    }

    const { __ } = wp.i18n;
    const apiFetch = wp.apiFetch;

    apiFetch.use(apiFetch.createNonceMiddleware(settings.rest.nonce));

    const state = {
        productId: settings.productId,
        stepIndex: 0,
        attributes: [],
        mapping: [],
        options: {}, // opções avançadas removidas (modo determinístico)
        dryRun: null,
        dryRunError: null,
        _dryRunRequested: false,
        log: [],
    };

    const modal = document.getElementById('local2global-modal');
    const modalBody = modal ? modal.querySelector('.local2global-modal__body') : null;
    const modalTitle = modal ? modal.querySelector('#local2global-modal-title') : null;
    const nextButton = modal ? modal.querySelector('.local2global-next') : null;
    const prevButton = modal ? modal.querySelector('.local2global-prev') : null;

    const steps = [
        {
            id: 'discover',
            title: settings.i18n.discover,
            render: renderDiscoverStep,
        },
        {
            id: 'select-attribute',
            title: __('Selecionar atributos globais', 'local2global'),
            render: renderSelectAttributeStep,
        },
        {
            id: 'term-matrix',
            title: __('Matriz de mapeamento de termos', 'local2global'),
            render: renderTermMatrixStep,
        },
        {
            id: 'dry-run',
            title: settings.i18n.dryRunTitle,
            render: renderDryRunStep,
        },
        {
            id: 'apply',
            title: settings.i18n.applyTitle,
            render: renderApplyStep,
        },
    ];

    function openModal() {
        if (!modal) {
            return;
        }

        state.stepIndex = 0;
        state.log = [];
        state.dryRun = null;
        state.dryRunError = null;
        state._dryRunRequested = false;

        discoverAttributes().then(() => {
            modal.removeAttribute('hidden');
            document.body.classList.add('modal-open');
            renderStep();
            focusModal();
        }).catch((error) => {
            window.alert(error.message || error);
        });
    }

    function closeModal() {
        if (!modal) {
            return;
        }

        modal.setAttribute('hidden', 'hidden');
        document.body.classList.remove('modal-open');
    }

    function focusModal() {
        window.requestAnimationFrame(() => {
            modalBody && modalBody.focus();
        });
    }

    function renderStep() {
        if (!modalBody || !modalTitle) {
            return;
        }

        const step = steps[state.stepIndex];
        modalTitle.textContent = step.title;

        modalBody.innerHTML = '';
        step.render(modalBody);

        prevButton.disabled = state.stepIndex === 0;
        prevButton.textContent = __('Voltar', 'local2global');

        if (state.stepIndex === steps.length - 1) {
            nextButton.textContent = __('Fechar', 'local2global');
        } else if (steps[state.stepIndex].id === 'dry-run') {
            nextButton.textContent = state.dryRun ? settings.i18n.apply : settings.i18n.dryRun;
        } else {
            nextButton.textContent = __('Continuar', 'local2global');
        }

        nextButton.disabled = false;
    }

    function renderDiscoverStep(container) {
        const info = document.createElement('div');
        info.innerHTML = '<p>' + __('Abaixo estão os atributos locais detectados no produto.', 'local2global') + '</p>';
        container.appendChild(info);

        const table = document.createElement('table');
        table.className = 'local2global-attribute-table';
        table.innerHTML = '<thead><tr><th>' + __('Atributo local', 'local2global') + '</th><th>' + __('Valores', 'local2global') + '</th><th>' + __('Uso', 'local2global') + '</th></tr></thead>';
        const tbody = document.createElement('tbody');

        state.attributes.forEach((attr) => {
            const tr = document.createElement('tr');
            const badge = attr.in_variations ? '<span class="local2global-tag">' + __('Variações', 'local2global') + '</span>' : '';
            tr.innerHTML = '<td>' + escapeHtml(attr.label) + '</td>' +
                '<td>' + attr.values.map(escapeHtml).join(', ') + '</td>' +
                '<td><span class="local2global-tag">' + __('Atributos', 'local2global') + '</span>' + badge + '</td>';
            tbody.appendChild(tr);
        });

        table.appendChild(tbody);
        container.appendChild(table);
    }

    function renderSelectAttributeStep(container) {
        const intro = document.createElement('p');
        intro.textContent = __('Associe cada atributo local a um atributo global existente ou escolha criar automaticamente. Slug e rótulo serão derivados do nome local.', 'local2global');
        container.appendChild(intro);

        state.mapping.forEach((map, index) => {
            const wrapper = document.createElement('div');
            wrapper.className = 'local2global-attribute-card';

            const heading = document.createElement('h3');
            heading.textContent = map.local_label;
            wrapper.appendChild(heading);

            const select = document.createElement('select');
            select.dataset.index = index;
            select.className = 'local2global-attr-select';
            select.innerHTML = '<option value="">' + __('— Selecionar atributo global —', 'local2global') + '</option>';
            settings.attributes.forEach((attr) => {
                const option = document.createElement('option');
                option.value = attr.slug;
                option.textContent = attr.label + ' (' + attr.slug + ')';
                if (!map.create_attribute && attr.slug === map.target_tax) {
                    option.selected = true;
                }
                select.appendChild(option);
            });
            const createOption = document.createElement('option');
            createOption.value = '__create_new__';
            createOption.textContent = __('Criar novo atributo', 'local2global');
            if (map.create_attribute) {
                createOption.selected = true;
            }
            select.appendChild(createOption);

            select.addEventListener('change', (e) => {
                const entry = state.mapping[parseInt(e.target.dataset.index, 10)];
                if (e.target.value === '__create_new__') {
                    entry.create_attribute = true;
                    entry.target_tax = 'pa_' + slugify(entry.local_label);
                } else {
                    entry.create_attribute = false;
                    entry.target_tax = e.target.value;
                }
                renderStep();
            });
            wrapper.appendChild(select);

            if (map.create_attribute) {
                const hint = document.createElement('p');
                hint.className = 'description';
                hint.textContent = __('Será criado atributo global: ', 'local2global') + map.target_tax;
                wrapper.appendChild(hint);
            }
            container.appendChild(wrapper);
        });
    }

    function renderTermMatrixStep(container) {
        const intro = document.createElement('p');
        intro.textContent = __('Associe cada valor local a um termo global existente ou selecione criar novo. O nome e slug do termo criado serão derivados do valor local.', 'local2global');
        container.appendChild(intro);

        state.mapping.forEach((map, mapIndex) => {
            const block = document.createElement('section');
            block.innerHTML = '<h3>' + escapeHtml(map.local_label) + '</h3>';

            const table = document.createElement('table');
            table.className = 'local2global-attribute-table';
            table.innerHTML = '<thead><tr><th>' + __('Valor local', 'local2global') + '</th><th>' + __('Termo global', 'local2global') + '</th></tr></thead>';
            const tbody = document.createElement('tbody');

            map.terms.forEach((term, termIndex) => {
                const tr = document.createElement('tr');
                const localValueCell = document.createElement('td');
                localValueCell.textContent = term.local_value;
                tr.appendChild(localValueCell);

                const globalCell = document.createElement('td');
                const select = document.createElement('select');
                select.className = 'local2global-term-select';
                select.dataset.attrIndex = mapIndex;
                select.dataset.termIndex = termIndex;
                select.innerHTML = '<option value="">' + __('— Selecionar termo —', 'local2global') + '</option>';

                ensureTermOptions(map).forEach((termOption) => {
                    const option = document.createElement('option');
                    option.value = termOption.slug;
                    option.textContent = termOption.name + ' (' + termOption.slug + ')';
                    if (termOption.slug === term.term_slug && !term.create) {
                        option.selected = true;
                    }
                    select.appendChild(option);
                });

                // Opção para criar novo termo
                const createValue = '__create__';
                const createOption = document.createElement('option');
                createOption.value = createValue;
                createOption.textContent = __('Criar novo termo', 'local2global') + ' (' + term.local_value + ')';
                if (term.create) {
                    createOption.selected = true;
                }
                select.appendChild(createOption);

                select.addEventListener('change', (event) => {
                    const attrIdx = parseInt(event.target.dataset.attrIndex, 10);
                    const tIdx = parseInt(event.target.dataset.termIndex, 10);
                    const entry = state.mapping[attrIdx].terms[tIdx];
                    if (event.target.value === createValue) {
                        entry.create = true;
                        entry.term_slug = ''; // slug será gerado no backend
                    } else {
                        entry.create = false;
                        entry.term_slug = event.target.value;
                    }
                });

                globalCell.appendChild(select);
                tr.appendChild(globalCell);
                tbody.appendChild(tr);
            });

            table.appendChild(tbody);
            block.appendChild(table);
            container.appendChild(block);
        });
    }


    function renderDryRunStep(container) {
        if (!state.dryRun && !state.dryRunError) {
            container.innerHTML = '<p>' + __('Calculando pré-visualização…', 'local2global') + '</p>';
            // Dispara automaticamente uma única vez ao entrar na etapa.
            if (!state._dryRunRequested) {
                state._dryRunRequested = true;
                performDryRun();
            }
            return;
        }

        if (state.dryRunError) {
            const errorBox = document.createElement('div');
            errorBox.className = 'notice notice-error';
            errorBox.innerHTML = '<p><strong>' + __('Falha ao calcular pré-visualização:', 'local2global') + '</strong> ' + escapeHtml(state.dryRunError.message) + '</p>' + (state.dryRunError.details ? '<pre>' + escapeHtml(state.dryRunError.details) + '</pre>' : '');
            const retryBtn = document.createElement('button');
            retryBtn.type = 'button';
            retryBtn.className = 'button';
            retryBtn.textContent = __('Tentar novamente', 'local2global');
            retryBtn.addEventListener('click', () => {
                state.dryRunError = null;
                state.dryRun = null;
                state._dryRunRequested = false;
                renderStep();
            });
            errorBox.appendChild(retryBtn);
            container.appendChild(errorBox);
            return;
        }

        const preview = state.dryRun;
        const issues = Array.isArray(preview?.errors) ? preview.errors : [];

        if (issues.length) {
            const errorsBox = document.createElement('div');
            errorsBox.className = 'notice notice-error';
            errorsBox.innerHTML = '<p><strong>' + __('Problemas encontrados:', 'local2global') + '</strong></p><ul>' +
                issues.map((err) => '<li>' + escapeHtml(err) + '</li>').join('') + '</ul>';
            container.appendChild(errorsBox);
        }

        (preview.attributes || []).forEach((attr) => {
            const section = document.createElement('section');
            section.innerHTML = '<h3>' + escapeHtml(attr.local_label) + '</h3>';
            const summary = document.createElement('div');
            summary.className = 'local2global-summary';
            summary.innerHTML = '<p>' + __('Taxonomia alvo:', 'local2global') + ' <strong>' + escapeHtml(attr.target_tax) + '</strong></p>' +
                '<p>' + __('Termos existentes:', 'local2global') + ' ' + attr.terms.existing.length + '</p>' +
                '<p>' + __('Termos a criar:', 'local2global') + ' ' + attr.terms.create.length + '</p>';
            section.appendChild(summary);
            container.appendChild(section);
        });
    }

    function renderApplyStep(container) {
        const summary = document.createElement('div');
        summary.innerHTML = '<p>' + __('Acompanhe o progresso. Os logs são atualizados automaticamente.', 'local2global') + '</p>';
        container.appendChild(summary);

        const logContainer = document.createElement('pre');
        logContainer.className = 'local2global-log';
        logContainer.textContent = state.log.join('\n');
        container.appendChild(logContainer);

        // Se o último log contém JSON de resultado, tentar parse para extrair resumo de variações.
        const lastJson = (() => {
            for (let i = state.log.length - 1; i >= 0; i--) {
                const line = state.log[i];
                if (line && line.startsWith('{') && line.endsWith('}')) {
                    try { return JSON.parse(line); } catch (e) { /* ignore */ }
                }
            }
            return null;
        })();

        if (lastJson && lastJson.variations) {
            const table = document.createElement('table');
            table.className = 'local2global-variation-summary';
            table.innerHTML = '<thead><tr>' +
                '<th>' + __('Taxonomia', 'local2global') + '</th>' +
                '<th>' + __('Atualizadas', 'local2global') + '</th>' +
                '<th>' + __('Ignoradas', 'local2global') + '</th>' +
                '<th>' + __('Total', 'local2global') + '</th>' +
                '<th>%</th>' +
                '<th>' + __('Razões', 'local2global') + '</th>' +
                '</tr></thead>';
            const tbody = document.createElement('tbody');
            Object.keys(lastJson.variations).forEach((tax) => {
                const stats = lastJson.variations[tax] || {};
                const tr = document.createElement('tr');
                const pct = typeof stats.updated_pct === 'number' ? stats.updated_pct : (stats.total_variations ? (stats.updated / stats.total_variations * 100).toFixed(2) : '0');
                const reasons = stats.reasons ? Object.entries(stats.reasons).filter(([,v]) => v>0).map(([k,v]) => k + ':' + v).join(', ') : '';
                tr.innerHTML = '<td>' + tax + '</td>' +
                    '<td>' + (stats.updated || 0) + '</td>' +
                    '<td>' + (stats.skipped || 0) + '</td>' +
                    '<td>' + (stats.total_variations || 0) + '</td>' +
                    '<td>' + pct + '</td>' +
                    '<td>' + reasons + '</td>';
                tbody.appendChild(tr);
            });
            table.appendChild(tbody);
            const header = document.createElement('h3');
            header.textContent = __('Resumo de variações', 'local2global');
            container.appendChild(header);
            container.appendChild(table);
        }
    }

    function discoverAttributes() {
        return apiFetch({
            path: '/local2global/v1/discover?product_id=' + state.productId,
        }).then((data) => {
            state.attributes = data.attributes || [];
            state.mapping = state.attributes.map((attr) => buildMappingFromAttribute(attr));
        });
    }

    function buildMappingFromAttribute(attr) {
        return {
            local_attr: attr.name,
            local_label: attr.label,
            target_tax: '',
            create_attribute: false,
            terms: attr.values.map((value) => ({
                local_value: value,
                term_slug: '',
                create: false,
            })),
            termOptions: [],
        };
    }

    function ensureTermOptions(map) {
        if (!map.termOptions || !map.termOptions.length) {
            loadTermOptions(map).then(() => {
                if (steps[state.stepIndex]?.id === 'term-matrix') {
                    renderStep();
                }
            });
        }
        return map.termOptions || [];
    }

    function loadTermOptions(map) {
        if (!map.target_tax) {
            return Promise.resolve([]);
        }

        return apiFetch({
            path: '/local2global/v1/terms/' + map.target_tax,
        }).then((response) => {
            map.termOptions = response.terms || [];
            autoMapAttributeTerms(map);
            return map.termOptions;
        }).catch(() => {
            map.termOptions = [];
        });
    }

    // Manipuladores de nome/slug removidos (simplificação UX)

    function performDryRun() {
        nextButton.disabled = true;
        state.dryRun = null;
        state.dryRunError = null;
        return apiFetch({
            path: '/local2global/v1/map',
            method: 'POST',
            data: {
                product_id: state.productId,
                mapping: serializeMapping(),
                options: state.options,
                mode: 'dry-run',
            },
        }).then((result) => {
            state.dryRun = result?.result || result;
            state.dryRunCorrId = result?.corr_id || null;
        }).catch((error) => {
            const formatted = formatApiError(error);
            state.dryRunError = formatted;
        }).finally(() => {
            nextButton.disabled = false;
            renderStep();
        });
    }

    async function applyMapping() {
        nextButton.disabled = true;
        prevButton.disabled = true;
        state.log.push(__('Iniciando aplicação...', 'local2global'));
        renderStep();

        try {
            const response = await apiFetch({
                path: '/local2global/v1/map',
                method: 'POST',
                parse: false,
                data: {
                    product_id: state.productId,
                    mapping: serializeMapping(),
                    options: state.options,
                    mode: 'apply',
                },
            });

            let data = null;
            try {
                data = await response.json();
            } catch (parseError) {
                data = null;
            }

            if (!response.ok) {
                throw buildApiError(response.status, data);
            }

            const corr = data?.corr_id ? ` (id: ${data.corr_id})` : '';
            state.log.push(__('Mapeamento concluído.', 'local2global') + corr);
            if (data?.result) {
                state.log.push(JSON.stringify(data.result, null, 2));
            }
        } catch (error) {
            const formatted = formatApiError(error);
            state.log.push(__('Erro: ', 'local2global') + formatted.message);
            if (formatted.details) {
                state.log.push(formatted.details);
            }
        } finally {
            nextButton.disabled = false;
            prevButton.disabled = true;
            nextButton.textContent = __('Fechar', 'local2global');
            renderStep();
        }
    }

    function buildApiError(status, payload) {
        const error = new Error(payload?.message || `HTTP ${status}`);
        error.corrId = payload?.data?.corr_id || payload?.corr_id || null;
        error.details = payload?.data?.details || payload?.details || null;
        return error;
    }

    function formatApiError(error) {
        const corr = error?.corrId || error?.data?.corr_id || error?.data?.data?.corr_id || null;
        let message = error?.message || __('Erro inesperado.', 'local2global');
        if (corr) {
            message += ` (id: ${corr})`;
        }

        let details = null;
        if (typeof error?.details === 'string') {
            details = error.details;
        } else if (typeof error?.data?.details === 'string') {
            details = error.data.details;
        }

        return { message, details };
    }

    function serializeMapping() {
        return state.mapping.map((map) => ({
            local_attr: map.local_attr,
            local_label: map.local_label,
            target_tax: ensurePaPrefix(map.target_tax),
            create_attribute: map.create_attribute,
            terms: map.terms.map((term) => ({
                local_value: term.local_value,
                term_slug: term.term_slug,
                create: !!term.create,
            })),
        }));
    }

    function ensurePaPrefix(value) {
        if (!value) {
            return '';
        }
        if (value.startsWith('pa_')) {
            return 'pa_' + slugify(value.replace(/^pa_/, ''));
        }
        return 'pa_' + slugify(value);
    }

    function slugify(value) {
        return (value || '')
            .toString()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '')
            .replace(/-{2,}/g, '-');
    }

    function autoMapAttributeTerms(map) {
        if (!map.termOptions || !map.termOptions.length) {
            return;
        }
        map.terms.forEach((term) => {
            const normalizedLocal = normalizeString(term.local_value);
            let bestMatch = null;
            let bestScore = 0;
            map.termOptions.forEach((option) => {
                const normalizedOption = normalizeString(option.name);
                if (normalizedOption === normalizedLocal || option.slug === slugify(term.local_value)) {
                    bestMatch = option;
                    bestScore = 1;
                    return;
                }
                const distance = similarity(normalizedLocal, normalizedOption);
                if (distance > bestScore) {
                    bestScore = distance;
                    bestMatch = option;
                }
            });
            if (bestMatch && bestScore > 0.5) {
                term.term_slug = bestMatch.slug;
                term.term_name = bestMatch.name;
                term.create = false;
            } else {
                // Nenhum termo existente suficientemente similar: marcar para criação.
                term.create = true;
                term.term_slug = ''; // backend derivará
            }
        });
    }

    function normalizeString(value) {
        return slugify(value).replace(/-/g, '');
    }

    function similarity(a, b) {
        if (!a.length || !b.length) {
            return 0;
        }
        const matrix = Array.from({ length: a.length + 1 }, () => new Array(b.length + 1).fill(0));
        for (let i = 0; i <= a.length; i++) {
            matrix[i][0] = i;
        }
        for (let j = 0; j <= b.length; j++) {
            matrix[0][j] = j;
        }
        for (let i = 1; i <= a.length; i++) {
            for (let j = 1; j <= b.length; j++) {
                const cost = a[i - 1] === b[j - 1] ? 0 : 1;
                matrix[i][j] = Math.min(
                    matrix[i - 1][j] + 1,
                    matrix[i][j - 1] + 1,
                    matrix[i - 1][j - 1] + cost
                );
            }
        }
        const distance = matrix[a.length][b.length];
        return 1 - distance / Math.max(a.length, b.length);
    }

    function escapeHtml(value) {
        return (value || '').toString().replace(/[&<>"']/g, (char) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;',
        })[char]);
    }

    document.querySelectorAll('.local2global-open').forEach((button) => {
        button.addEventListener('click', openModal);
    });

    modal?.querySelectorAll('[data-local2global-close]').forEach((button) => {
        button.addEventListener('click', closeModal);
    });

    prevButton?.addEventListener('click', () => {
        if (state.stepIndex === 0) {
            return;
        }
        state.stepIndex -= 1;
        renderStep();
    });

    nextButton?.addEventListener('click', () => {
        if (state.stepIndex === steps.length - 1) {
            closeModal();
            return;
        }

        const current = steps[state.stepIndex];
        if (current.id === 'dry-run') {
            if (!state.dryRun && !state.dryRunError) {
                // Caso usuário clique antes do auto disparo concluir, forçar execução.
                if (!state._dryRunRequested) {
                    state._dryRunRequested = true;
                    performDryRun();
                }
                return;
            }
            if (state.dryRunError) {
                // Não avança enquanto houver erro; usuário deve retry.
                return;
            }
            // avançar para apply com resultado válido
            state.stepIndex += 1;
            renderStep();
            applyMapping();
            return;
        }

        if (current.id === 'apply') {
            closeModal();
            return;
        }

        state.stepIndex += 1;
        renderStep();
    });
})(window.wp, window.Local2GlobalSettings);
