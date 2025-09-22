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
        options: {
            update_variations: true,
            auto_create_terms: true,
            create_backup: true,
            save_template: true,
        },
        dryRun: null,
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
            id: 'options',
            title: __('Opções avançadas', 'local2global'),
            render: renderOptionsStep,
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
        } else if (state.stepIndex === steps.length - 2) {
            nextButton.textContent = settings.i18n.apply;
        } else if (state.stepIndex === steps.length - 3) {
            nextButton.textContent = settings.i18n.dryRun;
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
        intro.textContent = __('Selecione um atributo global correspondente para cada atributo local.', 'local2global');
        container.appendChild(intro);

        state.mapping.forEach((map, index) => {
            const wrapper = document.createElement('div');
            wrapper.className = 'local2global-attribute-card';

            const heading = document.createElement('h3');
            heading.textContent = map.local_label;
            wrapper.appendChild(heading);

            const selectLabel = document.createElement('label');
            selectLabel.textContent = __('Atributo global', 'local2global');
            const select = document.createElement('select');
            select.dataset.index = index;
            select.innerHTML = '<option value="">' + __('— Selecionar —', 'local2global') + '</option>';
            settings.attributes.forEach((attr) => {
                const option = document.createElement('option');
                option.value = attr.slug;
                option.textContent = attr.label + ' (' + attr.slug + ')';
                if (attr.slug === map.target_tax) {
                    option.selected = true;
                }
                select.appendChild(option);
            });

            const createOption = document.createElement('option');
            createOption.value = '__create_new__';
            createOption.textContent = __('Criar novo atributo global', 'local2global');
            select.appendChild(createOption);

            select.addEventListener('change', (event) => {
                const target = event.target;
                const entry = state.mapping[parseInt(target.dataset.index, 10)];
                if (target.value === '__create_new__') {
                    entry.create_attribute = true;
                    entry.target_tax = 'pa_' + slugify(entry.local_label);
                    select.value = '';
                } else {
                    entry.target_tax = target.value;
                    entry.create_attribute = false;
                }
                renderStep();
            });

            selectLabel.appendChild(select);
            wrapper.appendChild(selectLabel);

            const labelField = document.createElement('label');
            labelField.textContent = __('Rótulo do atributo global', 'local2global');
            const labelInput = document.createElement('input');
            labelInput.type = 'text';
            labelInput.value = map.target_label || map.local_label;
            labelInput.dataset.index = index;
            labelInput.addEventListener('input', (event) => {
                state.mapping[parseInt(event.target.dataset.index, 10)].target_label = event.target.value;
            });
            labelField.appendChild(labelInput);
            wrapper.appendChild(labelField);

            const slugField = document.createElement('label');
            slugField.textContent = __('Slug alvo (pa_slug)', 'local2global');
            const slugInput = document.createElement('input');
            slugInput.type = 'text';
            slugInput.value = map.target_tax;
            slugInput.dataset.index = index;
            slugInput.addEventListener('input', (event) => {
                state.mapping[parseInt(event.target.dataset.index, 10)].target_tax = ensurePaPrefix(event.target.value);
            });
            slugField.appendChild(slugInput);
            wrapper.appendChild(slugField);

            const createCheckbox = document.createElement('label');
            const createInput = document.createElement('input');
            createInput.type = 'checkbox';
            createInput.checked = !!map.create_attribute;
            createInput.dataset.index = index;
            createInput.addEventListener('change', (event) => {
                state.mapping[parseInt(event.target.dataset.index, 10)].create_attribute = event.target.checked;
            });
            createCheckbox.appendChild(createInput);
            createCheckbox.appendChild(document.createTextNode(' ' + __('Criar atributo se necessário', 'local2global')));
            wrapper.appendChild(createCheckbox);

            if (state.attributes[index].suggestion) {
                const suggestion = document.createElement('p');
                suggestion.className = 'description';
                suggestion.textContent = __('Template sugerido aplicado. Revise antes de continuar.', 'local2global');
                wrapper.appendChild(suggestion);
            }

            container.appendChild(wrapper);
        });
    }

    function renderTermMatrixStep(container) {
        const intro = document.createElement('div');
        intro.innerHTML = '<p>' + __('Associe cada valor local a um termo global existente ou indique a criação automática.', 'local2global') + '</p>';
        const autoMap = document.createElement('button');
        autoMap.type = 'button';
        autoMap.className = 'button';
        autoMap.textContent = settings.i18n.autoMap;
        autoMap.addEventListener('click', () => {
            Promise.all(state.mapping.map(loadTermOptions)).then(() => {
                state.mapping.forEach(autoMapAttributeTerms);
                renderStep();
            });
        });
        intro.appendChild(autoMap);
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
                select.innerHTML = '<option value="">' + __('— Selecionar termo existente —', 'local2global') + '</option>';

                ensureTermOptions(map).forEach((termOption) => {
                    const option = document.createElement('option');
                    option.value = termOption.slug;
                    option.textContent = termOption.name + ' (' + termOption.slug + ')';
                    if (termOption.slug === term.term_slug) {
                        option.selected = true;
                    }
                    select.appendChild(option);
                });

                select.addEventListener('change', (event) => {
                    const attrIdx = parseInt(event.target.dataset.attrIndex, 10);
                    const termIdx = parseInt(event.target.dataset.termIndex, 10);
                    const entry = state.mapping[attrIdx].terms[termIdx];
                    entry.term_slug = event.target.value;
                    entry.term_name = event.target.options[event.target.selectedIndex]?.textContent?.split(' (')[0] || entry.term_name;
                    entry.create = false;
                });

                const customWrapper = document.createElement('div');
                customWrapper.className = 'local2global-term-custom';

                const nameInput = document.createElement('input');
                nameInput.type = 'text';
                nameInput.placeholder = __('Nome do termo', 'local2global');
                nameInput.value = term.term_name || term.local_value;
                nameInput.dataset.attrIndex = mapIndex;
                nameInput.dataset.termIndex = termIndex;
                nameInput.addEventListener('input', onTermNameChange);

                const slugInput = document.createElement('input');
                slugInput.type = 'text';
                slugInput.placeholder = __('Slug', 'local2global');
                slugInput.value = term.term_slug || slugify(term.term_name || term.local_value);
                slugInput.dataset.attrIndex = mapIndex;
                slugInput.dataset.termIndex = termIndex;
                slugInput.addEventListener('input', onTermSlugChange);

                const createLabel = document.createElement('label');
                const createCheckbox = document.createElement('input');
                createCheckbox.type = 'checkbox';
                createCheckbox.checked = !!term.create;
                createCheckbox.dataset.attrIndex = mapIndex;
                createCheckbox.dataset.termIndex = termIndex;
                createCheckbox.addEventListener('change', (event) => {
                    const attrIdx = parseInt(event.target.dataset.attrIndex, 10);
                    const termIdx = parseInt(event.target.dataset.termIndex, 10);
                    state.mapping[attrIdx].terms[termIdx].create = event.target.checked;
                });
                createLabel.appendChild(createCheckbox);
                createLabel.appendChild(document.createTextNode(' ' + settings.i18n.createTerm));

                const refreshButton = document.createElement('button');
                refreshButton.type = 'button';
                refreshButton.className = 'button button-small';
                refreshButton.textContent = __('Atualizar termos', 'local2global');
                refreshButton.addEventListener('click', () => {
                    loadTermOptions(map).then(() => renderStep());
                });

                customWrapper.appendChild(select);
                customWrapper.appendChild(nameInput);
                customWrapper.appendChild(slugInput);
                customWrapper.appendChild(createLabel);
                customWrapper.appendChild(refreshButton);

                globalCell.appendChild(customWrapper);
                tr.appendChild(globalCell);
                tbody.appendChild(tr);
            });

            table.appendChild(tbody);
            block.appendChild(table);
            container.appendChild(block);
        });
    }

    function renderOptionsStep(container) {
        const description = document.createElement('p');
        description.textContent = __('Ajuste as opções antes de aplicar o mapeamento.', 'local2global');
        container.appendChild(description);

        const options = [
            { key: 'update_variations', label: settings.i18n.updateVariations },
            { key: 'auto_create_terms', label: settings.i18n.createTerm },
            { key: 'create_backup', label: settings.i18n.backup },
            { key: 'save_template', label: __('Aplicar como template para outros produtos', 'local2global') },
        ];

        options.forEach((option) => {
            const label = document.createElement('label');
            const input = document.createElement('input');
            input.type = 'checkbox';
            input.checked = !!state.options[option.key];
            input.dataset.optionKey = option.key;
            input.addEventListener('change', (event) => {
                state.options[event.target.dataset.optionKey] = event.target.checked;
            });
            label.appendChild(input);
            label.appendChild(document.createTextNode(' ' + option.label));
            container.appendChild(label);
        });
    }

    function renderDryRunStep(container) {
        if (!state.dryRun) {
            container.innerHTML = '<p>' + __('Calculando pré-visualização…', 'local2global') + '</p>';
            return;
        }

        if (state.dryRun.errors.length) {
            const errors = document.createElement('div');
            errors.className = 'notice notice-error';
            errors.innerHTML = '<p><strong>' + __('Problemas encontrados:', 'local2global') + '</strong></p><ul>' +
                state.dryRun.errors.map((err) => '<li>' + escapeHtml(err) + '</li>').join('') + '</ul>';
            container.appendChild(errors);
        }

        state.dryRun.attributes.forEach((attr) => {
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
        const suggestion = attr.suggestion || {};
        const mapping = {
            local_attr: attr.name,
            local_label: attr.label,
            target_tax: ensurePaPrefix(suggestion.target_tax || ''),
            target_label: suggestion.target_label || attr.label,
            create_attribute: !suggestion.target_tax,
            terms: attr.values.map((value) => ({
                local_value: value,
                term_slug: suggestion.terms ? suggestion.terms[value] : '',
                term_name: value,
                create: !!suggestion.terms && !suggestion.terms[value],
            })),
            termOptions: [],
        };

        mapping.terms.forEach((term) => {
            if (!term.term_slug) {
                term.term_slug = slugify(term.term_name || term.local_value);
            }
            if (!suggestion.terms) {
                term.create = state.options.auto_create_terms;
            }
        });

        return mapping;
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
            return map.termOptions;
        }).catch(() => {
            map.termOptions = [];
        });
    }

    function onTermNameChange(event) {
        const attrIdx = parseInt(event.target.dataset.attrIndex, 10);
        const termIdx = parseInt(event.target.dataset.termIndex, 10);
        const entry = state.mapping[attrIdx].terms[termIdx];
        entry.term_name = event.target.value;
        if (!entry.term_slug || entry.term_slug === slugify(entry.local_value)) {
            entry.term_slug = slugify(event.target.value);
        }
    }

    function onTermSlugChange(event) {
        const attrIdx = parseInt(event.target.dataset.attrIndex, 10);
        const termIdx = parseInt(event.target.dataset.termIndex, 10);
        state.mapping[attrIdx].terms[termIdx].term_slug = slugify(event.target.value);
    }

    function performDryRun() {
        nextButton.disabled = true;
        state.dryRun = null;
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
            state.dryRun = result;
        }).catch((error) => {
            window.alert(error.message || error);
        }).finally(() => {
            nextButton.disabled = false;
            renderStep();
        });
    }

    function applyMapping() {
        nextButton.disabled = true;
        prevButton.disabled = true;
        state.log.push(__('Iniciando aplicação...', 'local2global'));
        renderStep();

        apiFetch({
            path: '/local2global/v1/map',
            method: 'POST',
            data: {
                product_id: state.productId,
                mapping: serializeMapping(),
                options: state.options,
                mode: 'apply',
            },
        }).then((result) => {
            state.log.push(__('Mapeamento concluído.', 'local2global'));
            state.log.push(JSON.stringify(result));
        }).catch((error) => {
            state.log.push(__('Erro: ', 'local2global') + (error.message || error));
        }).finally(() => {
            nextButton.disabled = false;
            prevButton.disabled = true;
            nextButton.textContent = __('Fechar', 'local2global');
            renderStep();
        });
    }

    function serializeMapping() {
        return state.mapping.map((map) => ({
            local_attr: map.local_attr,
            local_label: map.local_label,
            target_tax: ensurePaPrefix(map.target_tax),
            target_label: map.target_label,
            create_attribute: map.create_attribute,
            save_template: state.options.save_template,
            terms: map.terms.map((term) => ({
                local_value: term.local_value,
                term_slug: term.term_slug,
                term_name: term.term_name,
                create: term.create,
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

        if (state.stepIndex === steps.length - 3) {
            state.stepIndex += 1;
            state.dryRun = null;
            renderStep();
            performDryRun();
            return;
        }

        if (state.stepIndex === steps.length - 2) {
            applyMapping();
            state.stepIndex += 1;
            renderStep();
            return;
        }

        state.stepIndex += 1;
        renderStep();
    });
})(window.wp, window.Local2GlobalSettings);
