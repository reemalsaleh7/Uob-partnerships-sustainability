(function () {
    'use strict';

    const params = new URLSearchParams(window.location.search);

    /*
     * The approved-Initiative Workspace alias uses:
     * add-initiative-approved.php?request_id=3
     *
     * PHP renders the conversion view server-side, but the browser URL does
     * not contain view=convert or id=3. Detect the rendered page from its DOM
     * and accept both id and request_id.
     */
    const renderedView = document.querySelector(
        '[data-final-form][data-final-mode="existing"]'
    )
        ? 'existing'
        : document.querySelector('[data-final-form]')
            ? 'convert'
            : document.querySelector('[data-request-form]')
            ? 'form'
            : document.querySelector('[data-detail-content]')
                ? 'detail'
                : document.querySelector('[data-notification-list]')
                    ? 'notifications'
                    : null;

    const view = (
        params.get('view')
        || renderedView
        || 'list'
    ).toLowerCase();

    const requestId =
        params.get('id')
        || params.get('request_id');

    const alertBox = document.querySelector('[data-module-alert]');

    function showError(error, fallback) {
        if (!alertBox) return;

        alertBox.textContent = error?.message || fallback;
        alertBox.classList.remove('d-none');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function clearError() {
        if (!alertBox) return;

        alertBox.textContent = '';
        alertBox.classList.add('d-none');
    }

    function text(value, fallback = '—') {
        const normalized = String(value ?? '').trim();
        return normalized === '' ? fallback : normalized;
    }

    function waiting(value) {
        const seconds = Number(value);

        if (!Number.isFinite(seconds)) {
            return text(value);
        }

        const days = Math.floor(seconds / 86400);
        const hours = Math.floor((seconds % 86400) / 3600);

        return days > 0 ? `${days}d ${hours}h` : `${hours}h`;
    }

    function displayMemberRole(value) {
        const role = String(value || 'COLLABORATOR').toUpperCase();

        if (role === 'CO_OWNER') {
            return 'Co-owner';
        }

        return 'Collaborator';
    }


    function booleanValue(value) {
        return value === true
            || value === 1
            || value === '1'
            || value === 't'
            || value === 'true';
    }

    const enhancedMultiByControl = new WeakMap();
    const enhancedMultiInstances = [];
    let enhancedMultiOptionSequence = 0;

    const enhancedMultiPlaceholders = {
        secondary_types: 'Select secondary types',
        target_groups: 'Select target audiences',
        related_agreement_ids: 'Select one or more Agreements',
        required_resources: 'Select required resources',
        sdg_goals: 'Select Sustainable Development Goals',
        secondary_initiative_types:
            'Select secondary Initiative types',
        provider_categories: 'Select provider categories',
        international_collaboration_nature:
            'Select collaboration types',
        initiative_descriptors:
            'Select Initiative descriptors',
        resources_mobilized_options:
            'Select mobilized resources',
        the_areas: 'Select THE areas',
        qs_categories: 'Select QS categories',
        environmental_impact_types:
            'Select environmental impact types',
        secondary_sdgs:
            'Select secondary Sustainable Development Goals',
        evidence_types: 'Select evidence methods'
    };

    const enhancedSdgDetails = {
        SDG_1: [
            'Goal 1 — No Poverty',
            'End poverty in all its forms everywhere'
        ],
        SDG_2: [
            'Goal 2 — Zero Hunger',
            'End hunger and improve nutrition'
        ],
        SDG_3: [
            'Goal 3 — Good Health and Well-Being',
            'Ensure healthy lives and promote well-being for all'
        ],
        SDG_4: [
            'Goal 4 — Quality Education',
            'Ensure inclusive and equitable quality education'
        ],
        SDG_5: [
            'Goal 5 — Gender Equality',
            'Achieve equality and empower women and girls'
        ],
        SDG_6: [
            'Goal 6 — Clean Water and Sanitation',
            'Ensure access to water and sanitation services'
        ],
        SDG_7: [
            'Goal 7 — Affordable and Clean Energy',
            'Ensure access to modern and sustainable energy'
        ],
        SDG_8: [
            'Goal 8 — Decent Work and Economic Growth',
            'Promote growth and decent work for all'
        ],
        SDG_9: [
            'Goal 9 — Industry, Innovation and Infrastructure',
            'Promote innovation and sustainable infrastructure'
        ],
        SDG_10: [
            'Goal 10 — Reduced Inequalities',
            'Reduce inequality within and among countries'
        ],
        SDG_11: [
            'Goal 11 — Sustainable Cities and Communities',
            'Make cities inclusive, safe and sustainable'
        ],
        SDG_12: [
            'Goal 12 — Responsible Consumption and Production',
            'Ensure sustainable consumption and production patterns'
        ],
        SDG_13: [
            'Goal 13 — Climate Action',
            'Take urgent action to combat climate change'
        ],
        SDG_14: [
            'Goal 14 — Life Below Water',
            'Conserve oceans and marine resources'
        ],
        SDG_15: [
            'Goal 15 — Life on Land',
            'Protect terrestrial ecosystems'
        ],
        SDG_16: [
            'Goal 16 — Peace, Justice and Strong Institutions',
            'Promote peaceful societies and effective institutions'
        ],
        SDG_17: [
            'Goal 17 — Partnerships for the Goals',
            'Strengthen implementation and partnerships'
        ]
    };

    function normalizedMultiText(value) {
        return String(value ?? '')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function enhancedMultiFieldLabel(source) {
        const container = source.closest(
            '.col-12, .col-md-3, .col-md-4, .col-md-6, '
            + '.col-md-8, .col-lg-4, .col-lg-6'
        );

        if (!container) {
            return '';
        }

        const label = Array.from(container.children)
            .find((child) => child.matches?.('label.form-label'));

        return normalizedMultiText(label?.textContent)
            .replace(/\s*\*$/, '');
    }

    function enhancedMultiOptionData(value, fallbackLabel) {
        const normalizedValue = String(value || '');

        if (enhancedSdgDetails[normalizedValue]) {
            const [label, description] =
                enhancedSdgDetails[normalizedValue];

            return { label, description };
        }

        return {
            label: normalizedMultiText(fallbackLabel)
                || normalizedValue,
            description: ''
        };
    }

    function enhancedMultiSelection(instance) {
        if (instance.type === 'select') {
            return Array.from(instance.source.selectedOptions)
                .filter((option) => option.value)
                .map((option) => {
                    const details = enhancedMultiOptionData(
                        option.value,
                        option.textContent
                    );

                    return {
                        value: option.value,
                        label: details.label
                    };
                });
        }

        return instance.controls
            .filter((control) => control.checked)
            .map((control) => ({
                value: control.value,
                label: instance.optionData.get(control)?.label
                    || control.value
            }));
    }

    function clearEnhancedMultiInvalid(instance) {
        instance.component.classList.remove('is-invalid');
        instance.trigger.removeAttribute('aria-invalid');
        instance.error.textContent = '';
        instance.error.classList.add('d-none');

        if (instance.type === 'select') {
            instance.source.setCustomValidity('');
            return;
        }

        instance.controls.forEach((control) => {
            control.setCustomValidity('');
        });
    }

    function markEnhancedMultiInvalid(control, message) {
        const instance = enhancedMultiByControl.get(control);

        if (!instance) {
            return false;
        }

        instance.component.classList.add('is-invalid');
        instance.trigger.setAttribute('aria-invalid', 'true');
        instance.error.textContent =
            message || 'Select at least one option.';
        instance.error.classList.remove('d-none');
        instance.trigger.focus();

        return true;
    }

    function clearEnhancedMultiSelections(instance) {
        if (instance.type === 'select') {
            Array.from(instance.source.options).forEach(
                (option) => {
                    option.selected = false;
                }
            );

            instance.menuControls.forEach((control) => {
                control.checked = false;
            });

            instance.source.dispatchEvent(
                new Event('change', {
                    bubbles: true
                })
            );
        } else {
            instance.controls.forEach((control) => {
                control.checked = false;
                control.dispatchEvent(
                    new Event('change', {
                        bubbles: true
                    })
                );
            });
        }

        clearEnhancedMultiInvalid(instance);
        setEnhancedMultiActiveOption(
            instance,
            visibleEnhancedMultiOptions(instance)[0]
                || null,
            false
        );
        renderEnhancedMulti(instance);
    }

    function renderEnhancedMulti(instance) {
        const selected = enhancedMultiSelection(instance);
        instance.values.replaceChildren();

        if (!selected.length) {
            const placeholder = document.createElement('span');
            placeholder.className =
                'initiative-multi-placeholder';
            placeholder.textContent = instance.placeholder;
            instance.values.append(placeholder);
        } else {
            selected.forEach((item) => {
                const chip = document.createElement('span');
                chip.className = 'initiative-multi-chip';

                const chipText = document.createElement('span');
                chipText.className =
                    'initiative-multi-chip-label';
                chipText.textContent = item.label;

                const remove = document.createElement('span');
                remove.className =
                    'initiative-multi-chip-remove';
                remove.setAttribute('role', 'button');
                remove.setAttribute('tabindex', '0');
                remove.setAttribute(
                    'aria-label',
                    `Remove ${item.label}`
                );
                remove.textContent = '×';

                const removeValue = () => {
                    if (instance.type === 'select') {
                        const option = Array.from(
                            instance.source.options
                        ).find(
                            (candidate) =>
                                candidate.value === item.value
                        );

                        if (option) {
                            option.selected = false;
                        }

                        const menuControl =
                            instance.menuControls.get(item.value);

                        if (menuControl) {
                            menuControl.checked = false;
                        }

                        instance.source.dispatchEvent(
                            new Event('change', {
                                bubbles: true
                            })
                        );
                    } else {
                        const control = instance.controls.find(
                            (candidate) =>
                                candidate.value === item.value
                        );

                        if (control) {
                            control.checked = false;
                            control.dispatchEvent(
                                new Event('change', {
                                    bubbles: true
                                })
                            );
                        }
                    }

                    clearEnhancedMultiInvalid(instance);
                    window.queueMicrotask(
                        () => renderEnhancedMulti(instance)
                    );
                };

                remove.addEventListener('click', (event) => {
                    event.preventDefault();
                    event.stopPropagation();
                    removeValue();
                });

                remove.addEventListener(
                    'keydown',
                    (event) => {
                        if (
                            event.key === 'Enter'
                            || event.key === ' '
                        ) {
                            event.preventDefault();
                            event.stopPropagation();
                            removeValue();
                        }
                    }
                );

                chip.append(chipText, remove);
                instance.values.append(chip);
            });
        }

        instance.component.classList.toggle(
            'has-selection',
            selected.length > 0
        );

        if (instance.clearButton) {
            instance.clearButton.disabled =
                selected.length === 0;
            instance.clearButton.classList.toggle(
                'd-none',
                selected.length === 0
            );
        }

        if (instance.type === 'select') {
            instance.menuControls.forEach(
                (control, value) => {
                    const option = Array.from(
                        instance.source.options
                    ).find(
                        (candidate) =>
                            candidate.value === value
                    );

                    control.checked = Boolean(option?.selected);
                    const optionLabel = control.closest(
                        '.initiative-multi-option'
                    );

                    optionLabel?.classList.toggle(
                        'is-selected',
                        control.checked
                    );
                    optionLabel?.setAttribute(
                        'aria-selected',
                        String(control.checked)
                    );
                }
            );
        } else {
            instance.controls.forEach((control) => {
                const optionLabel = control.closest(
                    '.initiative-multi-option'
                );

                optionLabel?.classList.toggle(
                    'is-selected',
                    control.checked
                );
                optionLabel?.setAttribute(
                    'aria-selected',
                    String(control.checked)
                );
            });
        }
    }

    function closeEnhancedMulti(instance, restoreFocus = false) {
        instance.component.classList.remove('is-open');
        instance.trigger.setAttribute('aria-expanded', 'false');
        instance.search.value = '';
        instance.optionLabels.forEach((option) => {
            option.hidden = false;
            option.classList.remove(
                'is-filtered-out',
                'is-keyboard-active'
            );
        });

        instance.activeOption = null;
        instance.search.removeAttribute(
            'aria-activedescendant'
        );

        if (instance.noResults) {
            instance.noResults.classList.add('d-none');
        }

        if (restoreFocus) {
            instance.trigger.focus();
        }
    }

    function closeOtherEnhancedMultis(activeInstance) {
        enhancedMultiInstances.forEach((instance) => {
            if (instance !== activeInstance) {
                closeEnhancedMulti(instance);
            }
        });
    }

    function openEnhancedMulti(instance) {
        closeOtherEnhancedMultis(instance);
        instance.component.classList.add('is-open');
        instance.trigger.setAttribute('aria-expanded', 'true');

        window.setTimeout(() => {
            instance.search.focus();
        }, 30);
    }

    function createEnhancedMultiShell(
        source,
        placeholder
    ) {
        const component = document.createElement('div');
        component.className = 'initiative-multi';

        const trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'initiative-multi-trigger';
        trigger.setAttribute('aria-expanded', 'false');

        const values = document.createElement('span');
        values.className = 'initiative-multi-values';

        const arrow = document.createElement('span');
        arrow.className = 'initiative-multi-arrow';
        arrow.setAttribute('aria-hidden', 'true');
        arrow.textContent = '▼';

        trigger.append(values, arrow);

        const menu = document.createElement('div');
        menu.className = 'initiative-multi-menu';

        const searchWrap = document.createElement('div');
        searchWrap.className = 'initiative-multi-search';

        const searchRow = document.createElement('div');
        searchRow.className = 'initiative-multi-search-row';

        const search = document.createElement('input');
        search.type = 'search';
        search.className = 'form-control';
        search.placeholder = 'Search…';
        search.setAttribute('autocomplete', 'off');

        const clearButton = document.createElement('button');
        clearButton.type = 'button';
        clearButton.className =
            'initiative-multi-clear d-none';
        clearButton.textContent = 'Clear all';
        clearButton.setAttribute(
            'aria-label',
            'Clear all selected options'
        );

        searchRow.append(search, clearButton);
        searchWrap.append(searchRow);
        menu.append(searchWrap);

        const error = document.createElement('div');
        error.className =
            'initiative-multi-error d-none';
        error.setAttribute('role', 'alert');

        component.append(trigger, menu, error);

        source.parentNode.insertBefore(component, source);

        return {
            component,
            trigger,
            values,
            arrow,
            menu,
            search,
            clearButton,
            error,
            placeholder
        };
    }

    function prepareCheckboxMultiOption(
        label,
        input,
        value
    ) {
        const originalText = normalizedMultiText(
            label.textContent
        );
        const details = enhancedMultiOptionData(
            value,
            originalText
        );

        label.classList.add('initiative-multi-option');
        label.dataset.search = normalizedMultiText(
            `${details.label} ${details.description}`
        ).toLowerCase();

        Array.from(label.childNodes).forEach((node) => {
            if (node !== input) {
                node.remove();
            }
        });

        const copy = document.createElement('span');
        copy.className = 'initiative-multi-option-copy';

        const strong = document.createElement('strong');
        strong.textContent = details.label;
        copy.append(strong);

        if (details.description) {
            const small = document.createElement('small');
            small.textContent = details.description;
            copy.append(small);
        }

        label.append(copy);

        return details;
    }

    function enhanceCheckboxMulti(
        form,
        name,
        placeholder
    ) {
        const controls = Array.from(
            form.querySelectorAll(
                `input[type="checkbox"][name="${name}"]`
            )
        );

        if (!controls.length) {
            return null;
        }

        const source = controls[0].closest(
            '.initiative-choice-grid, '
            + '.initiative-sdg-grid, '
            + '.initiative-final-check-grid'
        );

        if (!source || source.dataset.multiEnhanced === 'true') {
            return null;
        }

        source.dataset.multiEnhanced = 'true';
        source.classList.add('initiative-multi-options');

        const shell = createEnhancedMultiShell(
            source,
            placeholder
        );

        shell.menu.append(source);

        const optionData = new Map();
        const optionLabels = [];

        controls.forEach((control) => {
            const label = control.closest('label');

            if (!label) {
                return;
            }

            const details = prepareCheckboxMultiOption(
                label,
                control,
                control.value
            );

            optionData.set(control, details);
            optionLabels.push(label);
        });

        const instance = {
            ...shell,
            type: 'checkbox',
            name,
            source,
            controls,
            optionData,
            optionLabels,
            menuControls: new Map()
        };

        controls.forEach((control) => {
            enhancedMultiByControl.set(control, instance);

            control.addEventListener('change', () => {
                clearEnhancedMultiInvalid(instance);

                window.queueMicrotask(() => {
                    renderEnhancedMulti(instance);
                });
            });
        });

        return instance;
    }

    function enhanceSelectMulti(select) {
        if (
            !(select instanceof HTMLSelectElement)
            || !select.multiple
            || select.dataset.multiEnhanced === 'true'
        ) {
            return null;
        }

        select.dataset.multiEnhanced = 'true';
        select.classList.add('initiative-multi-native');
        select.setAttribute('aria-hidden', 'true');
        select.tabIndex = -1;

        const name = select.name;
        const fieldLabel = enhancedMultiFieldLabel(select);
        const placeholder =
            enhancedMultiPlaceholders[name]
            || (fieldLabel
                ? `Select ${fieldLabel}`
                : 'Select one or more options');

        const shell = createEnhancedMultiShell(
            select,
            placeholder
        );

        const options = document.createElement('div');
        options.className = 'initiative-multi-options';
        shell.menu.append(options);

        const menuControls = new Map();
        const optionLabels = [];

        Array.from(select.options)
            .filter((option) => option.value)
            .forEach((option) => {
                const details = enhancedMultiOptionData(
                    option.value,
                    option.textContent
                );

                const label = document.createElement('label');
                label.className = 'initiative-multi-option';
                label.dataset.search = normalizedMultiText(
                    `${details.label} ${details.description}`
                ).toLowerCase();

                const input = document.createElement('input');
                input.type = 'checkbox';
                input.value = option.value;
                input.checked = option.selected;

                const copy = document.createElement('span');
                copy.className = 'initiative-multi-option-copy';

                const strong = document.createElement('strong');
                strong.textContent = details.label;
                copy.append(strong);

                if (details.description) {
                    const small = document.createElement('small');
                    small.textContent = details.description;
                    copy.append(small);
                }

                label.append(input, copy);
                options.append(label);

                menuControls.set(option.value, input);
                optionLabels.push(label);

                input.addEventListener('change', () => {
                    option.selected = input.checked;
                    clearEnhancedMultiInvalid(instance);
                    select.dispatchEvent(
                        new Event('change', {
                            bubbles: true
                        })
                    );
                    renderEnhancedMulti(instance);
                });
            });

        const instance = {
            ...shell,
            type: 'select',
            name,
            source: select,
            controls: [select],
            optionData: new Map(),
            optionLabels,
            menuControls
        };

        enhancedMultiByControl.set(select, instance);

        select.addEventListener('change', () => {
            clearEnhancedMultiInvalid(instance);
            renderEnhancedMulti(instance);
        });

        return instance;
    }

    function visibleEnhancedMultiOptions(instance) {
        return instance.optionLabels.filter(
            (option) => (
                !option.hidden
                && !option.classList.contains(
                    'is-filtered-out'
                )
            )
        );
    }

    function setEnhancedMultiActiveOption(
        instance,
        option,
        scroll = true
    ) {
        instance.optionLabels.forEach((candidate) => {
            candidate.classList.remove(
                'is-keyboard-active'
            );
        });

        instance.activeOption = option || null;

        if (!option) {
            instance.search.removeAttribute(
                'aria-activedescendant'
            );
            return;
        }

        option.classList.add('is-keyboard-active');
        instance.search.setAttribute(
            'aria-activedescendant',
            option.id
        );

        if (scroll) {
            option.scrollIntoView({
                block: 'nearest'
            });
        }
    }

    function moveEnhancedMultiActive(
        instance,
        direction
    ) {
        const visible = visibleEnhancedMultiOptions(
            instance
        );

        if (!visible.length) {
            setEnhancedMultiActiveOption(
                instance,
                null,
                false
            );
            return;
        }

        const currentIndex = visible.indexOf(
            instance.activeOption
        );
        let nextIndex;

        if (currentIndex === -1) {
            nextIndex = direction > 0
                ? 0
                : visible.length - 1;
        } else {
            nextIndex = (
                currentIndex
                + direction
                + visible.length
            ) % visible.length;
        }

        setEnhancedMultiActiveOption(
            instance,
            visible[nextIndex]
        );
    }

    function toggleEnhancedMultiActive(instance) {
        const visible = visibleEnhancedMultiOptions(
            instance
        );

        const option = instance.activeOption
            && visible.includes(instance.activeOption)
            ? instance.activeOption
            : visible[0];

        if (!option) {
            return;
        }

        setEnhancedMultiActiveOption(
            instance,
            option
        );

        const control = option.querySelector(
            'input[type="checkbox"]'
        );

        if (!control || control.disabled) {
            return;
        }

        control.checked = !control.checked;
        control.dispatchEvent(
            new Event('change', {
                bubbles: true
            })
        );
    }

    function bindEnhancedMultiInteraction(instance) {
        const optionsContainer =
            instance.optionLabels[0]?.parentElement
            || instance.menu;

        optionsContainer.id = optionsContainer.id
            || `initiative-multi-list-${
                ++enhancedMultiOptionSequence
            }`;
        optionsContainer.setAttribute(
            'role',
            'listbox'
        );
        optionsContainer.setAttribute(
            'aria-multiselectable',
            'true'
        );

        instance.search.setAttribute(
            'role',
            'combobox'
        );
        instance.search.setAttribute(
            'aria-controls',
            optionsContainer.id
        );
        instance.search.setAttribute(
            'aria-autocomplete',
            'list'
        );

        instance.optionLabels.forEach((option) => {
            option.id = option.id
                || `initiative-multi-option-${
                    ++enhancedMultiOptionSequence
                }`;
            option.setAttribute('role', 'option');
            option.setAttribute(
                'aria-selected',
                String(
                    Boolean(
                        option.querySelector(
                            'input[type="checkbox"]'
                        )?.checked
                    )
                )
            );

            option.addEventListener(
                'mouseenter',
                () => {
                    setEnhancedMultiActiveOption(
                        instance,
                        option,
                        false
                    );
                }
            );
        });

        const noResults = document.createElement('div');
        noResults.className =
            'initiative-multi-no-results d-none';
        noResults.textContent =
            'No matching options found.';
        optionsContainer.append(noResults);
        instance.noResults = noResults;

        instance.trigger.addEventListener('click', () => {
            if (instance.component.classList.contains('is-open')) {
                closeEnhancedMulti(instance);
            } else {
                openEnhancedMulti(instance);
            }
        });

        instance.clearButton.addEventListener(
            'click',
            (event) => {
                event.preventDefault();
                event.stopPropagation();
                clearEnhancedMultiSelections(instance);
                instance.search.focus();
            }
        );

        instance.search.addEventListener('input', () => {
            const query = normalizedMultiText(
                instance.search.value
            ).toLowerCase();

            const terms = query
                .split(/\s+/)
                .filter(Boolean);

            let visibleCount = 0;

            instance.optionLabels.forEach((option) => {
                const haystack = normalizedMultiText(
                    option.dataset.search
                    || option.textContent
                ).toLowerCase();

                const matches = terms.every(
                    (term) => haystack.includes(term)
                );

                option.hidden = !matches;
                option.classList.toggle(
                    'is-filtered-out',
                    !matches
                );

                if (matches) {
                    visibleCount += 1;
                }
            });

            instance.noResults?.classList.toggle(
                'd-none',
                visibleCount !== 0
            );

            const visible = visibleEnhancedMultiOptions(
                instance
            );

            setEnhancedMultiActiveOption(
                instance,
                visible[0] || null,
                false
            );
        });

        instance.search.addEventListener(
            'keydown',
            (event) => {
                if (event.key === 'ArrowDown') {
                    event.preventDefault();
                    moveEnhancedMultiActive(
                        instance,
                        1
                    );
                    return;
                }

                if (event.key === 'ArrowUp') {
                    event.preventDefault();
                    moveEnhancedMultiActive(
                        instance,
                        -1
                    );
                    return;
                }

                if (event.key === 'Home') {
                    const visible =
                        visibleEnhancedMultiOptions(
                            instance
                        );

                    if (visible.length) {
                        event.preventDefault();
                        setEnhancedMultiActiveOption(
                            instance,
                            visible[0]
                        );
                    }
                    return;
                }

                if (event.key === 'End') {
                    const visible =
                        visibleEnhancedMultiOptions(
                            instance
                        );

                    if (visible.length) {
                        event.preventDefault();
                        setEnhancedMultiActiveOption(
                            instance,
                            visible[
                                visible.length - 1
                            ]
                        );
                    }
                    return;
                }

                if (event.key === 'Enter') {
                    event.preventDefault();
                    toggleEnhancedMultiActive(
                        instance
                    );
                    return;
                }

                if (event.key === 'Escape') {
                    event.preventDefault();
                    closeEnhancedMulti(instance, true);
                }
            }
        );
    }

    function initializeEnhancedMultiControls(
        form,
        checkboxNames = []
    ) {
        if (!form) {
            return;
        }

        checkboxNames.forEach((name) => {
            const instance = enhanceCheckboxMulti(
                form,
                name,
                enhancedMultiPlaceholders[name]
                    || 'Select one or more options'
            );

            if (instance) {
                enhancedMultiInstances.push(instance);
                bindEnhancedMultiInteraction(instance);
                renderEnhancedMulti(instance);
            }
        });

        form.querySelectorAll('select[multiple]')
            .forEach((select) => {
                const instance = enhanceSelectMulti(select);

                if (instance) {
                    enhancedMultiInstances.push(instance);
                    bindEnhancedMultiInteraction(instance);
                    renderEnhancedMulti(instance);
                }
            });
    }

    function refreshEnhancedMultiByName(form, name) {
        const controls = Array.from(
            form.querySelectorAll(`[name="${name}"]`)
        );

        const instance = controls
            .map((control) => enhancedMultiByControl.get(control))
            .find(Boolean);

        if (instance) {
            renderEnhancedMulti(instance);
        }
    }

    document.addEventListener('click', (event) => {
        if (!event.target.closest('.initiative-multi')) {
            enhancedMultiInstances.forEach((instance) => {
                closeEnhancedMulti(instance);
            });
        }
    });

    async function refreshNotificationBadge() {
        const badge = document.querySelector(
            '[data-notification-count]'
        );

        if (!badge) {
            return;
        }

        try {
            const payload = await AgreementApi.request(
                '/initiative-requests/notifications/unread-count'
            );
            const count = Number(payload?.unread_count || 0);

            badge.textContent = count > 99 ? '99+' : String(count);
            badge.classList.toggle('d-none', count < 1);
        } catch (error) {
            badge.classList.add('d-none');
        }
    }

    async function initializeList() {
        const state = { items: [] };
        const loading = document.querySelector('[data-loading]');
        const body = document.querySelector('[data-list-body]');
        const empty = document.querySelector('[data-empty]');
        const wrap = document.querySelector('[data-table-wrap]');
        const search = document.querySelector('[data-search]');
        const filter = document.querySelector('[data-status-filter]');
        const approvedMenu = document.querySelector(
            '[data-add-approved-initiative-menu]'
        );
        const approvedOptions = document.querySelector(
            '[data-approved-initiative-options]'
        );

        function eligibleConversions() {
            return state.items.filter((item) => (
                booleanValue(item.can_convert)
                && ['APPROVED', 'CONVERTING'].includes(
                    String(item.status || '').toUpperCase()
                )
            ));
        }

        function renderApprovedMenu() {
            if (!approvedMenu || !approvedOptions) {
                return;
            }

            const eligible = eligibleConversions();
            approvedOptions.replaceChildren();
            approvedMenu.classList.toggle(
                'd-none',
                eligible.length === 0
            );

            eligible.forEach((item) => {
                const listItem = document.createElement('li');
                const link = document.createElement('a');
                link.className = 'dropdown-item';
                link.href =
                    `add-initiative-approved.php?request_id=${
                        encodeURIComponent(item.request_id)
                    }`;

                const title = document.createElement('strong');
                title.className = 'd-block';
                title.textContent = text(item.title);

                const detail = document.createElement('small');
                detail.className = 'text-secondary';
                detail.textContent =
                    `${text(item.request_code, 'Approved request')} · ${
                        item.status === 'CONVERTING'
                            ? 'Continue draft'
                            : 'Create Final Initiative'
                    }`;

                link.append(title, detail);
                listItem.append(link);
                approvedOptions.append(listItem);
            });
        }

        function render() {
            const query = search.value.trim().toLowerCase();
            const status = filter.value;
            const items = state.items.filter((item) => {
                const haystack =
                    `${item.request_code || ''} ${item.title || ''}`
                        .toLowerCase();

                return (
                    (!query || haystack.includes(query))
                    && (!status || item.status === status)
                );
            });

            body.replaceChildren();
            empty.classList.toggle('d-none', items.length !== 0);
            wrap.classList.toggle('d-none', items.length === 0);

            items.forEach((item) => {
                const row = document.createElement('tr');

                row.innerHTML = `
                    <td>
                        <strong></strong>
                        <small class="d-block text-secondary"></small>
                    </td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td class="text-end"></td>
                `;

                row.children[0].querySelector('strong').textContent =
                    text(item.title);
                row.children[0].querySelector('small').textContent =
                    text(item.request_code, 'Draft request');
                row.children[1].textContent = text(item.requester_name);
                row.children[2].textContent =
                    item.status === 'REVISION_REQUIRED'
                        ? 'Returned for revision'
                        : text(item.current_stage_label, 'Not submitted');
                row.children[3].append(
                    AgreementApi.createStatusBadge(item.status)
                );
                row.children[4].textContent = item.status === 'DRAFT'
                    ? '—'
                    : waiting(item.waiting_seconds);

                const requestHref =
                    `initiative-workflow.php?view=detail&id=${
                        encodeURIComponent(item.request_id)
                    }`;

                const openRequest = () => {
                    if (row.dataset.opening === 'true') {
                        return;
                    }

                    row.dataset.opening = 'true';
                    window.location.assign(requestHref);
                };

                row.classList.add('initiative-request-row');
                row.tabIndex = 0;
                row.setAttribute('role', 'link');
                row.setAttribute(
                    'aria-label',
                    `Open ${
                        text(
                            item.request_code,
                            item.title || 'Initiative request'
                        )
                    }`
                );

                row.addEventListener('click', (event) => {
                    const interactiveTarget =
                        event.target instanceof Element
                        && event.target.closest(
                            'a, button, input, select, textarea, label'
                        );

                    if (interactiveTarget) {
                        return;
                    }

                    openRequest();
                });

                row.addEventListener('keydown', (event) => {
                    if (
                        event.key !== 'Enter'
                        && event.key !== ' '
                    ) {
                        return;
                    }

                    event.preventDefault();
                    openRequest();
                });

                const actionCell = row.children[5];
                actionCell.classList.add(
                    'initiative-request-actions'
                );

                if (
                    booleanValue(item.can_convert)
                    && ['APPROVED', 'CONVERTING'].includes(
                        String(item.status || '').toUpperCase()
                    )
                ) {
                    const conversionLink =
                        document.createElement('a');
                    conversionLink.className =
                        'btn btn-primary btn-sm';
                    conversionLink.href =
                        `add-initiative-approved.php?request_id=${
                            encodeURIComponent(item.request_id)
                        }`;
                    conversionLink.textContent =
                        item.status === 'CONVERTING'
                            ? 'Continue Final Initiative'
                            : 'Create Final Initiative';
                    conversionLink.setAttribute(
                        'aria-label',
                        `${conversionLink.textContent}: ${
                            text(item.title)
                        }`
                    );
                    actionCell.append(conversionLink);
                }

                const link = document.createElement('a');
                link.className = 'btn btn-outline-primary btn-sm';
                link.href = requestHref;
                link.textContent = 'Open';

                actionCell.append(link);
                body.append(row);
            });
        }

        try {
            await AgreementApi.requireSession();
            await refreshNotificationBadge();

            const access = await AgreementApi.request(
                '/initiative-access'
            );

            document
                .querySelector('[data-create-request]')
                .classList.toggle(
                    'd-none',
                    !access.can_create_initiative
                );

            document
                .querySelector('[data-add-existing-initiative]')
                ?.classList.toggle(
                    'd-none',
                    !booleanValue(access.can_create_initiative)
                );

            const payload = await AgreementApi.request(
                '/initiative-requests'
            );

            state.items = Array.isArray(payload)
                ? payload
                : (payload.items || []);

            renderApprovedMenu();
            render();
            search.addEventListener('input', render);
            filter.addEventListener('change', render);
        } catch (error) {
            showError(
                error,
                'Initiative requests could not be loaded.'
            );
        } finally {
            loading.classList.add('d-none');
        }
    }

    function routeFor(user) {
        const roles = new Set(user.roles || []);
        const positions = (user.positions || [])
            .map((item) => item.position);

        if (
            roles.has('Student')
            || positions.includes('Faculty Member')
        ) {
            return [
                'Requester',
                'Department Head',
                'Dean',
                'Vice President / Office',
                'President / Office'
            ];
        }

        if (positions.includes('Department Head')) {
            return [
                'Requester',
                'Dean',
                'Vice President / Office',
                'President / Office'
            ];
        }

        if (positions.includes('Dean')) {
            return [
                'Requester',
                'Vice President / Office',
                'President / Office'
            ];
        }

        if (positions.includes('Vice President Office')) {
            return [
                'Requester',
                'Vice President',
                'President / Office'
            ];
        }

        return [
            'Requester',
            'Department/College routing',
            'Vice President / Office',
            'President / Office'
        ];
    }

    function initializeFormWorkspaceLayout() {
        const body = document.body;
        const sidebar = document.getElementById(
            'workspaceSidebar'
        );
        const toggle = document.querySelector(
            '[data-sidebar-toggle]'
        );
        const requestForm = document.querySelector(
            '[data-request-form]'
        );

        body.classList.add('initiative-form-focus');

        if (!sidebar || !toggle) {
            return;
        }

        sidebar.classList.remove('is-open');

        const syncLayout = () => {
            const isOpen = sidebar.classList.contains('is-open');

            body.classList.toggle(
                'initiative-form-sidebar-open',
                isOpen
            );
            toggle.setAttribute(
                'aria-expanded',
                String(isOpen)
            );
            toggle.setAttribute(
                'aria-label',
                isOpen
                    ? 'Hide workspace navigation'
                    : 'Open workspace navigation'
            );
        };

        syncLayout();

        const observer = new MutationObserver(syncLayout);
        observer.observe(sidebar, {
            attributes: true,
            attributeFilter: ['class']
        });

        const isStepNavigationTarget = (target) => (
            target instanceof Element
            && Boolean(
                target.closest('[data-form-step-button]')
            )
        );

        const closeSidebarForFormInteraction = (event) => {
            /*
             * Do not close the sidebar during pointerdown/focusin on a
             * Form Step. Closing it before the click finishes moves the
             * button, which can cancel the click and leave the user on
             * the previous section.
             *
             * The Form Step click handler closes the sidebar after it
             * has captured the requested section.
             */
            if (
                event
                && isStepNavigationTarget(event.target)
            ) {
                return;
            }

            if (sidebar.classList.contains('is-open')) {
                sidebar.classList.remove('is-open');
            }
        };

        if (requestForm) {
            requestForm.addEventListener(
                'pointerdown',
                closeSidebarForFormInteraction,
                { passive: true }
            );

            requestForm.addEventListener(
                'focusin',
                closeSidebarForFormInteraction
            );
        }

        document.addEventListener('keydown', (event) => {
            if (
                event.key === 'Escape'
                && sidebar.classList.contains('is-open')
            ) {
                sidebar.classList.remove('is-open');
            }
        });
    }

    async function initializeForm() {
        initializeFormWorkspaceLayout();

        const form = document.querySelector('[data-request-form]');
        const people = [];
        const collaboratorList = document.querySelector(
            '[data-collaborator-list]'
        );
        const stepButtons = Array.from(
            document.querySelectorAll('[data-form-step-button]')
        );
        const stepSections = Array.from(
            document.querySelectorAll('[data-form-step]')
        );
        const previousButton = document.querySelector(
            '[data-previous-step]'
        );
        const nextButton = document.querySelector(
            '[data-next-step]'
        );
        const saveButton = document.querySelector(
            '[data-save-draft]'
        );
        const submitButton = document.querySelector(
            '[data-submit-request]'
        );
        const progressTrack = document.querySelector(
            '[data-form-progress-track]'
        );
        const progressFill = document.querySelector(
            '[data-form-progress-fill]'
        );
        const progressText = document.querySelector(
            '[data-form-progress-text]'
        );
        const attachmentInput = document.querySelector(
            '[data-attachment-input]'
        );
        const attachmentQueue = document.querySelector(
            '[data-attachment-queue]'
        );
        const existingAttachmentList = document.querySelector(
            '[data-existing-attachments]'
        );
        const isEditing = Boolean(requestId);

        const preselectedAgreementId = Number(
            params.get('agreement_id')
        );
        const maximumAttachmentCount = 5;
        const maximumAttachmentSize = 10 * 1024 * 1024;
        const allowedAttachmentExtensions = new Set([
            'pdf',
            'doc',
            'docx',
            'xls',
            'xlsx',
            'ppt',
            'pptx',
            'jpg',
            'jpeg',
            'png'
        ]);
        let currentStep = 0;
        let queuedFiles = [];
        let existingAttachments = [];
        let activeRequestId = isEditing
            ? Number(requestId)
            : null;
        let draft = null;

        function formField(name) {
            return form.elements.namedItem(name);
        }

        function radioValue(name) {
            return form.querySelector(
                `input[name="${name}"]:checked`
            )?.value || '';
        }

        function checkedValues(name) {
            return Array.from(
                form.querySelectorAll(
                    `input[name="${name}"]:checked`
                )
            ).map((input) => input.value);
        }

        function setRadioValue(name, value) {
            const normalized = value === true
                ? 'true'
                : value === false
                    ? 'false'
                    : String(value ?? '');

            form.querySelectorAll(
                `input[name="${name}"]`
            ).forEach((input) => {
                input.checked = input.value === normalized;
            });
        }

        function setCheckedValues(name, values) {
            const selected = new Set(
                Array.isArray(values)
                    ? values.map((value) => String(value))
                    : []
            );

            form.querySelectorAll(
                `input[name="${name}"]`
            ).forEach((input) => {
                input.checked = selected.has(input.value);
            });

            refreshEnhancedMultiByName(form, name);
        }


        function selectedOptionValues(name) {
            const select = formField(name);

            if (!(select instanceof HTMLSelectElement)) {
                return [];
            }

            return Array.from(select.selectedOptions)
                .map((option) => String(option.value))
                .filter(Boolean);
        }

        function setSelectedOptionValues(name, values) {
            const select = formField(name);

            if (!(select instanceof HTMLSelectElement)) {
                return;
            }

            const selected = new Set(
                Array.isArray(values)
                    ? values.map((value) => String(value))
                    : []
            );

            Array.from(select.options).forEach((option) => {
                option.selected = selected.has(String(option.value));
            });

            refreshEnhancedMultiByName(form, name);
        }
        function nullableBoolean(name) {
            const value = radioValue(name);

            if (value === '') {
                return null;
            }

            return value === 'true';
        }

        function formatFileSize(bytes) {
            const size = Number(bytes);

            if (!Number.isFinite(size) || size < 1) {
                return '—';
            }

            if (size >= 1024 * 1024) {
                return `${(size / (1024 * 1024)).toFixed(1)} MB`;
            }

            return `${Math.max(1, Math.round(size / 1024))} KB`;
        }

        function setProfile(profile) {
            const values = {
                '[data-requester-name]': profile.full_name,
                '[data-requester-email]': profile.email,
                '[data-requester-position]': profile.position,
                '[data-requester-entity]': profile.entity,
                '[data-requester-department]': profile.department,
                '[data-requester-role-key]': String(
                    profile.role_key || 'University account'
                ).replaceAll('_', ' ')
            };

            Object.entries(values).forEach(([selector, value]) => {
                const element = document.querySelector(selector);

                if (element) {
                    element.textContent = text(value);
                }
            });
        }

        function addCollaborator(member = null) {
            const template = document.querySelector(
                '[data-collaborator-template]'
            );
            const row = template.content.firstElementChild.cloneNode(
                true
            );
            const select = row.querySelector(
                '[data-collaborator-user]'
            );

            people.forEach((person) => {
                const option = document.createElement('option');
                option.value = person.user_id;
                option.textContent =
                    `${person.full_name || person.email} · ${
                        person.position
                        || person.role
                        || 'University user'
                    }`;
                select.append(option);
            });

            if (member) {
                select.value = String(member.user_id);
                row.querySelector(
                    '[data-collaborator-role]'
                ).value = member.member_role || 'COLLABORATOR';
            }

            row.querySelector(
                '[data-remove-collaborator]'
            ).addEventListener('click', () => {
                row.remove();
                updateProgress();
            });

            row.addEventListener('change', updateProgress);
            collaboratorList.append(row);
        }

        function renderQueuedAttachments() {
            attachmentQueue.replaceChildren();

            if (queuedFiles.length === 0) {
                return;
            }

            const heading = document.createElement('p');
            heading.className = 'small fw-bold text-secondary mb-2';
            heading.textContent = 'Files ready to upload';
            attachmentQueue.append(heading);

            queuedFiles.forEach((file, index) => {
                const item = document.createElement('div');
                item.className = 'initiative-attachment-item';
                item.innerHTML = `
                    <div>
                        <strong></strong>
                        <small></small>
                    </div>
                    <button
                        class="btn btn-sm btn-outline-danger"
                        type="button"
                    >Remove</button>
                `;
                item.querySelector('strong').textContent = file.name;
                item.querySelector('small').textContent =
                    formatFileSize(file.size);
                item.querySelector('button').addEventListener(
                    'click',
                    () => {
                        queuedFiles.splice(index, 1);
                        renderQueuedAttachments();
                        updateProgress();
                    }
                );
                attachmentQueue.append(item);
            });
        }

        function renderExistingAttachments() {
            existingAttachmentList.replaceChildren();

            if (existingAttachments.length === 0) {
                return;
            }

            const heading = document.createElement('p');
            heading.className = 'small fw-bold text-secondary mb-2';
            heading.textContent = 'Saved attachments';
            existingAttachmentList.append(heading);

            existingAttachments.forEach((attachment) => {
                const item = document.createElement('div');
                item.className = 'initiative-attachment-item';
                item.innerHTML = `
                    <div>
                        <strong></strong>
                        <small></small>
                    </div>
                    <button
                        class="btn btn-sm btn-outline-danger"
                        type="button"
                    >Remove</button>
                `;
                item.querySelector('strong').textContent =
                    attachment.original_name;
                item.querySelector('small').textContent =
                    `${formatFileSize(
                        attachment.file_size_bytes
                    )} · saved ${AgreementApi.formatDate(
                        attachment.uploaded_at
                    )}`;

                item.querySelector('button').addEventListener(
                    'click',
                    async () => {
                        if (!window.confirm(
                            `Remove ${attachment.original_name}?`
                        )) {
                            return;
                        }

                        const button = item.querySelector('button');
                        button.disabled = true;

                        try {
                            await AgreementApi.request(
                                `/initiative-requests/${
                                    encodeURIComponent(activeRequestId)
                                }/attachments/${
                                    encodeURIComponent(
                                        attachment.attachment_id
                                    )
                                }`,
                                { method: 'DELETE' }
                            );
                            existingAttachments = existingAttachments.filter(
                                (saved) => Number(saved.attachment_id)
                                    !== Number(
                                        attachment.attachment_id
                                    )
                            );
                            renderExistingAttachments();
                            updateProgress();
                        } catch (error) {
                            showError(
                                error,
                                'The attachment could not be removed.'
                            );
                            button.disabled = false;
                        }
                    }
                );

                existingAttachmentList.append(item);
            });
        }

        function acceptFiles(fileList) {
            clearError();
            const files = Array.from(fileList || []);
            const accepted = [];

            for (const file of files) {
                const extension = String(file.name || '')
                    .split('.')
                    .pop()
                    .toLowerCase();

                if (!allowedAttachmentExtensions.has(extension)) {
                    showError(
                        null,
                        `${file.name} is not a supported file type.`
                    );
                    continue;
                }

                if (file.size < 1 || file.size > maximumAttachmentSize) {
                    showError(
                        null,
                        `${file.name} must be 10 MB or smaller.`
                    );
                    continue;
                }

                const duplicate = [
                    ...queuedFiles,
                    ...existingAttachments.map((item) => ({
                        name: item.original_name,
                        size: Number(item.file_size_bytes)
                    }))
                ].some(
                    (item) =>
                        item.name === file.name
                        && Number(item.size) === Number(file.size)
                );

                if (!duplicate) {
                    accepted.push(file);
                }
            }

            const remaining = maximumAttachmentCount
                - existingAttachments.length
                - queuedFiles.length;

            if (accepted.length > remaining) {
                showError(
                    null,
                    `A maximum of ${maximumAttachmentCount} supporting attachments is allowed.`
                );
            }

            queuedFiles.push(...accepted.slice(0, Math.max(remaining, 0)));
            attachmentInput.value = '';
            renderQueuedAttachments();
            updateProgress();
        }


        function locationRequirements(
            scope = radioValue('implementation_scope')
        ) {
            return {
                venue: ['OUTSIDE_UOB', 'HYBRID'].includes(scope),
                country: ['OUTSIDE_UOB', 'HYBRID'].includes(scope),
                platform: ['VIRTUAL', 'HYBRID'].includes(scope)
            };
        }

        function googlePlaceMetadataComplete() {
            return (
                String(
                    formField('proposed_venue_latitude')?.value || ''
                ).trim() !== ''
                && String(
                    formField('proposed_venue_longitude')?.value || ''
                ).trim() !== ''
            );
        }

        function venueSelectionComplete() {
            const venue = formField('proposed_venue');

            if (!venue || String(venue.value).trim() === '') {
                return false;
            }

            if (
                venue.dataset.manualLocationFallback === 'true'
                || venue.readOnly === false
            ) {
                return true;
            }

            return googlePlaceMetadataComplete();
        }

        function locationDetailsComplete() {
            const requirements = locationRequirements();
            const hasValue = (name) =>
                String(formField(name)?.value || '').trim() !== '';

            return (
                (!requirements.venue || venueSelectionComplete())
                && (
                    !requirements.country
                    || hasValue('implementation_country')
                )
                && (
                    !requirements.platform
                    || hasValue('online_platform_name')
                )
            );
        }

        function toggleConditionalFields(
            clearHiddenLocationValues = false
        ) {
            const targetOther = form.querySelector(
                '[data-conditional-field="target-group-other"]'
            );
            const resourceOther = form.querySelector(
                '[data-conditional-field="resource-other"]'
            );
            const relationshipType = String(
                formField('relationship_type')?.value || ''
            );
            const agreementWrap = document.querySelector(
                '[data-related-agreement-wrap]'
            );
            const partnerWrap = document.querySelector(
                '[data-external-partner-wrap]'
            );
            const internationalWrap = document.querySelector(
                '[data-international-participation-wrap]'
            );
            const primaryTypeOtherWrap = document.querySelector(
                '[data-primary-type-other-wrap]'
            );
            const sdgWrap = document.querySelector(
                '[data-sdg-wrap]'
            );
            const locationWraps = {
                venue: form.querySelector(
                    '[data-location-detail="venue"]'
                ),
                country: form.querySelector(
                    '[data-location-detail="country"]'
                ),
                platform: form.querySelector(
                    '[data-location-detail="platform"]'
                )
            };
            const requirements = locationRequirements();

            targetOther.classList.toggle(
                'd-none',
                !checkedValues('target_groups').includes('OTHER')
            );
            resourceOther?.classList.toggle(
                'd-none',
                !checkedValues('required_resources').includes('OTHER')
            );
            agreementWrap.classList.toggle(
                'd-none',
                relationshipType !== 'LINKED_AGREEMENTS'
            );
            partnerWrap.classList.toggle(
                'd-none',
                relationshipType !== 'EXTERNAL_WITHOUT_AGREEMENT'
            );
            internationalWrap.classList.toggle(
                'd-none',
                radioValue('international_participation') !== 'true'
            );
            primaryTypeOtherWrap.classList.toggle(
                'd-none',
                formField('initiative_type').value !== 'OTHER'
            );
            formField('primary_type_other').required =
                formField('initiative_type').value === 'OTHER';
            sdgWrap?.classList.toggle(
                'd-none',
                radioValue('supports_sdg') !== 'true'
            );

            Object.entries(locationWraps).forEach(([key, wrap]) => {
                const visible = requirements[key];
                const input = wrap.querySelector('input');

                wrap.classList.toggle('d-none', !visible);
                wrap.setAttribute(
                    'aria-hidden',
                    visible ? 'false' : 'true'
                );
                input.required = visible;

                if (!visible && clearHiddenLocationValues) {
                    input.value = '';
                }
            });

            if (relationshipType !== 'LINKED_AGREEMENTS') {
                setSelectedOptionValues('related_agreement_ids', []);
            }
            if (relationshipType !== 'EXTERNAL_WITHOUT_AGREEMENT') {
                formField('external_partner_name').value = '';
                formField('external_partner_country').value = '';
                formField('external_partner_role').value = '';
            }
            if (radioValue('international_participation') !== 'true') {
                formField('international_countries_text').value = '';
                formField('international_partner').value = '';
            }
            if (formField('initiative_type').value !== 'OTHER') {
                formField('primary_type_other').value = '';
            }
        }

        function enforceNoResourceCombination(changedInput = null) {
            const inputs = Array.from(
                form.querySelectorAll(
                    'input[name="required_resources"]'
                )
            );
            const none = inputs.find((input) => input.value === 'NONE');

            if (!none?.checked) {
                return;
            }

            if (changedInput && changedInput.value !== 'NONE') {
                none.checked = false;
                return;
            }

            inputs.forEach((input) => {
                if (input.value !== 'NONE') {
                    input.checked = false;
                }
            });
        }

        function relationshipDetailsComplete() {
            const relationshipType = String(
                formField('relationship_type')?.value || ''
            );

            if (relationshipType === '') {
                return false;
            }

            if (relationshipType === 'LINKED_AGREEMENTS') {
                return selectedOptionValues(
                    'related_agreement_ids'
                ).length > 0;
            }

            if (relationshipType === 'EXTERNAL_WITHOUT_AGREEMENT') {
                return (
                    String(
                        formField('external_partner_name')?.value || ''
                    ).trim() !== ''
                    && String(
                        formField('external_partner_country')?.value || ''
                    ).trim() !== ''
                );
            }

            return true;
        }
        function sectionCompletion() {
            const has = (name) => String(
                formField(name)?.value || ''
            ).trim() !== '';
            const radio = (name) => radioValue(name) !== '';
            const checked = (name) => checkedValues(name).length > 0;

            return [
                radio('requester_type'),
                has('title') && has('initiative_type'),
                has('proposed_start_date')
                    && has('proposed_end_date')
                    && radio('implementation_scope')
                    && locationDetailsComplete(),
                relationshipDetailsComplete()
                    && radio('international_participation'),
                checked('required_resources'),
                radio('supports_sdg')
                    && formField('declaration_confirmed').checked
            ];
        }

        function updateProgress() {
            const units = [
                radioValue('requester_type') !== '',
                String(formField('title').value).trim() !== '',
                String(formField('initiative_type').value).trim() !== '',
                String(formField('description').value).trim() !== '',
                String(formField('objective').value).trim() !== '',
                checkedValues('target_groups').length > 0,
                String(formField('expected_participants').value).trim() !== '',
                String(formField('proposed_start_date').value).trim() !== '',
                String(formField('proposed_end_date').value).trim() !== '',
                radioValue('implementation_scope') !== '',
                locationDetailsComplete(),
                relationshipDetailsComplete(),
                radioValue('international_participation') !== '',
                checkedValues('required_resources').length > 0,
                radioValue('supports_sdg') !== '',
                formField('declaration_confirmed').checked
            ];
            const completed = units.filter(Boolean).length;
            const percentage = Math.round(
                (completed / units.length) * 100
            );

            progressFill.style.width = `${percentage}%`;
            progressText.textContent = `${percentage}%`;
            progressTrack.setAttribute(
                'aria-valuenow',
                String(percentage)
            );

            sectionCompletion().forEach((complete, index) => {
                stepButtons[index]?.classList.toggle(
                    'is-complete',
                    complete
                );
            });
        }

        function showStep(index, scroll = true) {
            currentStep = Math.max(
                0,
                Math.min(index, stepSections.length - 1)
            );

            stepButtons.forEach((button, buttonIndex) => {
                button.classList.toggle(
                    'is-active',
                    buttonIndex === currentStep
                );
                button.setAttribute(
                    'aria-current',
                    buttonIndex === currentStep ? 'step' : 'false'
                );
            });
            stepSections.forEach((section, sectionIndex) => {
                section.classList.toggle(
                    'is-active',
                    sectionIndex === currentStep
                );
            });

            previousButton.disabled = currentStep === 0;
            nextButton.classList.toggle(
                'd-none',
                currentStep === stepSections.length - 1
            );
            submitButton.classList.toggle(
                'd-none',
                currentStep !== stepSections.length - 1
            );

            if (scroll) {
                const activeSection = stepSections[currentStep];

                /*
                 * Closing the workspace sidebar changes the form width and
                 * vertical layout. Wait for that layout to settle, then
                 * position the selected section directly below the sticky
                 * workspace top bar.
                 */
                window.requestAnimationFrame(() => {
                    window.requestAnimationFrame(() => {
                        if (!activeSection) {
                            return;
                        }

                        const topbar = document.querySelector(
                            '.workspace-topbar'
                        );
                        const topbarHeight = topbar
                            ? topbar.getBoundingClientRect().height
                            : 0;
                        const sectionTop =
                            window.scrollY
                            + activeSection
                                .getBoundingClientRect()
                                .top
                            - topbarHeight
                            - 16;

                        window.scrollTo({
                            top: Math.max(0, sectionTop),
                            behavior: 'smooth'
                        });
                    });
                });
            }
        }

        function clearCustomValidity() {
            form.querySelectorAll('input, select, textarea')
                .forEach((field) => field.setCustomValidity(''));
        }

        function submissionProblem() {
            clearCustomValidity();

            const problem = (step, field, message) => ({
                step,
                field,
                message
            });
            const field = (name) => formField(name);
            const first = (selector) => form.querySelector(selector);

            if (radioValue('requester_type') === '') {
                return problem(
                    0,
                    first('input[name="requester_type"]'),
                    'Select the requester type.'
                );
            }
            if (String(field('title').value).trim() === '') {
                return problem(1, field('title'), 'Enter the Initiative title.');
            }
            if (String(field('initiative_type').value).trim() === '') {
                return problem(
                    1,
                    field('initiative_type'),
                    'Select the Initiative or Activity type.'
                );
            }
            if (
                field('initiative_type').value === 'OTHER'
                && String(field('primary_type_other').value).trim() === ''
            ) {
                return problem(
                    1,
                    field('primary_type_other'),
                    'Specify the other Initiative or Activity type.'
                );
            }
            if (String(field('description').value).trim() === '') {
                return problem(
                    1,
                    field('description'),
                    'Enter the Initiative description.'
                );
            }
            if (String(field('objective').value).trim() === '') {
                return problem(
                    1,
                    field('objective'),
                    'Enter the main Initiative objective.'
                );
            }
            if (checkedValues('target_groups').length === 0) {
                return problem(
                    1,
                    first('input[name="target_groups"]'),
                    'Select at least one target audience.'
                );
            }
            if (
                checkedValues('target_groups').includes('OTHER')
                && String(field('target_group_other').value).trim() === ''
            ) {
                return problem(
                    1,
                    field('target_group_other'),
                    'Specify the other target audience.'
                );
            }
            if (String(field('expected_participants').value).trim() === '') {
                return problem(
                    1,
                    field('expected_participants'),
                    'Enter the expected number of participants or beneficiaries.'
                );
            }
            if (String(field('proposed_start_date').value).trim() === '') {
                return problem(
                    2,
                    field('proposed_start_date'),
                    'Select the expected start date.'
                );
            }
            if (String(field('proposed_end_date').value).trim() === '') {
                return problem(
                    2,
                    field('proposed_end_date'),
                    'Select the expected end date.'
                );
            }
            if (
                field('proposed_start_date').value
                && field('proposed_end_date').value
                && field('proposed_end_date').value
                    < field('proposed_start_date').value
            ) {
                return problem(
                    2,
                    field('proposed_end_date'),
                    'The end date cannot be before the start date.'
                );
            }
            if (radioValue('implementation_scope') === '') {
                return problem(
                    2,
                    first('input[name="implementation_scope"]'),
                    'Select the implementation scope.'
                );
            }
            const locationScope = radioValue(
                'implementation_scope'
            );
            const locationRules = locationRequirements(locationScope);

            if (
                locationRules.venue
                && String(field('proposed_venue').value).trim() === ''
            ) {
                return problem(
                    2,
                    field('proposed_venue'),
                    'Search Google Maps and select a place.'
                );
            }
            if (
                locationRules.venue
                && field('proposed_venue').readOnly
                && field('proposed_venue')
                    .dataset.manualLocationFallback !== 'true'
                && !googlePlaceMetadataComplete()
            ) {
                return problem(
                    2,
                    field('proposed_venue'),
                    'Select a place from Google Maps search or the interactive map.'
                );
            }
            if (
                locationRules.country
                && String(
                    field('implementation_country').value
                ).trim() === ''
            ) {
                return problem(
                    2,
                    field('implementation_country'),
                    'Search and select a country.'
                );
            }
            if (
                locationRules.country
                && field('implementation_country')
                    .dataset.countrySelectionValid !== 'true'
            ) {
                return problem(
                    2,
                    field('implementation_country'),
                    'Select a country from the list.'
                );
            }
            if (
                locationRules.platform
                && String(
                    field('online_platform_name').value
                ).trim() === ''
            ) {
                return problem(
                    2,
                    field('online_platform_name'),
                    'Enter the online platform name.'
                );
            }
            const relationshipType = String(
                field('relationship_type').value || ''
            );
            if (relationshipType === '') {
                return problem(
                    3,
                    field('relationship_type'),
                    'Select how the Initiative is connected to external entities or Agreements.'
                );
            }
            if (
                relationshipType === 'LINKED_AGREEMENTS'
                && selectedOptionValues('related_agreement_ids').length === 0
            ) {
                return problem(
                    3,
                    field('related_agreement_ids'),
                    'Select at least one related Agreement.'
                );
            }
            if (
                relationshipType === 'EXTERNAL_WITHOUT_AGREEMENT'
                && String(field('external_partner_name').value).trim() === ''
            ) {
                return problem(
                    3,
                    field('external_partner_name'),
                    'Enter the external entity name.'
                );
            }
            if (
                relationshipType === 'EXTERNAL_WITHOUT_AGREEMENT'
                && String(field('external_partner_country').value).trim() === ''
            ) {
                return problem(
                    3,
                    field('external_partner_country'),
                    'Enter the external entity country.'
                );
            }
            if (radioValue('international_participation') === '') {
                return problem(
                    3,
                    first('input[name="international_participation"]'),
                    'Specify whether additional international participation is included.'
                );
            }
            if (
                radioValue('international_participation') === 'true'
                && String(field('international_countries_text').value).trim() === ''
            ) {
                return problem(
                    3,
                    field('international_countries_text'),
                    'Enter at least one country of international participation.'
                );
            }            if (checkedValues('required_resources').length === 0) {
                return problem(
                    4,
                    first('input[name="required_resources"]'),
                    'Select the required support and resources.'
                );
            }
            if (
                checkedValues('required_resources').includes('OTHER')
                && String(field('resource_other').value).trim() === ''
            ) {
                return problem(
                    4,
                    field('resource_other'),
                    'Specify the other required resource.'
                );
            }
            if (radioValue('supports_sdg') === '') {
                return problem(
                    5,
                    first('input[name="supports_sdg"]'),
                    'Specify whether the Initiative supports the SDGs.'
                );
            }
            if (
                radioValue('supports_sdg') === 'true'
                && checkedValues('sdg_goals').length === 0
            ) {
                return problem(
                    5,
                    first('input[name="sdg_goals"]'),
                    'Select at least one Sustainable Development Goal.'
                );
            }
            if (!field('declaration_confirmed').checked) {
                return problem(
                    5,
                    field('declaration_confirmed'),
                    'Accept the final declaration before submission.'
                );
            }

            const collaboratorRows = Array.from(
                document.querySelectorAll('[data-collaborator-row]')
            );
            const selectedCollaborators = new Set();

            for (const row of collaboratorRows) {
                const select = row.querySelector(
                    '[data-collaborator-user]'
                );
                const userId = Number(select.value);

                if (!Number.isInteger(userId) || userId < 1) {
                    return problem(
                        3,
                        select,
                        'Select a person for every collaborator row.'
                    );
                }

                if (selectedCollaborators.has(userId)) {
                    return problem(
                        3,
                        select,
                        'The same collaborator cannot be added more than once.'
                    );
                }

                selectedCollaborators.add(userId);
            }

            return null;
        }

        function reportProblem(problem) {
            if (!problem) {
                return true;
            }

            showStep(problem.step);
            problem.field.setCustomValidity(problem.message);
            window.setTimeout(() => {
                if (
                    markEnhancedMultiInvalid(
                        problem.field,
                        problem.message
                    )
                ) {
                    return;
                }

                problem.field.reportValidity();
                problem.field.focus();
            }, 50);
            return false;
        }

        function payload() {
            const numericOrNull = (name) => {
                const value = String(formField(name)?.value || '').trim();
                return value === '' ? null : Number(value);
            };

            return {
                title: formField('title').value.trim(),
                initiative_type:
                    formField('initiative_type').value || null,
                primary_type_other:
                    formField('initiative_type').value === 'OTHER'
                        ? formField('primary_type_other').value.trim()
                        : '',
                secondary_types: [],
                description: formField('description').value.trim(),
                objective: formField('objective').value.trim(),
                expected_impact:
                    formField('expected_impact').value.trim(),
                beneficiaries: formField('beneficiaries').value.trim(),
                target_groups: checkedValues('target_groups'),
                target_group_other:
                    formField('target_group_other').value.trim(),
                expected_participants:
                    numericOrNull('expected_participants'),
                proposed_start_date:
                    formField('proposed_start_date').value || null,
                proposed_end_date:
                    formField('proposed_end_date').value || null,
                implementation_scope:
                    radioValue('implementation_scope') || null,
                implementation_scope_other:
                    formField('implementation_scope_other').value.trim(),
                proposed_venue:
                    formField('proposed_venue').value.trim(),
                proposed_venue_place_id:
                    formField('proposed_venue_place_id').value.trim(),
                proposed_venue_name:
                    formField('proposed_venue_name').value.trim(),
                proposed_venue_latitude:
                    formField('proposed_venue_latitude').value.trim(),
                proposed_venue_longitude:
                    formField('proposed_venue_longitude').value.trim(),
                proposed_venue_country_code:
                    formField('proposed_venue_country_code').value.trim(),
                implementation_country:
                    (
                        formField('implementation_country')
                            .dataset.countryNameEnglish
                        || formField('implementation_country').value
                    ).trim(),
                online_platform_name:
                    formField('online_platform_name').value.trim(),
                relationship_type:
                    formField('relationship_type').value || null,
                related_agreement_ids:
                    selectedOptionValues('related_agreement_ids')
                        .map((value) => Number(value))
                        .filter((value) => Number.isInteger(value) && value > 0),
                related_agreement_id:
                    selectedOptionValues('related_agreement_ids').length > 0
                        ? Number(selectedOptionValues('related_agreement_ids')[0])
                        : null,
                has_related_agreement:
                    formField('relationship_type').value === ''
                        ? null
                        : formField('relationship_type').value
                            === 'LINKED_AGREEMENTS',
                has_external_partner:
                    formField('relationship_type').value === ''
                        ? null
                        : [
                            'EXTERNAL_WITHOUT_AGREEMENT',
                            'LINKED_AGREEMENTS'
                        ].includes(formField('relationship_type').value),
                external_partner_name:
                    formField('external_partner_name').value.trim(),
                external_partner_country:
                    formField('external_partner_country').value.trim(),
                external_partner_role:
                    formField('external_partner_role').value.trim(),
                international_participation:
                    nullableBoolean('international_participation'),
                international_countries:
                    formField('international_countries_text').value
                        .split(',')
                        .map((value) => value.trim())
                        .filter(Boolean),
                international_partner:
                    formField('international_partner').value.trim(),
                requester_mobile:
                    formField('requester_mobile').value.trim(),
                requester_type:
                    radioValue('requester_type') || null,
                requester_type_other:
                    formField('requester_type_other').value.trim(),
                required_resources:
                    checkedValues('required_resources'),
                resource_other:
                    formField('resource_other').value.trim(),
                estimated_budget:
                    numericOrNull('estimated_budget'),
                needs_media_support:
                    formField('needs_media_support').value === ''
                        ? null
                        : formField('needs_media_support').value === 'true',
                supports_sdg: nullableBoolean('supports_sdg'),
                sdg_goals: checkedValues('sdg_goals'),
                declaration_confirmed:
                    formField('declaration_confirmed').checked,
                members: Array.from(
                    document.querySelectorAll(
                        '[data-collaborator-row]'
                    )
                ).map((row) => ({
                    user_id: Number(
                        row.querySelector(
                            '[data-collaborator-user]'
                        ).value
                    ),
                    member_role: row.querySelector(
                        '[data-collaborator-role]'
                    ).value,
                    can_convert_after_approval: true
                })).filter((member) =>
                    Number.isInteger(member.user_id)
                    && member.user_id > 0
                )
            };
        }

        function fillDraft(data) {
            const simpleFields = [
                'title',
                'initiative_type',
                'primary_type_other',
                'description',
                'objective',
                'expected_impact',
                'beneficiaries',
                'target_group_other',
                'expected_participants',
                'proposed_start_date',
                'proposed_end_date',
                'implementation_scope_other',
                'proposed_venue',
                'proposed_venue_place_id',
                'proposed_venue_name',
                'proposed_venue_latitude',
                'proposed_venue_longitude',
                'proposed_venue_country_code',
                'implementation_country',
                'online_platform_name',
                'relationship_type',
                'external_partner_name',
                'external_partner_country',
                'external_partner_role',
                'international_partner',
                'requester_mobile',
                'requester_type_other',
                'resource_other',
                'estimated_budget'
            ];

            simpleFields.forEach((name) => {
                const field = formField(name);

                if (field) {
                    field.value = data[name] ?? '';
                }
            });

            formField('needs_media_support').value =
                data.needs_media_support === null
                || data.needs_media_support === undefined
                    ? ''
                    : booleanValue(data.needs_media_support)
                        ? 'true'
                        : 'false';

            setRadioValue('requester_type', data.requester_type);
            setRadioValue(
                'implementation_scope',
                data.implementation_scope
            );
            const savedRelationshipType = String(
                data.relationship_type
                || (
                    booleanValue(data.has_related_agreement)
                    || data.related_agreement_id
                        ? 'LINKED_AGREEMENTS'
                        : booleanValue(data.has_external_partner)
                            ? 'EXTERNAL_WITHOUT_AGREEMENT'
                            : (
                                data.has_related_agreement === false
                                && data.has_external_partner === false
                                    ? 'NO_EXTERNAL_PARTY'
                                    : ''
                            )
                )
            );
            formField('relationship_type').value = savedRelationshipType;
            setSelectedOptionValues(
                'related_agreement_ids',
                Array.isArray(data.related_agreement_ids)
                    ? data.related_agreement_ids
                    : data.related_agreement_id
                        ? [data.related_agreement_id]
                        : []
            );
            setRadioValue(
                'international_participation',
                data.international_participation === null
                || data.international_participation === undefined
                    ? null
                    : booleanValue(data.international_participation)
            );
            formField('international_countries_text').value =
                Array.isArray(data.international_countries)
                    ? data.international_countries.join(', ')
                    : '';
            setRadioValue(
                'supports_sdg',
                data.supports_sdg === null
                || data.supports_sdg === undefined
                    ? null
                    : booleanValue(data.supports_sdg)
            );
            setCheckedValues('target_groups', data.target_groups);
            setCheckedValues(
                'required_resources',
                data.required_resources
            );
            setCheckedValues('sdg_goals', data.sdg_goals);
            formField('declaration_confirmed').checked =
                booleanValue(data.declaration_confirmed);

            collaboratorList.replaceChildren();
            (data.members || []).forEach(addCollaborator);
            existingAttachments = Array.isArray(data.attachments)
                ? data.attachments
                : [];
            renderExistingAttachments();
            toggleConditionalFields();
            updateProgress();
            form.dispatchEvent(
                new CustomEvent('initiative:form-data-loaded', {
                    detail: { request: data }
                })
            );
        }

        function setBusy(isBusy, activeButton = null) {
            form.querySelectorAll('button, input, select, textarea')
                .forEach((control) => {
                    control.disabled = isBusy;
                });

            if (activeButton && isBusy) {
                activeButton.dataset.originalText =
                    activeButton.textContent;
            }
        }

        function restoreControls() {
            form.querySelectorAll('button, input, select, textarea')
                .forEach((control) => {
                    control.disabled = false;
                });
            previousButton.disabled = currentStep === 0;
            [saveButton, submitButton].forEach((button) => {
                if (button.dataset.originalText) {
                    button.textContent = button.dataset.originalText;
                    delete button.dataset.originalText;
                }
            });
        }

        async function persistRequest(forSubmission) {
            const body = payload();
            let persistedRequestId = activeRequestId;

            if (persistedRequestId) {
                await AgreementApi.request(
                    `/initiative-requests/${
                        encodeURIComponent(persistedRequestId)
                    }`,
                    {
                        method: 'PUT',
                        body: AgreementApi.jsonBody(body)
                    }
                );
            } else {
                body.submit = false;
                const result = await AgreementApi.request(
                    '/initiative-requests',
                    {
                        method: 'POST',
                        body: AgreementApi.jsonBody(body)
                    }
                );
                persistedRequestId = Number(result.request_id);
                activeRequestId = persistedRequestId;
            }

            if (queuedFiles.length > 0) {
                const fileBody = new FormData();
                queuedFiles.forEach((file) => {
                    fileBody.append('attachments[]', file, file.name);
                });

                existingAttachments = await AgreementApi.request(
                    `/initiative-requests/${
                        encodeURIComponent(persistedRequestId)
                    }/attachments`,
                    {
                        method: 'POST',
                        body: fileBody
                    }
                );
                queuedFiles = [];
                renderQueuedAttachments();
                renderExistingAttachments();
            }

            if (forSubmission) {
                await AgreementApi.request(
                    `/initiative-requests/${
                        encodeURIComponent(persistedRequestId)
                    }/submit`,
                    { method: 'POST' }
                );
            }

            return persistedRequestId;
        }

        async function saveDraft() {
            clearCustomValidity();

            if (String(formField('title').value).trim() === '') {
                reportProblem({
                    step: 1,
                    field: formField('title'),
                    message: 'Enter the Initiative title before saving.'
                });
                return;
            }

            clearError();
            setBusy(true, saveButton);
            saveButton.textContent = 'Saving…';

            try {
                const persistedRequestId = await persistRequest(false);
                window.location.assign(
                    `initiative-workflow.php?view=detail&id=${
                        encodeURIComponent(persistedRequestId)
                    }`
                );
            } catch (error) {
                showError(
                    error,
                    'The Initiative draft could not be saved.'
                );
                restoreControls();
            }
        }

        async function submitForApproval() {
            const problem = submissionProblem();

            if (!reportProblem(problem)) {
                return;
            }

            clearError();
            setBusy(true, submitButton);
            submitButton.textContent = draft?.status === 'REVISION_REQUIRED'
                ? 'Resubmitting…'
                : 'Submitting…';

            try {
                const persistedRequestId = await persistRequest(true);
                window.location.assign(
                    `initiative-workflow.php?view=detail&id=${
                        encodeURIComponent(persistedRequestId)
                    }`
                );
            } catch (error) {
                showError(
                    error,
                    'The Initiative request could not be submitted.'
                );
                restoreControls();
            }
        }

        try {
            const user = await AgreementApi.requireSession();
            const [access, profile, agreements, collaborators] =
                await Promise.all([
                    AgreementApi.request('/initiative-access'),
                    AgreementApi.request('/initiative-requests/requester-profile'),
                    AgreementApi.request('/agreements'),
                    AgreementApi.request(
                        '/initiative-eligible-collaborators'
                    )
                ]);

            if (!access.can_create_initiative) {
                form.classList.add('d-none');
                showError(
                    null,
                    'You are not allowed to create an Initiative request.'
                );
                return;
            }

            setProfile(profile);
            people.push(
                ...(
                    Array.isArray(collaborators)
                        ? collaborators
                        : collaborators.items || []
                )
            );

            const agreementSelect = formField(
                'related_agreement_ids'
            );
            (
                Array.isArray(agreements)
                    ? agreements
                    : agreements.items || []
            ).forEach((agreement) => {
                if (!['ACTIVE', 'APPROVED'].includes(agreement.status)) {
                    return;
                }

                const option = document.createElement('option');
                option.value = agreement.agreement_id;
                const partnerName = String(
                    agreement.primary_partner_name
                    || agreement.partner_name
                    || agreement.partner_organization_name
                    || ''
                ).trim();
                option.textContent =
                    `${agreement.agreement_code || ''} — ${
                        agreement.title
                    }${partnerName ? ` · ${partnerName}` : ''}`;
                agreementSelect.append(option);
            });

            initializeEnhancedMultiControls(form, [
                'target_groups',
                'required_resources'
            ]);

            const preview = document.querySelector(
                '[data-route-preview]'
            );
            preview.replaceChildren();
            routeFor(user).forEach((label, index) => {
                const step = document.createElement('div');
                step.className =
                    `timeline-step${index === 0 ? ' is-current' : ''}`;
                step.innerHTML = `
                    <span class="timeline-marker">${index + 1}</span>
                    <strong></strong>
                    <small></small>
                `;
                step.querySelector('strong').textContent = label;
                step.querySelector('small').textContent =
                    index === 0
                        ? AgreementApi.displayName(user)
                        : 'Resolved from University hierarchy';
                preview.append(step);
            });

            if (isEditing) {
                draft = await AgreementApi.request(
                    `/initiative-requests/${
                        encodeURIComponent(requestId)
                    }`
                );

                if (!draft?.can_edit_draft) {
                    form.classList.add('d-none');
                    showError(
                        null,
                        'This request is not an editable draft.'
                    );
                    return;
                }

                const isRevision =
                    draft.status === 'REVISION_REQUIRED';
                document.querySelector(
                    '[data-form-heading]'
                ).textContent = isRevision
                    ? 'Revise Initiative request'
                    : 'Edit Initiative draft';
                document.querySelector(
                    '[data-form-description]'
                ).textContent = isRevision
                    ? 'Apply the requested changes, review every section, and resubmit. A new approval cycle will start from the first required stage.'
                    : 'Update the full request, supporting files, collaborators, and Agreement link before submission.';
                saveButton.textContent = isRevision
                    ? 'Save revision'
                    : 'Save changes';
                submitButton.textContent = isRevision
                    ? 'Resubmit for approval'
                    : 'Submit for approval';
                fillDraft(draft);
            } else {
                formField('requester_mobile').value = profile.mobile || '';
                setRadioValue(
                    'requester_type',
                    profile.suggested_requester_type
                );

                if (
                    Number.isInteger(preselectedAgreementId)
                    && preselectedAgreementId > 0
                    && Array.from(agreementSelect.options).some(
                        (option) =>
                            Number(option.value)
                            === preselectedAgreementId
                    )
                ) {
                    formField('relationship_type').value =
                        'LINKED_AGREEMENTS';
                    setSelectedOptionValues(
                        'related_agreement_ids',
                        [preselectedAgreementId]
                    );
                }

                toggleConditionalFields();
                updateProgress();
                form.dispatchEvent(
                    new CustomEvent('initiative:form-data-loaded', {
                        detail: { request: null }
                    })
                );
            }
        } catch (error) {
            showError(
                error,
                'The Initiative form could not be prepared.'
            );
            form.classList.add('d-none');
            return;
        }

        stepButtons.forEach((button, index) => {
            button.addEventListener('click', (event) => {
                event.preventDefault();

                /*
                 * Capture and display the requested section first.
                 * Then close the workspace navigation. showStep()
                 * performs the corrected scroll after the layout settles.
                 */
                showStep(index);

                document.getElementById(
                    'workspaceSidebar'
                )?.classList.remove('is-open');
            });
        });
        previousButton.addEventListener(
            'click',
            () => showStep(currentStep - 1)
        );
        nextButton.addEventListener(
            'click',
            () => showStep(currentStep + 1)
        );
        document.querySelector(
            '[data-add-collaborator]'
        )?.addEventListener('click', () => addCollaborator());
        attachmentInput.addEventListener('change', () => {
            acceptFiles(attachmentInput.files);
        });
        form.addEventListener('input', () => {
            clearCustomValidity();
            toggleConditionalFields();
            updateProgress();
        });
        form.addEventListener('change', (event) => {
            if (
                event.target instanceof HTMLInputElement
                && event.target.name === 'required_resources'
            ) {
                enforceNoResourceCombination(event.target);
            }
            clearCustomValidity();
            toggleConditionalFields(
                event.target instanceof HTMLInputElement
                && event.target.name === 'implementation_scope'
            );
            updateProgress();
        });
        saveButton.addEventListener('click', saveDraft);
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            submitForApproval();
        });

        showStep(0, false);
        toggleConditionalFields();
        updateProgress();
    }

    async function initializeNotifications() {
        const loading = document.querySelector(
            '[data-notifications-loading]'
        );
        const list = document.querySelector(
            '[data-notification-list]'
        );
        const empty = document.querySelector(
            '[data-notifications-empty]'
        );
        const unreadOnly = document.querySelector(
            '[data-unread-notifications-only]'
        );
        const markAll = document.querySelector(
            '[data-mark-all-notifications-read]'
        );
        let notifications = [];

        function filteredNotifications() {
            if (!unreadOnly.checked) {
                return notifications;
            }

            return notifications.filter(
                (item) => !booleanValue(item.is_read)
            );
        }

        async function markRead(notificationId) {
            await AgreementApi.request(
                `/initiative-requests/notifications/${
                    encodeURIComponent(notificationId)
                }/read`,
                { method: 'POST' }
            );

            const item = notifications.find(
                (notification) =>
                    Number(notification.notification_id)
                    === Number(notificationId)
            );

            if (item) {
                item.is_read = true;
                item.read_at = new Date().toISOString();
            }

            await refreshNotificationBadge();
        }

        function render() {
            const items = filteredNotifications();

            list.replaceChildren();
            list.classList.toggle('d-none', items.length === 0);
            empty.classList.toggle('d-none', items.length !== 0);

            items.forEach((notification) => {
                const isRead = booleanValue(notification.is_read);
                const item = document.createElement('article');
                item.className =
                    `initiative-notification-item${
                        isRead ? '' : ' is-unread'
                    }`;

                const indicator = document.createElement('span');
                indicator.className =
                    'initiative-notification-indicator';
                indicator.setAttribute('aria-hidden', 'true');

                const copy = document.createElement('div');
                copy.className = 'initiative-notification-copy';

                const header = document.createElement('div');
                header.className = 'initiative-notification-header';

                const title = document.createElement('strong');
                title.textContent = text(
                    notification.title,
                    'Initiative update'
                );

                const time = document.createElement('time');
                time.textContent = AgreementApi.formatDate(
                    notification.created_at
                );

                header.append(title, time);

                const message = document.createElement('p');
                message.textContent = text(
                    notification.message,
                    'The Initiative request was updated.'
                );

                const context = document.createElement('small');
                context.textContent = notification.request_code
                    ? `${notification.request_code} · ${
                        text(
                            notification.request_title,
                            'Initiative request'
                        )
                    }`
                    : String(
                        notification.notification_type
                        || 'INITIATIVE_UPDATE'
                    ).replaceAll('_', ' ');

                copy.append(header, message, context);

                const actions = document.createElement('div');
                actions.className =
                    'initiative-notification-actions';

                if (!isRead) {
                    const readButton = document.createElement('button');
                    readButton.className =
                        'btn btn-sm btn-outline-secondary';
                    readButton.type = 'button';
                    readButton.textContent = 'Mark read';

                    readButton.addEventListener(
                        'click',
                        async () => {
                            readButton.disabled = true;

                            try {
                                await markRead(
                                    notification.notification_id
                                );
                                render();
                            } catch (error) {
                                showError(
                                    error,
                                    'The notification could not be updated.'
                                );
                                readButton.disabled = false;
                            }
                        }
                    );

                    actions.append(readButton);
                }

                if (notification.request_id) {
                    const requestHref =
                        `initiative-workflow.php?view=detail&id=${
                            encodeURIComponent(
                                notification.request_id
                            )
                        }`;

                    const openNotification = async () => {
                        if (item.dataset.opening === 'true') {
                            return;
                        }

                        item.dataset.opening = 'true';
                        item.setAttribute('aria-busy', 'true');

                        try {
                            if (!booleanValue(notification.is_read)) {
                                await markRead(
                                    notification.notification_id
                                );
                            }
                        } catch (error) {
                            showError(
                                error,
                                'The notification could not be marked as read.'
                            );
                        } finally {
                            window.location.assign(requestHref);
                        }
                    };

                    item.classList.add('is-clickable');
                    item.tabIndex = 0;
                    item.setAttribute('role', 'link');
                    item.setAttribute(
                        'aria-label',
                        `Open ${
                            text(
                                notification.request_code,
                                'Initiative request'
                            )
                        }`
                    );

                    item.addEventListener('click', (event) => {
                        const interactiveTarget =
                            event.target instanceof Element
                            && event.target.closest('button, a');

                        if (interactiveTarget) {
                            return;
                        }

                        openNotification();
                    });

                    item.addEventListener('keydown', (event) => {
                        if (
                            event.key !== 'Enter'
                            && event.key !== ' '
                        ) {
                            return;
                        }

                        event.preventDefault();
                        openNotification();
                    });

                    const open = document.createElement('a');
                    open.className = 'btn btn-sm btn-primary';
                    open.href = requestHref;
                    open.textContent = 'Open request';

                    open.addEventListener('click', (event) => {
                        event.preventDefault();
                        openNotification();
                    });

                    actions.append(open);
                }

                item.append(indicator, copy, actions);
                list.append(item);
            });
        }

        async function load() {
            loading.classList.remove('d-none');
            list.classList.add('d-none');
            empty.classList.add('d-none');

            const query = unreadOnly.checked
                ? '?unread_only=true'
                : '';

            const payload = await AgreementApi.request(
                `/initiative-requests/notifications${query}`
            );

            notifications = Array.isArray(payload)
                ? payload
                : (payload.items || []);

            loading.classList.add('d-none');
            render();
            await refreshNotificationBadge();
        }

        try {
            await AgreementApi.requireSession();
            await load();
        } catch (error) {
            loading.classList.add('d-none');
            showError(
                error,
                'Initiative notifications could not be loaded.'
            );
        }

        unreadOnly.addEventListener('change', () => {
            load().catch((error) => {
                loading.classList.add('d-none');
                showError(
                    error,
                    'Initiative notifications could not be loaded.'
                );
            });
        });

        markAll.addEventListener('click', async () => {
            markAll.disabled = true;
            clearError();

            try {
                await AgreementApi.request(
                    '/initiative-requests/notifications/read-all',
                    { method: 'POST' }
                );

                notifications.forEach((item) => {
                    item.is_read = true;
                });
                render();
                await refreshNotificationBadge();
            } catch (error) {
                showError(
                    error,
                    'Notifications could not be marked as read.'
                );
            } finally {
                markAll.disabled = false;
            }
        });
    }

    async function initializeConversion() {
        const loading = document.querySelector('[data-loading]');
        const content = document.querySelector('[data-final-content]');
        const form = document.querySelector('[data-final-form]');
        const existingMode =
            view === 'existing'
            || form?.dataset.finalMode === 'existing';
        const id = requestId;
        let existingDraftId = Number(params.get('draft_id')) || null;
        const stepButtons = Array.from(
            document.querySelectorAll('[data-final-step-button]')
        );
        const stepSections = Array.from(
            document.querySelectorAll('[data-final-step]')
        );
        const previousButton = document.querySelector(
            '[data-final-previous]'
        );
        const nextButton = document.querySelector(
            '[data-final-next]'
        );
        const saveButton = document.querySelector(
            '[data-final-save]'
        );
        const finalizeButton = document.querySelector(
            '[data-finalize]'
        );
        const fileInput = document.querySelector(
            '[data-final-file-input]'
        );
        const peopleContainer = document.querySelector(
            '[data-final-contributors]'
        );
        const personTemplate = document.querySelector(
            '[data-final-person-template]'
        );
        const participantsContainer = document.querySelector(
            '[data-final-participants]'
        );
        const participantTemplate = document.querySelector(
            '[data-final-participant-template]'
        );
        const printButton = document.querySelector('[data-final-print]');
        const translateFinalText = (value) => (
            typeof window.workspaceT === 'function'
                ? window.workspaceT(value)
                : value
        );

        if ((!existingMode && !id) || !form) {
            showError(
                null,
                existingMode
                    ? 'The existing Initiative form is unavailable.'
                    : 'The approved Initiative request is missing.'
            );
            loading?.classList.add('d-none');
            return;
        }


        let currentStep = 0;
        let agreements = [];
        let requestAttachments = [];
        let conversionAttachments = [];
        let queuedFiles = [];
        const excludedRequestAttachmentIds = new Set();

        const updateExistingDraftUrl = () => {
            if (!existingMode || !existingDraftId) {
                return;
            }

            const url = new URL(window.location.href);
            url.searchParams.set('draft_id', existingDraftId);
            window.history.replaceState({}, '', url);
        };

        const persistExistingDraft = async () => {
            const saved = await AgreementApi.request(
                '/initiative-requests/legacy-initiative/draft',
                {
                    method: 'POST',
                    body: AgreementApi.jsonBody({
                        draft_id: existingDraftId,
                        form_data: payload()
                    })
                }
            );
            existingDraftId = Number(
                saved.conversion_draft_id
                || saved.draft_id
            ) || existingDraftId;
            updateExistingDraftUrl();
            return saved;
        };

        const field = (name) =>
            form.querySelector(`[name="${name}"]`);

        const controls = (name) => Array.from(
            form.querySelectorAll(`[name="${name}"]`)
        );

        const scalarValue = (name) => {
            const control = field(name);
            return control ? String(control.value || '').trim() : '';
        };

        const radioBoolean = (name) => {
            const checked = form.querySelector(
                `[name="${name}"]:checked`
            );
            if (!checked) {
                return null;
            }
            return checked.value === 'true';
        };

        const multiValues = (name) => {
            const control = field(name);
            if (!control) {
                return [];
            }

            if (control instanceof HTMLSelectElement) {
                return Array.from(control.selectedOptions)
                    .map((option) => option.value)
                    .filter(Boolean);
            }

            return controls(name)
                .filter((item) => item.checked)
                .map((item) => item.value)
                .filter(Boolean);
        };

        const textLines = (name) => {
            const value = scalarValue(name);
            if (value === '') {
                return [];
            }

            return value
                .split(/\r?\n|,/)
                .map((item) => item.trim())
                .filter(Boolean);
        };

        const numberOrNull = (name) => {
            const value = scalarValue(name);
            return value === '' ? null : Number(value);
        };

        function initializeFinalWorkspaceLayout() {
            const body = document.body;
            const sidebar = document.getElementById(
                'workspaceSidebar'
            );
            const toggle = document.querySelector(
                '[data-sidebar-toggle]'
            );

            body.classList.add('initiative-final-focus');

            if (!sidebar || !toggle) {
                return;
            }

            sidebar.classList.remove('is-open');

            const syncLayout = () => {
                const isOpen = sidebar.classList.contains('is-open');

                body.classList.toggle(
                    'initiative-final-sidebar-open',
                    isOpen
                );
                toggle.setAttribute(
                    'aria-expanded',
                    String(isOpen)
                );
                toggle.setAttribute(
                    'aria-label',
                    isOpen
                        ? 'Hide workspace navigation'
                        : 'Open workspace navigation'
                );
            };

            syncLayout();

            const observer = new MutationObserver(syncLayout);
            observer.observe(sidebar, {
                attributes: true,
                attributeFilter: ['class']
            });

            const isStepTarget = (target) => (
                target instanceof Element
                && Boolean(
                    target.closest('[data-final-step-button]')
                )
            );

            const closeForInteraction = (event) => {
                if (
                    event
                    && isStepTarget(event.target)
                ) {
                    return;
                }

                sidebar.classList.remove('is-open');
            };

            form.addEventListener(
                'pointerdown',
                closeForInteraction,
                { passive: true }
            );
            form.addEventListener(
                'focusin',
                closeForInteraction
            );

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    sidebar.classList.remove('is-open');
                }
            });
        }

        function applyReferenceFinalFormLayout() {
            if (form.dataset.referenceLayoutApplied === 'true') return;
            const rows = stepSections.map((section) =>
                section.querySelector(':scope > .row')
            );
            if (rows.some((row) => !row)) return;

            const blockFor = (name) => {
                const control = field(name);
                return control
                    ? (control.closest('[class*="col-"]') || control)
                    : null;
            };
            const appendUnique = (row, nodes) => {
                const seen = new Set();
                nodes.filter(Boolean).forEach((node) => {
                    if (seen.has(node)) return;
                    seen.add(node);
                    row.append(node);
                });
            };
            const heading = (title, description) => {
                const wrap = document.createElement('div');
                wrap.className = 'col-12 initiative-final-generated-heading';
                wrap.innerHTML = `
                    <div class="initiative-final-subheading">
                        <div><h3>${translateFinalText(title)}</h3><p>${translateFinalText(description)}</p></div>
                    </div>`;
                return wrap;
            };

            const requesterHeading = blockFor('requester_name')
                ?.previousElementSibling;
            const responsibleBlock = document.querySelector(
                '[data-final-contributors]'
            )?.closest('.col-12');
            const participantBlock = document.querySelector(
                '[data-final-participant-group]'
            );
            const internationalBlock = document.querySelector(
                '[data-final-international]'
            );

            rows.slice(0, 4).forEach((row) => {
                Array.from(row.children).forEach((child) => {
                    const hasSubheading = child.querySelector?.(
                        ':scope > .initiative-final-subheading'
                    );
                    if (
                        hasSubheading
                        && child !== requesterHeading
                        && child !== responsibleBlock
                        && child !== participantBlock
                    ) child.remove();
                });
            });

            appendUnique(rows[0], [
                heading('Reference and Submitter Information', 'The approved request values are prefilled and may be updated for the official Initiative record.'),
                blockFor('approval_request_id'), blockFor('legacy_approval_date'), blockFor('initiative_number'),
                requesterHeading, blockFor('requester_name'), blockFor('requester_email'), blockFor('requester_mobile'),
                blockFor('requester_type'), blockFor('requester_type_other'), blockFor('requester_position'),
                blockFor('requester_entity'), blockFor('requester_department'),
                heading('Activity Information', 'Record the Initiative identity, implementing entity, timing, status, and delivery location.'),
                blockFor('title'), blockFor('initiative_type'), blockFor('initiative_type_other'),
                blockFor('entity'), blockFor('department_unit'), blockFor('start_date'), blockFor('end_date'),
                blockFor('duration_hours'), blockFor('activity_status'), blockFor('activity_recurrence'),
                blockFor('academic_year'), blockFor('location_mode'), blockFor('implementation_scope_other'),
                blockFor('proposed_venue'), blockFor('outside_location'), blockFor('proposed_venue_place_id'),
                blockFor('proposed_venue_name'), blockFor('proposed_venue_latitude'), blockFor('proposed_venue_longitude'),
                blockFor('proposed_venue_country_code'), blockFor('implementation_country'), blockFor('online_platform_name'),
                blockFor('international_participation'), internationalBlock
            ]);
            appendUnique(rows[1], [
                heading('Responsible People and Participants', 'Confirm the responsible people and optionally record additional implementation participants.'),
                responsibleBlock, participantBlock, blockFor('provider_categories'),
                heading('Partnerships and Agreements', 'Choose the relationship type, linked Agreements, and external entity details.'),
                blockFor('relationship_type'), blockFor('related_agreement_ids'), blockFor('relation_notes'),
                blockFor('external_entities'), blockFor('external_partner_name'), blockFor('external_partner_country'),
                blockFor('external_partner_role')
            ]);
            appendUnique(rows[2], [
                heading('Description and Contribution Areas', 'Describe the Initiative, its objectives, and the areas to which it contributes.'),
                blockFor('description'), blockFor('objectives'), blockFor('initiative_descriptors'),
                heading('Beneficiaries and Attendance', 'Select every target group and record the available beneficiary counts.'),
                blockFor('target_groups'), blockFor('target_group_other'), blockFor('school_names'),
                blockFor('expected_participants'), blockFor('male_count'), blockFor('female_count'),
                blockFor('unspecified_count'), blockFor('beneficiary_count_basis'), blockFor('youth_18_35'),
                blockFor('beneficiaries')
            ]);
            appendUnique(rows[3], [
                heading('Resources, Funding, Training, and Volunteering', 'Record the resources mobilized and the relevant financial or participation values.'),
                blockFor('resources_mobilized_options'), blockFor('resources_mobilized_other'),
                blockFor('expected_budget'), blockFor('internal_funding_bhd'), blockFor('in_kind_support_bhd'),
                blockFor('external_funding_amount'), blockFor('external_funding_currency'), blockFor('funding_entity'),
                blockFor('training_hours'), blockFor('trainees_count'), blockFor('volunteers_count'),
                blockFor('volunteer_hours_per_person'), blockFor('direct_outputs'), blockFor('expected_impact'),
                blockFor('societal_impact'),
                heading('Global Rankings and Sustainable Development Goals', 'Complete only the ranking, environmental, and SDG fields that apply.'),
                blockFor('ranking_framework'), blockFor('the_areas'), blockFor('qs_categories'),
                blockFor('environmental_impact_types'), blockFor('environmental_before_value'),
                blockFor('environmental_after_value'), blockFor('environmental_improvement_value'),
                blockFor('environmental_unit'), blockFor('environmental_measurement_basis'),
                blockFor('environmental_data_source'), blockFor('environmental_impact'),
                blockFor('supports_sdg'), blockFor('primary_sdg'), blockFor('secondary_sdgs')
            ]);
            form.dataset.referenceLayoutApplied = 'true';
        }

        function sectionIsComplete(index) {
            const section = stepSections[index];

            if (!section) {
                return false;
            }

            const required = Array.from(
                section.querySelectorAll(
                    'input[required], select[required], textarea[required]'
                )
            ).filter((control) => !control.disabled);

            return required.length === 0
                || required.every((control) => control.checkValidity());
        }

        function scrollToSection(section) {
            window.requestAnimationFrame(() => {
                window.requestAnimationFrame(() => {
                    if (!section) {
                        return;
                    }

                    const topbar = document.querySelector(
                        '.workspace-topbar'
                    );
                    const topbarHeight = topbar
                        ? topbar.getBoundingClientRect().height
                        : 0;
                    const top =
                        window.scrollY
                        + section.getBoundingClientRect().top
                        - topbarHeight
                        - 16;

                    window.scrollTo({
                        top: Math.max(0, top),
                        behavior: 'smooth'
                    });
                });
            });
        }

        function updateReview() {
            const setText = (selector, value) => {
                const target = document.querySelector(selector);
                if (target) {
                    target.textContent = text(value);
                }
            };

            setText(
                '[data-final-review-title]',
                scalarValue('title')
            );
            setText(
                '[data-final-review-type]',
                field('initiative_type')
                    ?.selectedOptions?.[0]
                    ?.textContent
            );
            setText(
                '[data-final-review-dates]',
                `${scalarValue('start_date') || '—'} to ${
                    scalarValue('end_date') || '—'
                }`
            );
            setText(
                '[data-final-review-targets]',
                multiValues('target_groups').join(', ')
            );

            const sdgs = [
                scalarValue('primary_sdg'),
                ...multiValues('secondary_sdgs')
            ].filter(Boolean);
            setText(
                '[data-final-review-sdgs]',
                sdgs.join(', ')
            );
        }

        function updateStepState() {
            stepButtons.forEach((button, index) => {
                const active = index === currentStep;

                button.classList.toggle('is-active', active);
                button.classList.toggle(
                    'is-complete',
                    !active && sectionIsComplete(index)
                );
                button.setAttribute(
                    'aria-current',
                    active ? 'step' : 'false'
                );
            });

            previousButton.disabled = currentStep === 0;
            nextButton.classList.toggle(
                'd-none',
                currentStep === stepSections.length - 1
            );
            finalizeButton.classList.toggle(
                'd-none',
                currentStep !== stepSections.length - 1
            );
        }

        function showStep(index, shouldScroll = true) {
            const safeIndex = Math.max(
                0,
                Math.min(
                    Number(index) || 0,
                    stepSections.length - 1
                )
            );
            currentStep = safeIndex;

            stepSections.forEach((section, sectionIndex) => {
                const active = sectionIndex === currentStep;
                section.classList.toggle('d-none', !active);
                section.classList.toggle('is-active', active);
            });

            syncConditionalFields();
            updateReview();
            updateStepState();

            if (shouldScroll) {
                scrollToSection(stepSections[currentStep]);
            }
        }

        function setScalar(name, value) {
            const control = field(name);

            if (!control) {
                return;
            }

            control.value = value ?? '';
        }

        function setRadioBoolean(name, value) {
            controls(name).forEach((control) => {
                control.checked = value === null
                    ? false
                    : control.value === String(Boolean(value));
            });
        }

        function setMulti(name, values) {
            const normalized = Array.isArray(values)
                ? values.map(String)
                : [];
            const control = field(name);

            if (control instanceof HTMLSelectElement) {
                Array.from(control.options).forEach((option) => {
                    option.selected = normalized.includes(option.value);
                });
            } else {
                controls(name).forEach((item) => {
                    item.checked = normalized.includes(item.value);
                });
            }

            refreshEnhancedMultiByName(form, name);
        }

        function personData(card) {
            const get = (name) =>
                card.querySelector(
                    `[data-person-field="${name}"]`
                );

            return {
                user_id: Number(get('user_id')?.value) || null,
                name: String(get('name')?.value || '').trim(),
                email: String(get('email')?.value || '').trim(),
                mobile: String(get('mobile')?.value || '').trim(),
                role: String(get('role')?.value || '').trim(),
                is_primary: Boolean(get('is_primary')?.checked),
                is_coordinator:
                    Boolean(get('is_coordinator')?.checked)
            };
        }

        function updatePersonNumbers() {
            Array.from(
                peopleContainer.querySelectorAll(
                    '[data-final-person]'
                )
            ).forEach((card, index) => {
                card.querySelector(
                    '[data-final-person-number]'
                ).textContent = `Person ${index + 1}`;
            });
        }

        function addPerson(data = {}) {
            const fragment = personTemplate.content.cloneNode(true);
            const card = fragment.querySelector('[data-final-person]');
            const get = (name) =>
                card.querySelector(
                    `[data-person-field="${name}"]`
                );

            get('user_id').value = data.user_id || '';
            get('name').value = data.name || data.full_name || '';
            get('email').value = data.email || '';
            get('mobile').value = data.mobile || data.phone || '';
            get('role').value =
                data.role || data.participant_role || '';
            get('is_primary').checked =
                Boolean(data.is_primary);
            get('is_coordinator').checked =
                Boolean(data.is_coordinator);

            card.querySelector(
                '[data-remove-final-person]'
            ).addEventListener('click', () => {
                card.remove();
                updatePersonNumbers();
                updateStepState();
            });

            peopleContainer.append(fragment);
            updatePersonNumbers();
        }

        function contributors() {
            return Array.from(
                peopleContainer.querySelectorAll(
                    '[data-final-person]'
                )
            )
                .map(personData)
                .filter((person) =>
                    person.name
                    || person.email
                    || person.role
                );
        }

        function participantData(card) {
            const get = (name) => card.querySelector(
                `[data-participant-field="${name}"]`
            );
            return {
                user_id: Number(get('user_id')?.value) || null,
                name: String(get('name')?.value || '').trim(),
                email: String(get('email')?.value || '').trim(),
                mobile: String(get('mobile')?.value || '').trim(),
                department: String(get('department')?.value || '').trim(),
                role: String(get('role')?.value || '').trim(),
                is_primary: false,
                is_coordinator: false
            };
        }

        function updateParticipantNumbers() {
            const cards = Array.from(
                participantsContainer?.querySelectorAll(
                    '[data-final-participant]'
                ) || []
            );
            cards.forEach((card, index) => {
                card.querySelector('[data-final-participant-number]')
                    .textContent = translateFinalText(
                        `Participant ${index + 1}`
                    );
            });
            document.querySelector('[data-final-participants-empty]')
                ?.classList.toggle('d-none', cards.length > 0);
        }

        function addParticipant(data = {}) {
            if (!participantsContainer || !participantTemplate) return;
            const fragment = participantTemplate.content.cloneNode(true);
            const card = fragment.querySelector('[data-final-participant]');
            const get = (name) => card.querySelector(
                `[data-participant-field="${name}"]`
            );
            get('user_id').value = data.user_id || '';
            get('name').value = data.name || data.full_name || '';
            get('email').value = data.email || '';
            get('mobile').value = data.mobile || data.phone || '';
            get('department').value = data.department || data.entity || '';
            get('role').value = data.role || data.participant_role || '';
            card.querySelector('[data-remove-final-participant]')
                .addEventListener('click', () => {
                    card.remove();
                    updateParticipantNumbers();
                    updateStepState();
                });
            participantsContainer.append(fragment);
            updateParticipantNumbers();
        }

        function implementationParticipants() {
            return Array.from(participantsContainer?.querySelectorAll(
                '[data-final-participant]'
            ) || []).map(participantData).filter((person) =>
                person.name || person.email || person.department || person.role
            );
        }

        function syncConditionalFields() {
            const toggleBlocks = (selector, visible) => {
                form.querySelectorAll(selector).forEach((element) => {
                    element.classList.toggle('d-none', !visible);
                    element.setAttribute('aria-hidden', String(!visible));
                });
            };
            const requireField = (name, required) => {
                const control = field(name);
                if (control) control.required = Boolean(required);
            };
            const clearScalar = (name) => {
                const control = field(name);
                if (control && String(control.value || '') !== '') {
                    control.value = '';
                }
            };
            const clearMulti = (name) => {
                const control = field(name);
                if (control instanceof HTMLSelectElement) {
                    Array.from(control.options).forEach((option) => {
                        option.selected = false;
                    });
                } else {
                    controls(name).forEach((item) => {
                        item.checked = false;
                    });
                }
                refreshEnhancedMultiByName(form, name);
            };

            const requesterOther =
                scalarValue('requester_type') === 'OTHER';
            toggleBlocks(
                '[data-final-requester-type-other]',
                requesterOther
            );
            if (!requesterOther) clearScalar('requester_type_other');

            const initiativeType = scalarValue('initiative_type');
            const initiativeOther = initiativeType === 'OTHER';
            toggleBlocks('[data-final-other-type]', initiativeOther);
            requireField('initiative_type_other', initiativeOther);
            if (!initiativeOther) clearScalar('initiative_type_other');

            const relationshipType = scalarValue('relationship_type');
            const related = relationshipType === 'LINKED_AGREEMENTS';
            toggleBlocks('[data-final-related-agreement]', related);
            requireField('related_agreement_ids', related);
            if (!related) {
                clearMulti('related_agreement_ids');
                clearScalar('relation_notes');
            }

            const externalPartner =
                relationshipType === 'EXTERNAL_WITHOUT_AGREEMENT';
            toggleBlocks('[data-final-external-partner]', externalPartner);
            requireField('external_partner_name', externalPartner);
            requireField('external_partner_country', externalPartner);
            if (!externalPartner) {
                clearScalar('external_partner_name');
                clearScalar('external_partner_country');
                clearScalar('external_partner_role');
            }

            const locationMode = scalarValue('location_mode');
            const locationOther = locationMode === 'OTHER';
            const locationVenue = [
                'WITHIN_UOB', 'OUTSIDE_UOB', 'HYBRID'
            ].includes(locationMode);
            const locationOutside = [
                'OUTSIDE_UOB', 'HYBRID'
            ].includes(locationMode);
            const locationOnline = [
                'VIRTUAL', 'HYBRID'
            ].includes(locationMode);

            toggleBlocks('[data-final-location-other]', locationOther);
            toggleBlocks('[data-final-location-venue]', locationVenue);
            toggleBlocks('[data-final-location-outside]', locationOutside);
            toggleBlocks('[data-final-location-country]', locationOutside);
            toggleBlocks('[data-final-location-platform]', locationOnline);
            requireField('implementation_scope_other', locationOther);
            requireField(
                'proposed_venue',
                locationMode === 'OUTSIDE_UOB' || locationMode === 'HYBRID'
            );
            requireField('implementation_country', locationOutside);
            requireField('online_platform_name', locationOnline);
            if (!locationOther) clearScalar('implementation_scope_other');
            if (!locationVenue) clearScalar('proposed_venue');
            if (!locationOutside) {
                clearScalar('outside_location');
                clearScalar('implementation_country');
            }
            if (!locationOnline) clearScalar('online_platform_name');

            const international =
                radioBoolean('international_participation') === true;
            toggleBlocks('[data-final-international]', international);
            if (!international) {
                clearScalar('international_countries_text');
                clearScalar('international_participants');
                clearScalar('international_partner');
                clearScalar('international_partner_type');
                clearMulti('international_collaboration_nature');
            }

            const targetGroups = multiValues('target_groups');
            const showSchool = targetGroups.includes('SCHOOL_STUDENTS');
            const showOtherTarget = targetGroups.includes('OTHER');
            toggleBlocks('[data-final-school-target]', showSchool);
            toggleBlocks('[data-final-other-target]', showOtherTarget);
            requireField('school_names', showSchool);
            requireField('target_group_other', showOtherTarget);
            if (!showSchool) clearScalar('school_names');
            if (!showOtherTarget) clearScalar('target_group_other');

            const resources = multiValues('resources_mobilized_options');
            const showOtherResource = resources.includes('OTHER');
            const showBudget = resources.includes('BUDGET');
            const showExternalFunding =
                resources.includes('EXTERNAL_FUNDING');
            const showTraining = [
                'WORKSHOP_TRAINING',
                'CAPACITY_BUILDING_TRAINING'
            ].includes(initiativeType);
            const showVolunteer =
                resources.includes('VOLUNTEERS')
                || initiativeType === 'VOLUNTEERING';
            toggleBlocks('[data-final-other-resource]', showOtherResource);
            toggleBlocks('[data-final-budget]', showBudget);
            toggleBlocks(
                '[data-final-external-funding]',
                showExternalFunding
            );
            toggleBlocks('[data-final-training]', showTraining);
            toggleBlocks('[data-final-volunteer]', showVolunteer);
            if (!showOtherResource) clearScalar('resources_mobilized_other');
            if (!showBudget) {
                clearScalar('expected_budget');
                clearScalar('internal_funding_bhd');
            }
            if (!showExternalFunding) {
                clearScalar('external_funding_amount');
                clearScalar('external_funding_currency');
                clearScalar('funding_entity');
            }
            if (!showTraining) {
                clearScalar('training_hours');
                clearScalar('trainees_count');
            }
            if (!showVolunteer) {
                clearScalar('volunteers_count');
                clearScalar('volunteer_hours_per_person');
            }

            const rankingFramework = scalarValue('ranking_framework');
            const showThe = ['THE', 'BOTH'].includes(rankingFramework);
            const showQs = ['QS', 'BOTH'].includes(rankingFramework);
            toggleBlocks('[data-final-the]', showThe);
            toggleBlocks('[data-final-qs]', showQs);
            if (!showThe) clearMulti('the_areas');
            if (!showQs) clearMulti('qs_categories');

            const showEnvironmental =
                showQs && multiValues('qs_categories').includes(
                    'ENVIRONMENTAL'
                );
            toggleBlocks('[data-final-environmental]', showEnvironmental);
            if (!showEnvironmental) {
                clearMulti('environmental_impact_types');
                [
                    'environmental_before_value',
                    'environmental_after_value',
                    'environmental_improvement_value',
                    'environmental_unit',
                    'environmental_measurement_basis',
                    'environmental_data_source',
                    'environmental_impact'
                ].forEach(clearScalar);
            }

            const supportsSdg =
                radioBoolean('supports_sdg') === true;
            toggleBlocks('[data-final-sdg]', supportsSdg);
            requireField('primary_sdg', supportsSdg);
            if (!supportsSdg) {
                clearScalar('primary_sdg');
                clearMulti('secondary_sdgs');
            }

            const coverageStatus = scalarValue('coverage_status');
            const hasCoverage = Boolean(coverageStatus)
                && coverageStatus !== 'NONE';
            toggleBlocks('[data-final-media-type]', hasCoverage);
            requireField('media_coverage_type', hasCoverage);
            if (!hasCoverage) clearScalar('media_coverage_type');

            const mediaType = scalarValue('media_coverage_type');
            const showNews = hasCoverage && mediaType === 'NEWS';
            const showTv = hasCoverage && mediaType === 'TV_INTERVIEW';
            const otherMediaTypes = [
                'SOCIAL_MEDIA', 'RADIO', 'PODCAST',
                'PRINT', 'WEBSITE', 'OTHER'
            ];
            const showOtherMedia =
                hasCoverage && otherMediaTypes.includes(mediaType);
            toggleBlocks('[data-final-news]', showNews);
            toggleBlocks('[data-final-tv]', showTv);
            toggleBlocks('[data-final-other-media]', showOtherMedia);

            if (!showNews) {
                [
                    'media_outlet_name', 'media_headline',
                    'media_publication_date', 'news_link'
                ].forEach(clearScalar);
            }
            if (!showTv) {
                [
                    'tv_channel', 'tv_program', 'tv_interview_topic',
                    'tv_interviewer', 'tv_uob_representatives',
                    'tv_interview_date', 'tv_duration_minutes',
                    'tv_broadcast_status', 'tv_broadcast_scope',
                    'tv_interview_language', 'tv_interview_link',
                    'tv_interview_highlights'
                ].forEach(clearScalar);
            }

            const otherMediumField = field(
                'coverage_other_medium_kind'
            );
            if (
                showOtherMedia
                && otherMediumField
                && otherMediumField.value !== mediaType
            ) {
                otherMediumField.value = mediaType;
            }
            if (!showOtherMedia) {
                [
                    'coverage_other_medium_kind',
                    'coverage_other_medium_other',
                    'coverage_other_outlet_name',
                    'coverage_other_reach',
                    'coverage_other_url'
                ].forEach(clearScalar);
            }
            const otherMedium = scalarValue(
                'coverage_other_medium_kind'
            );
            const specifyOtherMedium =
                showOtherMedia && otherMedium === 'OTHER';
            toggleBlocks(
                '[data-final-other-medium-name]',
                specifyOtherMedium
            );
            requireField(
                'coverage_other_medium_other',
                specifyOtherMedium
            );
            if (!specifyOtherMedium) {
                clearScalar('coverage_other_medium_other');
            }

            const evidenceTypes = multiValues('evidence_types');
            const hasEvidence = evidenceTypes.length > 0;
            const hasUploadEvidence = evidenceTypes.includes('UPLOAD');
            const hasUrlEvidence = evidenceTypes.includes('URL');
            const hasExplanationEvidence =
                evidenceTypes.includes('EXPLANATION');
            toggleBlocks('[data-final-evidence-meta]', hasEvidence);
            toggleBlocks(
                '[data-final-evidence-upload]',
                hasUploadEvidence
            );
            toggleBlocks('[data-final-evidence-url]', hasUrlEvidence);
            toggleBlocks(
                '[data-final-evidence-explanation]',
                hasExplanationEvidence
            );
            if (!hasEvidence) {
                [
                    'evidence_document_type', 'evidence_date',
                    'evidence_owner', 'evidence_public_access',
                    'public_sharing'
                ].forEach(clearScalar);
            }
            if (!hasUrlEvidence) clearScalar('evidence_urls_text');
            if (!hasExplanationEvidence) {
                clearScalar('evidence_explanation');
            }
        }

        function payload() {
            return {
                approval_request_id:
                    scalarValue('approval_request_id'),
                legacy_approval_date:
                    scalarValue('legacy_approval_date'),
                requester_name:
                    scalarValue('requester_name'),
                requester_email:
                    scalarValue('requester_email'),
                requester_mobile:
                    scalarValue('requester_mobile'),
                requester_type:
                    scalarValue('requester_type'),
                requester_type_other:
                    scalarValue('requester_type_other'),
                requester_position:
                    scalarValue('requester_position'),
                requester_entity:
                    scalarValue('requester_entity'),
                requester_department:
                    scalarValue('requester_department'),
                initiative_number:
                    scalarValue('initiative_number'),
                relationship_type: scalarValue('relationship_type'),
                related_agreement:
                    scalarValue('relationship_type') === 'LINKED_AGREEMENTS',
                related_agreement_ids:
                    multiValues('related_agreement_ids')
                        .map((value) => Number(value))
                        .filter((value) => Number.isInteger(value) && value > 0),
                related_agreement_id:
                    multiValues('related_agreement_ids').length > 0
                        ? Number(multiValues('related_agreement_ids')[0])
                        : null,
                relation_notes:
                    scalarValue('relation_notes'),
                title: scalarValue('title'),
                initiative_type:
                    scalarValue('initiative_type'),
                initiative_type_other:
                    scalarValue('initiative_type_other'),
                secondary_initiative_types: [],
                entity: scalarValue('entity'),
                external_entities:
                    scalarValue('external_entities'),
                has_external_partner:
                    scalarValue('relationship_type') === 'EXTERNAL_WITHOUT_AGREEMENT',
                external_partner_name:
                    scalarValue('external_partner_name'),
                external_partner_country:
                    scalarValue('external_partner_country'),
                external_partner_role:
                    scalarValue('external_partner_role'),
                provider_categories:
                    multiValues('provider_categories'),
                department_unit:
                    scalarValue('department_unit'),
                department_unit_other:
                    scalarValue('department_unit_other'),
                department_within_college:
                    scalarValue('department_within_college'),
                contributors: contributors(),
                implementation_participants:
                    implementationParticipants(),
                start_date: scalarValue('start_date'),
                end_date: scalarValue('end_date'),
                activity_status:
                    scalarValue('activity_status'),
                activity_recurrence:
                    scalarValue('activity_recurrence'),
                academic_year:
                    scalarValue('academic_year'),
                duration_hours:
                    numberOrNull('duration_hours'),
                location_mode:
                    scalarValue('location_mode'),
                implementation_scope_other:
                    scalarValue('implementation_scope_other'),
                proposed_venue:
                    scalarValue('proposed_venue'),
                proposed_venue_place_id:
                    scalarValue('proposed_venue_place_id'),
                proposed_venue_name:
                    scalarValue('proposed_venue_name'),
                proposed_venue_latitude:
                    scalarValue('proposed_venue_latitude'),
                proposed_venue_longitude:
                    scalarValue('proposed_venue_longitude'),
                proposed_venue_country_code:
                    scalarValue('proposed_venue_country_code'),
                implementation_country:
                    scalarValue('implementation_country'),
                online_platform_name:
                    scalarValue('online_platform_name'),
                outside_location:
                    scalarValue('outside_location'),
                international_participation:
                    radioBoolean('international_participation'),
                international_countries:
                    textLines('international_countries_text'),
                international_participants:
                    numberOrNull('international_participants'),
                international_partner:
                    scalarValue('international_partner'),
                international_partner_type:
                    scalarValue('international_partner_type'),
                international_collaboration_nature:
                    multiValues(
                        'international_collaboration_nature'
                    ),
                initiative_descriptors:
                    multiValues('initiative_descriptors'),
                description: scalarValue('description'),
                objectives: scalarValue('objectives'),
                expected_impact:
                    scalarValue('expected_impact'),
                beneficiaries:
                    scalarValue('beneficiaries'),
                target_groups:
                    multiValues('target_groups'),
                target_group_other:
                    scalarValue('target_group_other'),
                school_names: scalarValue('school_names'),
                expected_participants:
                    numberOrNull('expected_participants'),
                male_count: numberOrNull('male_count'),
                female_count: numberOrNull('female_count'),
                unspecified_count:
                    numberOrNull('unspecified_count'),
                beneficiary_count_basis:
                    scalarValue('beneficiary_count_basis'),
                youth_18_35:
                    scalarValue('youth_18_35'),
                resources_mobilized_options:
                    multiValues('resources_mobilized_options'),
                resources_mobilized_other:
                    scalarValue('resources_mobilized_other'),
                expected_budget:
                    numberOrNull('expected_budget'),
                internal_funding_bhd:
                    numberOrNull('internal_funding_bhd'),
                in_kind_support_bhd:
                    numberOrNull('in_kind_support_bhd'),
                external_funding_amount:
                    numberOrNull('external_funding_amount'),
                external_funding_currency:
                    scalarValue('external_funding_currency'),
                funding_entity:
                    scalarValue('funding_entity'),
                training_hours:
                    numberOrNull('training_hours'),
                trainees_count:
                    numberOrNull('trainees_count'),
                volunteers_count:
                    numberOrNull('volunteers_count'),
                volunteer_hours_per_person:
                    numberOrNull('volunteer_hours_per_person'),
                direct_outputs:
                    scalarValue('direct_outputs'),
                societal_impact:
                    scalarValue('societal_impact'),
                ranking_framework:
                    scalarValue('ranking_framework'),
                the_areas: multiValues('the_areas'),
                qs_categories:
                    multiValues('qs_categories'),
                environmental_impact_types:
                    multiValues('environmental_impact_types'),
                environmental_before_value:
                    numberOrNull('environmental_before_value'),
                environmental_after_value:
                    numberOrNull('environmental_after_value'),
                environmental_improvement_value:
                    numberOrNull('environmental_improvement_value'),
                environmental_unit:
                    scalarValue('environmental_unit'),
                environmental_measurement_basis:
                    scalarValue(
                        'environmental_measurement_basis'
                    ),
                environmental_data_source:
                    scalarValue('environmental_data_source'),
                environmental_impact:
                    scalarValue('environmental_impact'),
                supports_sdg:
                    radioBoolean('supports_sdg'),
                primary_sdg:
                    scalarValue('primary_sdg'),
                secondary_sdgs:
                    multiValues('secondary_sdgs'),
                needs_media_support:
                    scalarValue('needs_media_support'),
                publication_status:
                    scalarValue('publication_status'),
                coverage_status: scalarValue('coverage_status'),
                media_coverage_type:
                    scalarValue('media_coverage_type'),
                media_outlet_name:
                    scalarValue('media_outlet_name'),
                media_headline:
                    scalarValue('media_headline'),
                media_publication_date:
                    scalarValue('media_publication_date'),
                news_link: scalarValue('news_link'),
                coverage_other_medium_kind:
                    scalarValue('coverage_other_medium_kind'),
                coverage_other_medium_other:
                    scalarValue('coverage_other_medium_other'),
                coverage_other_outlet_name:
                    scalarValue('coverage_other_outlet_name'),
                coverage_other_reach:
                    numberOrNull('coverage_other_reach'),
                coverage_other_url:
                    scalarValue('coverage_other_url'),
                tv_channel: scalarValue('tv_channel'),
                tv_program: scalarValue('tv_program'),
                tv_interview_topic:
                    scalarValue('tv_interview_topic'),
                tv_interviewer:
                    scalarValue('tv_interviewer'),
                tv_uob_representatives:
                    scalarValue('tv_uob_representatives'),
                tv_interview_date:
                    scalarValue('tv_interview_date'),
                tv_duration_minutes:
                    numberOrNull('tv_duration_minutes'),
                tv_broadcast_status:
                    scalarValue('tv_broadcast_status'),
                tv_broadcast_scope:
                    scalarValue('tv_broadcast_scope'),
                tv_interview_language:
                    scalarValue('tv_interview_language'),
                tv_interview_link:
                    scalarValue('tv_interview_link'),
                tv_interview_highlights:
                    scalarValue('tv_interview_highlights'),
                evidence_types:
                    multiValues('evidence_types'),
                evidence_document_type:
                    scalarValue('evidence_document_type'),
                evidence_date:
                    scalarValue('evidence_date'),
                evidence_owner:
                    scalarValue('evidence_owner'),
                evidence_public_access:
                    scalarValue('evidence_public_access'),
                evidence_urls:
                    textLines('evidence_urls_text'),
                evidence_explanation:
                    scalarValue('evidence_explanation'),
                public_sharing:
                    scalarValue('public_sharing'),
                notes_entity:
                    scalarValue('notes_entity'),
                declaration_confirmed:
                    Boolean(
                        field('declaration_confirmed')?.checked
                    ),
                excluded_request_attachment_ids:
                    Array.from(
                        excludedRequestAttachmentIds
                    )
            };
        }

        function fillForm(data) {
            const scalarFields = [
                'approval_request_id',
                'legacy_approval_date',
                'requester_name',
                'requester_email',
                'requester_mobile',
                'requester_type',
                'requester_type_other',
                'requester_position',
                'requester_entity',
                'requester_department',
                'initiative_number',
                'relationship_type',
                'relation_notes',
                'title',
                'initiative_type',
                'initiative_type_other',
                'entity',
                'external_entities',
                'external_partner_name',
                'external_partner_country',
                'external_partner_role',
                'department_unit',
                'department_unit_other',
                'department_within_college',
                'start_date',
                'end_date',
                'activity_status',
                'activity_recurrence',
                'academic_year',
                'duration_hours',
                'location_mode',
                'implementation_scope_other',
                'proposed_venue',
                'proposed_venue_place_id',
                'proposed_venue_name',
                'proposed_venue_latitude',
                'proposed_venue_longitude',
                'proposed_venue_country_code',
                'implementation_country',
                'online_platform_name',
                'outside_location',
                'international_participants',
                'international_partner',
                'international_partner_type',
                'description',
                'objectives',
                'expected_impact',
                'beneficiaries',
                'target_group_other',
                'school_names',
                'expected_participants',
                'male_count',
                'female_count',
                'unspecified_count',
                'beneficiary_count_basis',
                'youth_18_35',
                'resources_mobilized_other',
                'expected_budget',
                'internal_funding_bhd',
                'in_kind_support_bhd',
                'external_funding_amount',
                'external_funding_currency',
                'funding_entity',
                'training_hours',
                'trainees_count',
                'volunteers_count',
                'volunteer_hours_per_person',
                'direct_outputs',
                'societal_impact',
                'ranking_framework',
                'environmental_before_value',
                'environmental_after_value',
                'environmental_improvement_value',
                'environmental_unit',
                'environmental_measurement_basis',
                'environmental_data_source',
                'environmental_impact',
                'primary_sdg',
                'needs_media_support',
                'coverage_status',
                'publication_status',
                'media_coverage_type',
                'media_outlet_name',
                'media_headline',
                'media_publication_date',
                'news_link',
                'coverage_other_medium_kind',
                'coverage_other_medium_other',
                'coverage_other_outlet_name',
                'coverage_other_reach',
                'coverage_other_url',
                'tv_channel',
                'tv_program',
                'tv_interview_topic',
                'tv_interviewer',
                'tv_uob_representatives',
                'tv_interview_date',
                'tv_duration_minutes',
                'tv_broadcast_status',
                'tv_broadcast_scope',
                'tv_interview_language',
                'tv_interview_link',
                'tv_interview_highlights',
                'evidence_document_type',
                'evidence_date',
                'evidence_owner',
                'evidence_public_access',
                'evidence_explanation',
                'public_sharing',
                'notes_entity'
            ];

            scalarFields.forEach((name) => {
                setScalar(name, data?.[name]);
            });

            [
                'related_agreement_ids',
                'provider_categories',
                'international_collaboration_nature',
                'initiative_descriptors',
                'target_groups',
                'resources_mobilized_options',
                'the_areas',
                'qs_categories',
                'environmental_impact_types',
                'secondary_sdgs',
                'evidence_types'
            ].forEach((name) => {
                setMulti(name, data?.[name]);
            });

            const savedRelationshipType = data?.relationship_type || (
                data?.related_agreement === true
                    ? 'LINKED_AGREEMENTS'
                    : (
                        data?.has_external_partner === true
                            ? 'EXTERNAL_WITHOUT_AGREEMENT'
                            : (existingMode ? '' : 'NO_EXTERNAL_PARTY')
                    )
            );
            setScalar('relationship_type', savedRelationshipType);
            setRadioBoolean(
                'international_participation',
                data?.international_participation
            );
            setRadioBoolean(
                'supports_sdg',
                data?.supports_sdg
            );

            setScalar(
                'international_countries_text',
                Array.isArray(data?.international_countries)
                    ? data.international_countries.join(', ')
                    : ''
            );
            setScalar(
                'evidence_urls_text',
                Array.isArray(data?.evidence_urls)
                    ? data.evidence_urls.join('\n')
                    : ''
            );

            field('declaration_confirmed').checked =
                Boolean(data?.declaration_confirmed);

            excludedRequestAttachmentIds.clear();
            (data?.excluded_request_attachment_ids || [])
                .forEach((attachmentId) => {
                    excludedRequestAttachmentIds.add(
                        Number(attachmentId)
                    );
                });

            peopleContainer.replaceChildren();
            (data?.contributors || []).forEach(addPerson);

            if (!peopleContainer.children.length) addPerson();
            if (participantsContainer) {
                participantsContainer.replaceChildren();
                (data?.implementation_participants || []).forEach(addParticipant);
                updateParticipantNumbers();
            }

            if (!scalarValue('coverage_status')) {
                setScalar(
                    'coverage_status',
                    scalarValue('media_coverage_type') ? 'PUBLISHED' : 'NONE'
                );
            }

            syncConditionalFields();
            updateReview();
            updateStepState();
        }

        function formatBytes(bytes) {
            const size = Number(bytes) || 0;

            if (size < 1024) {
                return `${size} B`;
            }
            if (size < 1024 * 1024) {
                return `${(size / 1024).toFixed(1)} KB`;
            }
            return `${(size / (1024 * 1024)).toFixed(1)} MB`;
        }

        function renderRequestAttachments() {
            const container = document.querySelector(
                '[data-final-request-attachments]'
            );
            container.replaceChildren();

            if (!requestAttachments.length) {
                container.textContent =
                    'No files were attached to the request.';
                return;
            }

            requestAttachments.forEach((attachment) => {
                const item = document.createElement('label');
                item.className = 'initiative-final-attachment';
                item.innerHTML = `
                    <input type="checkbox">
                    <span>
                        <strong></strong>
                        <small></small>
                    </span>
                    <button type="button" class="btn btn-sm btn-outline-primary">
                        Download
                    </button>
                `;
                const include = item.querySelector('input');
                const attachmentId =
                    Number(attachment.attachment_id);
                include.checked =
                    !excludedRequestAttachmentIds.has(
                        attachmentId
                    );
                include.addEventListener('change', () => {
                    if (include.checked) {
                        excludedRequestAttachmentIds.delete(
                            attachmentId
                        );
                    } else {
                        excludedRequestAttachmentIds.add(
                            attachmentId
                        );
                    }
                });
                item.querySelector('strong').textContent =
                    attachment.original_name;
                item.querySelector('small').textContent =
                    `${String(
                        attachment.file_extension || ''
                    ).toUpperCase()} · ${
                        formatBytes(
                            attachment.file_size_bytes
                        )
                    }`;
                item.querySelector('button')
                    .addEventListener('click', (event) => {
                        event.preventDefault();
                        AgreementApi.download(
                            `/initiative-requests/${
                                encodeURIComponent(id)
                            }/attachments/${
                                encodeURIComponent(
                                    attachment.attachment_id
                                )
                            }/download`,
                            attachment.original_name
                        );
                    });
                container.append(item);
            });
        }

        function renderConversionAttachments() {
            const container = document.querySelector(
                '[data-final-conversion-attachments]'
            );
            container.replaceChildren();

            if (!conversionAttachments.length) {
                return;
            }

            const heading = document.createElement('p');
            heading.className =
                'small fw-bold text-secondary mb-2';
            heading.textContent =
                'Evidence added while finalizing';
            container.append(heading);

            conversionAttachments.forEach((attachment) => {
                const item = document.createElement('div');
                item.className = 'initiative-final-attachment';
                item.innerHTML = `
                    <span>
                        <strong></strong>
                        <small></small>
                    </span>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-outline-primary" data-download>
                            Download
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger" data-delete>
                            Remove
                        </button>
                    </div>
                `;
                item.querySelector('strong').textContent =
                    attachment.original_name;
                item.querySelector('small').textContent =
                    `${String(
                        attachment.file_extension || ''
                    ).toUpperCase()} · ${
                        formatBytes(
                            attachment.file_size_bytes
                        )
                    }`;
                item.querySelector('[data-download]')
                    .addEventListener('click', () => {
                        AgreementApi.download(
                            existingMode
                                ? `/initiative-requests/legacy-initiative/${
                                    encodeURIComponent(existingDraftId)
                                }/attachments/${
                                    encodeURIComponent(
                                        attachment.attachment_id
                                    )
                                }/download`
                                : `/initiative-requests/${
                                    encodeURIComponent(id)
                                }/conversion/attachments/${
                                    encodeURIComponent(
                                        attachment.attachment_id
                                    )
                                }/download`,
                            attachment.original_name
                        );
                    });
                item.querySelector('[data-delete]')
                    .addEventListener('click', async () => {
                        if (!window.confirm(
                            `Remove ${attachment.original_name}?`
                        )) {
                            return;
                        }

                        await AgreementApi.request(
                            existingMode
                                ? `/initiative-requests/legacy-initiative/${
                                    encodeURIComponent(existingDraftId)
                                }/attachments/${
                                    encodeURIComponent(
                                        attachment.attachment_id
                                    )
                                }`
                                : `/initiative-requests/${
                                    encodeURIComponent(id)
                                }/conversion/attachments/${
                                    encodeURIComponent(
                                        attachment.attachment_id
                                    )
                                }`,
                            { method: 'DELETE' }
                        );
                        conversionAttachments =
                            conversionAttachments.filter(
                                (item) =>
                                    Number(item.attachment_id)
                                    !== Number(
                                        attachment.attachment_id
                                    )
                            );
                        renderConversionAttachments();
                    });
                container.append(item);
            });
        }

        function renderQueuedFiles() {
            const container = document.querySelector(
                '[data-final-queued-files]'
            );
            container.replaceChildren();

            queuedFiles.forEach((file, index) => {
                const item = document.createElement('div');
                item.className = 'initiative-final-queued-file';
                item.innerHTML =
                    '<span></span><button type="button">Remove</button>';
                item.querySelector('span').textContent =
                    `${file.name} · ${formatBytes(file.size)}`;
                item.querySelector('button')
                    .addEventListener('click', () => {
                        queuedFiles.splice(index, 1);
                        renderQueuedFiles();
                    });
                container.append(item);
            });
        }

        async function uploadQueuedFiles() {
            if (!queuedFiles.length) {
                return;
            }

            const body = new FormData();
            queuedFiles.forEach((file) => {
                body.append('attachments[]', file, file.name);
            });

            if (existingMode && !existingDraftId) {
                await persistExistingDraft();
            }

            conversionAttachments = await AgreementApi.request(
                existingMode
                    ? `/initiative-requests/legacy-initiative/${
                        encodeURIComponent(existingDraftId)
                    }/attachments`
                    : `/initiative-requests/${
                        encodeURIComponent(id)
                    }/conversion/attachments`,
                {
                    method: 'POST',
                    body
                }
            );
            queuedFiles = [];
            fileInput.value = '';
            renderQueuedFiles();
            renderConversionAttachments();
        }

        function validateFinalForm() {
            syncConditionalFields();

            if (
                scalarValue('start_date')
                && scalarValue('end_date')
                && scalarValue('end_date')
                    < scalarValue('start_date')
            ) {
                field('end_date').setCustomValidity(
                    'The end date cannot be before the start date.'
                );
            } else {
                field('end_date').setCustomValidity('');
            }

            const invalid = form.querySelector(':invalid');

            if (!invalid) {
                return true;
            }

            const section = invalid.closest('[data-final-step]');
            const index = stepSections.indexOf(section);

            if (index >= 0) {
                showStep(index);
            }

            window.setTimeout(() => {
                if (
                    markEnhancedMultiInvalid(
                        invalid,
                        invalid.validationMessage
                            || 'Select at least one option.'
                    )
                ) {
                    return;
                }

                invalid.reportValidity();
                invalid.focus();
            }, 120);
            return false;
        }

        function setBusy(isBusy, activeButton = null) {
            form.querySelectorAll(
                'button, input, select, textarea'
            ).forEach((control) => {
                control.disabled = isBusy;
            });

            if (activeButton && isBusy) {
                activeButton.dataset.originalText =
                    activeButton.textContent;
            }
        }

        function restoreControls() {
            form.querySelectorAll(
                'button, input, select, textarea'
            ).forEach((control) => {
                control.disabled = false;
            });

            [saveButton, finalizeButton].forEach((button) => {
                if (button.dataset.originalText) {
                    button.textContent =
                        button.dataset.originalText;
                    delete button.dataset.originalText;
                }
            });

            updateStepState();
        }

        async function saveDraft() {
            clearError();
            setBusy(true, saveButton);
            saveButton.textContent = 'Saving…';

            try {
                await uploadQueuedFiles();
                const saved = existingMode
                    ? await persistExistingDraft()
                    : await AgreementApi.request(
                        `/initiative-requests/${
                            encodeURIComponent(id)
                        }/conversion/draft`,
                        {
                            method: 'POST',
                            body: AgreementApi.jsonBody(payload())
                        }
                    );
                document.querySelector(
                    '[data-final-state]'
                ).textContent = 'Draft saved';
                document.querySelector(
                    '[data-final-state]'
                ).title = saved.conversion_draft_updated_at
                    ? AgreementApi.formatDate(
                        saved.conversion_draft_updated_at
                    )
                    : '';
                saveButton.textContent = 'Saved';
                window.setTimeout(restoreControls, 700);
            } catch (error) {
                showError(
                    error,
                    'The final Initiative draft could not be saved.'
                );
                restoreControls();
            }
        }

        applyReferenceFinalFormLayout();
        initializeFinalWorkspaceLayout();

        stepButtons.forEach((button, index) => {
            button.addEventListener('click', (event) => {
                event.preventDefault();

                showStep(index);

                document.getElementById(
                    'workspaceSidebar'
                )?.classList.remove('is-open');
            });
        });

        previousButton.addEventListener('click', () => {
            showStep(currentStep - 1);
        });

        nextButton.addEventListener('click', () => {
            showStep(currentStep + 1);
        });

        document.querySelector('[data-add-final-contributor]')
            ?.addEventListener('click', () => addPerson());
        document.querySelector('[data-add-final-participant]')
            ?.addEventListener('click', () => addParticipant());
        printButton?.addEventListener('click', () => {
            updateReview();
            window.print();
        });

        fileInput.addEventListener('change', () => {
            const selected = Array.from(fileInput.files || []);
            const total =
                requestAttachments.length
                + conversionAttachments.length
                + queuedFiles.length
                + selected.length;

            if (total > 10) {
                window.alert(
                    'A maximum of 10 evidence files is allowed.'
                );
                fileInput.value = '';
                return;
            }

            selected.forEach((file) => {
                if (file.size > 20 * 1024 * 1024) {
                    window.alert(
                        `${file.name} is larger than 20 MB.`
                    );
                    return;
                }
                queuedFiles.push(file);
            });
            fileInput.value = '';
            renderQueuedFiles();
        });

        form.addEventListener('input', () => {
            syncConditionalFields();
            updateReview();
            updateStepState();
        });
        form.addEventListener('change', () => {
            syncConditionalFields();
            updateReview();
            updateStepState();
        });

        saveButton.addEventListener('click', saveDraft);

        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            if (!validateFinalForm()) {
                return;
            }

            if (!window.confirm(
                existingMode
                    ? 'Register this existing Initiative as an official University record?'
                    : 'Complete this conversion and create the official Initiative record?'
            )) {
                return;
            }

            clearError();
            setBusy(true, finalizeButton);
            finalizeButton.textContent = 'Finalizing…';

            try {
                await uploadQueuedFiles();
                const result = await AgreementApi.request(
                    existingMode
                        ? '/initiative-requests/legacy-initiative/finalize'
                        : `/initiative-requests/${
                            encodeURIComponent(id)
                        }/conversion/finalize`,
                    {
                        method: 'POST',
                        body: AgreementApi.jsonBody(
                            existingMode
                                ? {
                                    draft_id: existingDraftId,
                                    form_data: payload()
                                }
                                : payload()
                        )
                    }
                );

                window.location.assign(
                    result.initiative_url
                    || `initiative-view.php?id=${
                        encodeURIComponent(
                            result.initiative_id
                        )
                    }`
                );
            } catch (error) {
                showError(
                    error,
                    existingMode
                    ? 'The existing Initiative could not be registered.'
                    : 'The approved Initiative could not be finalized.'
                );
                restoreControls();
            }
        });

        try {
            await AgreementApi.requireSession();

            const [data, agreementPayload] = await Promise.all([
                AgreementApi.request(
                    existingMode
                        ? `/initiative-requests/legacy-initiative${
                            existingDraftId
                                ? `?draft_id=${encodeURIComponent(existingDraftId)}`
                                : ''
                        }`
                        : `/initiative-requests/${
                            encodeURIComponent(id)
                        }/conversion`
                ),
                AgreementApi.request('/agreements')
            ]);

            agreements = Array.isArray(agreementPayload)
                ? agreementPayload
                : (agreementPayload.items || []);

            const agreementSelect =
                field('related_agreement_ids');
            agreements.forEach((agreement) => {
                if (
                    !['ACTIVE', 'APPROVED'].includes(
                        String(agreement.status || '')
                    )
                ) {
                    return;
                }

                const option = document.createElement('option');
                option.value = agreement.agreement_id;
                const partnerName = String(
                    agreement.primary_partner_name
                    || agreement.partner_name
                    || agreement.partner_organization_name
                    || ''
                ).trim();
                option.textContent =
                    `${agreement.agreement_code || ''} — ${
                        agreement.title
                    }${partnerName ? ` · ${partnerName}` : ''}`;
                agreementSelect.append(option);
            });

            initializeEnhancedMultiControls(form, [
                'related_agreement_ids',
                'provider_categories',
                'international_collaboration_nature',
                'initiative_descriptors',
                'target_groups',
                'resources_mobilized_options',
                'the_areas',
                'qs_categories',
                'environmental_impact_types',
                'secondary_sdgs',
                'evidence_types'
            ]);

            document.querySelector(
                '[data-final-source-title]'
            ).textContent =
                data.source_title || 'Approved Initiative request';
            document.querySelector(
                '[data-final-request-code]'
            ).textContent =
                data.request_code || 'Approved request';
            existingDraftId = existingMode
                ? Number(data.conversion_draft_id) || existingDraftId
                : existingDraftId;
            updateExistingDraftUrl();

            document.querySelector(
                '[data-final-state]'
            ).textContent = data.conversion_draft_id
                ? 'Draft saved'
                : 'Ready';

            requestAttachments = Array.isArray(
                data.request_attachments
            )
                ? data.request_attachments
                : [];
            conversionAttachments = Array.isArray(
                data.conversion_attachments
            )
                ? data.conversion_attachments
                : [];

            fillForm(data.form_data || {});
            renderRequestAttachments();
            renderConversionAttachments();
            renderQueuedFiles();
            showStep(0, false);
            content.classList.remove('d-none');
        } catch (error) {
            showError(
                error,
                existingMode
                ? 'The existing Initiative form could not be prepared.'
                : 'The approved Initiative request could not be prepared.'
            );
        } finally {
            loading.classList.add('d-none');
        }
    }

    async function initializeDetail() {
        const id = requestId;
        const loading = document.querySelector('[data-loading]');

        if (!id) {
            showError(
                null,
                'The Initiative request ID is missing.'
            );
            loading.classList.add('d-none');
            return;
        }

        const set = (selector, value) => {
            const element = document.querySelector(selector);

            if (element) {
                element.textContent = text(value);
            }
        };

        function validCoordinate(
            value,
            minimum,
            maximum
        ) {
            const coordinate = Number(value);

            return Number.isFinite(coordinate)
                && coordinate >= minimum
                && coordinate <= maximum;
        }

        function googleMapsLocationUrl(data) {
            const latitude = Number(
                data.proposed_venue_latitude
            );
            const longitude = Number(
                data.proposed_venue_longitude
            );
            const placeId = String(
                data.proposed_venue_place_id || ''
            ).trim();
            const venue = String(
                data.proposed_venue || ''
            ).trim();
            const hasCoordinates = (
                validCoordinate(latitude, -90, 90)
                && validCoordinate(longitude, -180, 180)
            );

            /*
             * Do not create a potentially inaccurate link for manual or
             * legacy text-only locations. A reusable link requires either
             * saved coordinates or the Google Place ID.
             */
            if (!hasCoordinates && placeId === '') {
                return '';
            }

            const query = hasCoordinates
                ? `${latitude},${longitude}`
                : venue;

            if (query === '') {
                return '';
            }

            const url = new URL(
                'https://www.google.com/maps/search/'
            );
            url.searchParams.set('api', '1');
            url.searchParams.set('query', query);

            if (placeId !== '') {
                url.searchParams.set(
                    'query_place_id',
                    placeId
                );
            }

            return url.toString();
        }

        function renderGoogleMapsLocationLink(data) {
            const link = document.querySelector(
                '[data-proposed-venue-map-link]'
            );

            if (!link) {
                return;
            }

            const locationUrl = googleMapsLocationUrl(data);

            if (locationUrl === '') {
                link.href = '#';
                link.classList.add('d-none');
                link.setAttribute('aria-hidden', 'true');
                return;
            }

            link.href = locationUrl;
            link.classList.remove('d-none');
            link.removeAttribute('aria-hidden');
        }

        const requesterTypeLabels = {
            FACULTY: 'Academic Staff',
            STAFF: 'Administrative Staff',
            STUDENT: 'Student',
            STUDENT_GROUP: 'Student Group',
            OTHER: 'Other'
        };
        const initiativeTypeLabels = {
            WORKSHOP_TRAINING: 'Workshop / Training',
            LECTURE_SEMINAR: 'Lecture / Seminar',
            STUDENT_INITIATIVE: 'Student Initiative',
            COMMUNITY_ENGAGEMENT: 'Community Engagement',
            VOLUNTEERING: 'Volunteering Program',
            AWARENESS_CAMPAIGN: 'Awareness Campaign',
            RESEARCH: 'Research or Academic Collaboration',
            CONSULTATION: 'Consultation / Advisory Role',
            PARTNERSHIP: 'Community Partnership',
            SUSTAINABILITY: 'Sustainability Activity',
            ACADEMIC: 'Academic',
            INNOVATION: 'Innovation',
            OTHER: 'Other'
        };
        const targetGroupLabels = {
            UNIVERSITY_STUDENTS: 'University Students',
            SCHOOL_STUDENTS: 'School Students',
            FACULTY_STAFF: 'Faculty and Staff',
            PROFESSIONALS: 'Professionals',
            ALUMNI: 'Alumni',
            LOCAL_COMMUNITY: 'Local Community',
            VULNERABLE_GROUPS: 'Vulnerable Groups',
            OPEN_PUBLIC: 'Open to the Public',
            OTHER: 'Other'
        };
        const scopeLabels = {
            WITHIN_UOB: 'Within the University of Bahrain',
            OUTSIDE_UOB: 'Outside the University Of Bahrain',
            VIRTUAL: 'Online / Virtual',
            HYBRID: 'Hybrid: In-Person and Online',
            OTHER: 'Other'
        };
        const resourceLabels = {
            VENUE: 'Venue',
            BUDGET: 'Budget or Funding',
            MEDIA: 'Media Support',
            TRANSPORT: 'Transportation',
            EQUIPMENT: 'Equipment or Technical Support',
            NONE: 'No Additional Requirements',
            OTHER: 'Other'
        };
        const sdgLabels = {
            SDG_1: 'SDG 1 — No Poverty',
            SDG_2: 'SDG 2 — Zero Hunger',
            SDG_3: 'SDG 3 — Good Health and Well-being',
            SDG_4: 'SDG 4 — Quality Education',
            SDG_5: 'SDG 5 — Gender Equality',
            SDG_6: 'SDG 6 — Clean Water and Sanitation',
            SDG_7: 'SDG 7 — Affordable and Clean Energy',
            SDG_8: 'SDG 8 — Decent Work and Economic Growth',
            SDG_9: 'SDG 9 — Industry, Innovation and Infrastructure',
            SDG_10: 'SDG 10 — Reduced Inequalities',
            SDG_11: 'SDG 11 — Sustainable Cities and Communities',
            SDG_12: 'SDG 12 — Responsible Consumption and Production',
            SDG_13: 'SDG 13 — Climate Action',
            SDG_14: 'SDG 14 — Life Below Water',
            SDG_15: 'SDG 15 — Life on Land',
            SDG_16: 'SDG 16 — Peace, Justice and Strong Institutions',
            SDG_17: 'SDG 17 — Partnerships for the Goals'
        };

        function labelCode(value, labels, otherValue = null) {
            const code = String(value || '').toUpperCase();

            if (code === 'OTHER' && String(otherValue || '').trim() !== '') {
                return `Other — ${otherValue}`;
            }

            return labels[code]
                || String(value || '').replaceAll('_', ' ')
                || '—';
        }

        function labelCodes(values, labels, otherValue = null) {
            if (!Array.isArray(values) || values.length === 0) {
                return '—';
            }

            return values.map((value) =>
                labelCode(value, labels, otherValue)
            ).join(', ');
        }

        function yesNo(value) {
            if (value === null || value === undefined || value === '') {
                return '—';
            }

            return booleanValue(value) ? 'Yes' : 'No';
        }

        function formatBudget(value) {
            if (value === null || value === undefined || value === '') {
                return '—';
            }

            const number = Number(value);
            return Number.isFinite(number)
                ? number.toLocaleString(
                    undefined,
                    {
                        minimumFractionDigits: 3,
                        maximumFractionDigits: 3
                    }
                )
                : text(value);
        }

        function formatFileSize(bytes) {
            const size = Number(bytes);

            if (!Number.isFinite(size) || size < 1) {
                return '—';
            }

            if (size >= 1024 * 1024) {
                return `${(size / (1024 * 1024)).toFixed(1)} MB`;
            }

            return `${Math.max(1, Math.round(size / 1024))} KB`;
        }

        async function downloadAttachment(attachment, button) {
            button.disabled = true;
            const originalText = button.textContent;
            button.textContent = 'Downloading…';

            try {
                const headers = new Headers();
                headers.set('Accept', 'application/octet-stream');
                const tabSession = window.sessionStorage.getItem(
                    'uob-agreement-tab-session'
                );

                if (tabSession) {
                    headers.set('X-UOB-Tab-Session', tabSession);
                }

                const response = await fetch(
                    `${AgreementApi.apiBase}/initiative-requests/${
                        encodeURIComponent(id)
                    }/attachments/${
                        encodeURIComponent(attachment.attachment_id)
                    }/download`,
                    {
                        method: 'GET',
                        headers,
                        cache: 'no-store',
                        credentials: 'same-origin'
                    }
                );

                if (!response.ok) {
                    const payload = await response.json().catch(() => null);
                    throw new Error(
                        payload?.error
                        || 'The attachment could not be downloaded.'
                    );
                }

                const blob = await response.blob();
                const url = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = url;
                link.download = attachment.original_name
                    || 'initiative-attachment';
                document.body.append(link);
                link.click();
                link.remove();
                URL.revokeObjectURL(url);
            } catch (error) {
                showError(
                    error,
                    'The attachment could not be downloaded.'
                );
            } finally {
                button.disabled = false;
                button.textContent = originalText;
            }
        }

        function renderDetailAttachments(attachments) {
            const container = document.querySelector(
                '[data-detail-attachments]'
            );
            container.replaceChildren();

            if (!Array.isArray(attachments) || attachments.length === 0) {
                container.textContent = 'No supporting attachments.';
                return;
            }

            attachments.forEach((attachment) => {
                const item = document.createElement('div');
                item.className = 'initiative-detail-attachment';
                item.innerHTML = `
                    <div>
                        <strong></strong>
                        <small></small>
                    </div>
                    <button
                        class="btn btn-sm btn-outline-primary"
                        type="button"
                    >Download</button>
                `;
                item.querySelector('strong').textContent =
                    attachment.original_name;
                item.querySelector('small').textContent =
                    `${formatFileSize(
                        attachment.file_size_bytes
                    )} · ${text(
                        attachment.uploaded_by_name,
                        'University user'
                    )} · ${AgreementApi.formatDate(
                        attachment.uploaded_at
                    )}`;
                const button = item.querySelector('button');
                button.addEventListener(
                    'click',
                    () => downloadAttachment(attachment, button)
                );
                container.append(item);
            });
        }

        function renderDraftActions(data) {
            const actions = document.querySelector(
                '[data-request-actions]'
            );

            if (!data.can_edit_draft) {
                return;
            }

            const isRevision =
                data.status === 'REVISION_REQUIRED';

            const edit = document.createElement('a');
            edit.className = 'btn btn-outline-primary';
            edit.href =
                `initiative-workflow.php?view=form&id=${
                    encodeURIComponent(id)
                }`;
            edit.textContent = isRevision
                ? 'Revise request'
                : 'Edit draft';

            const submit = document.createElement('button');
            submit.className = 'btn btn-primary';
            submit.type = 'button';
            submit.textContent = isRevision
                ? 'Resubmit for approval'
                : 'Submit for approval';

            submit.addEventListener('click', async () => {
                const confirmed = window.confirm(
                    isRevision
                        ? 'Resubmit this revised Initiative request? The approval route will restart from its first required stage.'
                        : 'Submit this Initiative request for approval? You will no longer be able to edit it as a draft.'
                );

                if (!confirmed) return;

                clearError();
                submit.disabled = true;
                edit.classList.add('disabled');
                submit.textContent = isRevision
                    ? 'Resubmitting…'
                    : 'Submitting…';

                try {
                    await AgreementApi.request(
                        `/initiative-requests/${
                            encodeURIComponent(id)
                        }/submit`,
                        { method: 'POST' }
                    );

                    window.location.reload();
                } catch (error) {
                    showError(
                        error,
                        isRevision
                            ? 'The Initiative request could not be resubmitted.'
                            : 'The Initiative request could not be submitted.'
                    );
                    submit.disabled = false;
                    edit.classList.remove('disabled');
                    submit.textContent = isRevision
                        ? 'Resubmit for approval'
                        : 'Submit for approval';
                }
            });

            actions.append(edit, submit);
        }

        function renderConversionActions(data) {
            const actions = document.querySelector(
                '[data-request-actions]'
            );

            if (!actions) {
                return;
            }

            if (data.status === 'CONVERTED' && data.converted_initiative_id) {
                const open = document.createElement('a');
                open.className = 'btn btn-primary';
                open.href = `initiative-view.php?id=${
                    encodeURIComponent(data.converted_initiative_id)
                }`;
                open.textContent = 'Open Initiative';
                actions.append(open);
                return;
            }

            if (!booleanValue(data.can_convert)) {
                return;
            }

            const convert = document.createElement('a');
            convert.className = 'btn btn-primary';
            convert.href = `add-initiative-approved.php?request_id=${
                encodeURIComponent(id)
            }`;
            convert.textContent = booleanValue(
                data.conversion_draft_exists
            ) || data.status === 'CONVERTING'
                ? 'Continue conversion'
                : 'Convert to Initiative';
            actions.append(convert);
        }

        function renderDecisionPanel(data) {
            const panel = document.querySelector(
                '[data-decision-panel]'
            );

            if (!panel || !data.can_decide) {
                return;
            }

            const form = panel.querySelector(
                '[data-decision-form]'
            );
            const comment = panel.querySelector(
                '[data-decision-comment]'
            );
            const stage = (data.stages || []).find(
                (item) =>
                    Number(item.stage_order)
                        === Number(data.current_stage_order)
                    && Number(item.cycle_number)
                        === Number(data.revision_cycle)
            );

            panel.querySelector(
                '[data-decision-stage]'
            ).textContent = stage?.stage_label || 'the current stage';

            const context = panel.querySelector(
                '[data-decision-context]'
            );
            const badge = panel.querySelector(
                '.initiative-decision-badge'
            );

            if (data.decision_as_delegate) {
                const principal = text(
                    data.decision_principal_name,
                    'the assigned University leader'
                );

                context.replaceChildren();

                const lead = document.createTextNode(
                    'You are acting as an authorized office delegate on behalf of '
                );
                const principalName = document.createElement('strong');
                principalName.textContent = principal;
                const tail = document.createTextNode(
                    ` for ${stage?.stage_label || 'the current stage'}.`
                );

                context.append(lead, principalName, tail);
                context.classList.add('initiative-delegate-context');
                badge.textContent = 'Office decision';
            }

            function setBusy(isBusy, activeButton = null) {
                form.querySelectorAll('button').forEach((button) => {
                    button.disabled = isBusy;
                });

                if (activeButton && isBusy) {
                    activeButton.dataset.originalText =
                        activeButton.textContent;
                    activeButton.textContent = 'Processing…';
                }
            }

            async function decide(action, button) {
                const note = comment.value.trim();
                const needsReason = [
                    'REQUEST_REVISION',
                    'REJECT'
                ].includes(action);

                if (needsReason && note.length < 10) {
                    comment.setCustomValidity(
                        'Enter a clear reason of at least 10 characters.'
                    );
                    comment.reportValidity();
                    comment.focus();
                    return;
                }

                comment.setCustomValidity('');

                const labels = {
                    APPROVE: 'approve this Initiative request',
                    REQUEST_REVISION: 'return this Initiative request for revision',
                    REJECT: 'reject this Initiative request'
                };

                const delegationNote = data.decision_as_delegate
                    ? ` This decision will be recorded as acting on behalf of ${
                        text(
                            data.decision_principal_name,
                            'the assigned University leader'
                        )
                    }.`
                    : '';

                if (!window.confirm(
                    `Are you sure you want to ${labels[action]}?${delegationNote}`
                )) {
                    return;
                }

                clearError();
                setBusy(true, button);

                try {
                    await AgreementApi.request(
                        `/initiative-requests/${
                            encodeURIComponent(id)
                        }/decision`,
                        {
                            method: 'POST',
                            body: AgreementApi.jsonBody({
                                action,
                                comment: note || null
                            })
                        }
                    );

                    window.location.reload();
                } catch (error) {
                    showError(
                        error,
                        'The Initiative decision could not be recorded.'
                    );
                    form.querySelectorAll('button').forEach((item) => {
                        item.disabled = false;
                        if (item.dataset.originalText) {
                            item.textContent = item.dataset.originalText;
                            delete item.dataset.originalText;
                        }
                    });
                }
            }

            form.querySelectorAll(
                '[data-decision-action]'
            ).forEach((button) => {
                button.addEventListener('click', () => {
                    decide(button.dataset.decisionAction, button);
                });
            });

            comment.addEventListener('input', () => {
                comment.setCustomValidity('');
            });

            panel.classList.remove('d-none');
        }

        function renderAdminSkipPanel(data) {
            const panel = document.querySelector(
                '[data-admin-skip-panel]'
            );

            if (!panel || !data.can_admin_skip) {
                return;
            }

            const form = panel.querySelector(
                '[data-admin-skip-form]'
            );
            const reason = panel.querySelector(
                '[data-admin-skip-reason]'
            );
            const submit = panel.querySelector(
                '[data-admin-skip-submit]'
            );
            const stage = (data.stages || []).find(
                (item) =>
                    Number(item.stage_order)
                        === Number(data.current_stage_order)
                    && Number(item.cycle_number)
                        === Number(data.revision_cycle)
            );
            const stageLabel = stage?.stage_label
                || data.current_stage_label
                || 'the current approval stage';

            panel.querySelector(
                '[data-admin-skip-stage]'
            ).textContent = stageLabel;

            reason.addEventListener('input', () => {
                reason.setCustomValidity('');
            });

            form.addEventListener('submit', async (event) => {
                event.preventDefault();

                const skipReason = reason.value.trim();

                if (skipReason.length < 10) {
                    reason.setCustomValidity(
                        'Enter a clear administrative reason of at least 10 characters.'
                    );
                    reason.reportValidity();
                    reason.focus();
                    return;
                }

                reason.setCustomValidity('');

                if (!window.confirm(
                    `Skip ${stageLabel}? This bypasses the assigned approver, records your reason, notifies the affected users, and activates the next stage immediately.`
                )) {
                    return;
                }

                clearError();
                submit.disabled = true;
                reason.disabled = true;
                submit.dataset.originalText =
                    submit.textContent;
                submit.textContent = 'Skipping stage…';

                try {
                    await AgreementApi.request(
                        `/initiative-requests/${
                            encodeURIComponent(id)
                        }/admin-skip`,
                        {
                            method: 'POST',
                            body: AgreementApi.jsonBody({
                                reason: skipReason
                            })
                        }
                    );

                    window.location.reload();
                } catch (error) {
                    showError(
                        error,
                        'The approval stage could not be skipped.'
                    );
                    submit.disabled = false;
                    reason.disabled = false;
                    submit.textContent =
                        submit.dataset.originalText
                        || 'Skip stage and continue';
                }
            });

            panel.classList.remove('d-none');
        }

        function renderRevisionDiscussions(data) {
            const panel = document.querySelector(
                '[data-revision-discussions]'
            );
            const list = document.querySelector(
                '[data-revision-thread-list]'
            );
            const count = document.querySelector(
                '[data-revision-thread-count]'
            );
            const threads = Array.isArray(data.revision_threads)
                ? data.revision_threads
                : [];

            if (!panel || !list || threads.length === 0) {
                return;
            }

            list.replaceChildren();
            count.textContent = `${threads.length} discussion${
                threads.length === 1 ? '' : 's'
            }`;

            threads.forEach((thread) => {
                const card = document.createElement('article');
                const isOpen = String(thread.status || '').toUpperCase()
                    === 'OPEN';
                const cycleNumber = Number(thread.cycle_number || 0) + 1;

                card.className =
                    `initiative-revision-thread${isOpen ? ' is-open' : ' is-resolved'}`;
                card.innerHTML = `
                    <header class="initiative-revision-thread-header">
                        <div>
                            <div class="initiative-revision-thread-kicker"></div>
                            <h3></h3>
                            <p class="initiative-revision-thread-scope"></p>
                        </div>
                        <span class="initiative-revision-thread-status"></span>
                    </header>
                    <div class="initiative-revision-thread-meta"></div>
                    <div class="initiative-revision-comment-list"></div>
                `;

                card.querySelector(
                    '.initiative-revision-thread-kicker'
                ).textContent = `Approval cycle ${cycleNumber}`;
                card.querySelector('h3').textContent =
                    `Revision requested at ${text(
                        thread.requested_stage_label,
                        'approval stage'
                    )}`;
                card.querySelector(
                    '.initiative-revision-thread-scope'
                ).textContent = text(
                    thread.discussion_scope,
                    'Revision participants'
                );

                const status = card.querySelector(
                    '.initiative-revision-thread-status'
                );
                status.textContent = isOpen ? 'Open' : 'Resolved';
                status.classList.add(
                    isOpen ? 'is-open' : 'is-resolved'
                );

                const requestedBy = text(
                    thread.requested_by_name,
                    'University reviewer'
                );
                const counterpart = thread.counterpart_stage_label
                    ? `${thread.counterpart_stage_label} · ${text(
                        thread.counterpart_actor_name
                            || thread.counterpart_principal_name,
                        'previous reviewer'
                    )}`
                    : `Requester · ${text(
                        thread.requester_name,
                        'request owner'
                    )}`;
                const opened = AgreementApi.formatDate(
                    thread.created_at
                );
                const resolved = thread.resolved_at
                    ? ` · resolved ${AgreementApi.formatDate(
                        thread.resolved_at
                    )}`
                    : '';

                card.querySelector(
                    '.initiative-revision-thread-meta'
                ).textContent =
                    `Opened by ${requestedBy} · discussion counterpart: ${counterpart} · ${opened}${resolved}`;

                const comments = card.querySelector(
                    '.initiative-revision-comment-list'
                );
                const threadComments = Array.isArray(thread.comments)
                    ? thread.comments
                    : [];

                threadComments.forEach((comment) => {
                    const item = document.createElement('div');
                    const visibility = String(
                        comment.visibility || 'PUBLIC'
                    ).toUpperCase();
                    const isPrivate = visibility === 'PRIVATE';

                    item.className =
                        `initiative-revision-comment${isPrivate ? ' is-private' : ' is-public'}`;
                    item.innerHTML = `
                        <div class="initiative-revision-comment-heading">
                            <div>
                                <strong></strong>
                                <small></small>
                            </div>
                            <span class="initiative-revision-visibility"></span>
                        </div>
                        <p></p>
                    `;
                    item.querySelector('strong').textContent = text(
                        comment.author_name,
                        'University user'
                    );
                    item.querySelector('small').textContent =
                        AgreementApi.formatDate(comment.created_at);
                    item.querySelector('p').textContent =
                        comment.comment_text || '';

                    const badge = item.querySelector(
                        '.initiative-revision-visibility'
                    );
                    badge.textContent = isPrivate
                        ? 'Private'
                        : 'Public';
                    badge.classList.add(
                        isPrivate ? 'is-private' : 'is-public'
                    );
                    comments.append(item);
                });

                if (threadComments.length === 0) {
                    const empty = document.createElement('p');
                    empty.className =
                        'text-secondary small mb-0 initiative-revision-empty';
                    empty.textContent = thread.consolidated_note
                        || 'No visible discussion notes.';
                    comments.append(empty);
                }

                if (booleanValue(thread.can_comment) && isOpen) {
                    const form = document.createElement('form');
                    form.className = 'initiative-revision-reply-form';
                    form.noValidate = true;
                    form.innerHTML = `
                        <div class="initiative-revision-reply-grid">
                            <div>
                                <label class="form-label">Visibility</label>
                                <select class="form-select" data-revision-visibility>
                                    <option value="PUBLIC">Public note</option>
                                    <option value="PRIVATE">Private note</option>
                                </select>
                            </div>
                            <div class="initiative-revision-reply-message">
                                <label class="form-label">Add note</label>
                                <textarea
                                    class="form-control"
                                    rows="3"
                                    maxlength="4000"
                                    placeholder="Write a revision discussion note."
                                    data-revision-comment
                                    required
                                ></textarea>
                            </div>
                        </div>
                        <div class="initiative-revision-reply-footer">
                            <small data-revision-privacy-help>
                                Public notes are visible to everyone who can view this request.
                            </small>
                            <button class="btn btn-primary" type="submit">
                                Post note
                            </button>
                        </div>
                    `;

                    const visibilitySelect = form.querySelector(
                        '[data-revision-visibility]'
                    );
                    const commentInput = form.querySelector(
                        '[data-revision-comment]'
                    );
                    const help = form.querySelector(
                        '[data-revision-privacy-help]'
                    );
                    const submit = form.querySelector(
                        'button[type="submit"]'
                    );

                    visibilitySelect.addEventListener('change', () => {
                        help.textContent = visibilitySelect.value === 'PRIVATE'
                            ? 'Private notes are visible only to the requester, the involved approval stages, and System Administrators.'
                            : 'Public notes are visible to everyone who can view this request.';
                    });

                    form.addEventListener('submit', async (event) => {
                        event.preventDefault();
                        const commentText = commentInput.value.trim();

                        if (commentText.length < 2) {
                            commentInput.setCustomValidity(
                                'Enter a revision note of at least 2 characters.'
                            );
                            commentInput.reportValidity();
                            commentInput.focus();
                            return;
                        }

                        commentInput.setCustomValidity('');
                        clearError();

                        let selectedViewerIds = [];
                        if (visibilitySelect.value === 'PRIVATE') {
                            try {
                                const storedViewerIds = JSON.parse(
                                    form.dataset.revisionAudienceViewerIds || '[]'
                                );
                                selectedViewerIds = Array.isArray(storedViewerIds)
                                    ? storedViewerIds
                                        .map((value) => Number(value))
                                        .filter((value) => Number.isInteger(value) && value > 0)
                                    : [];
                            } catch (_) {
                                selectedViewerIds = [];
                            }

                            if (selectedViewerIds.length === 0) {
                                visibilitySelect.setCustomValidity(
                                    'Choose at least one person who can view this private note.'
                                );
                                visibilitySelect.reportValidity();
                                return;
                            }
                        }
                        visibilitySelect.setCustomValidity('');

                        submit.disabled = true;
                        visibilitySelect.disabled = true;
                        commentInput.disabled = true;
                        submit.textContent = 'Posting…';

                        try {
                            const selectedAudience =
                                visibilitySelect.value === 'PRIVATE'
                                && selectedViewerIds.length > 0;
                            const commentEndpoint = selectedAudience
                                ? 'selected-comments'
                                : 'comments';
                            const commentPayload = {
                                comment_text: commentText,
                                visibility: visibilitySelect.value
                            };

                            if (selectedAudience) {
                                commentPayload.audience_mode = 'SELECTED_USERS';
                                commentPayload.viewer_user_ids = selectedViewerIds;
                            }

                            await AgreementApi.request(
                                `/initiative-requests/${
                                    encodeURIComponent(id)
                                }/revision-discussions/${
                                    encodeURIComponent(
                                        thread.revision_thread_id
                                    )
                                }/${commentEndpoint}`,
                                {
                                    method: 'POST',
                                    body: AgreementApi.jsonBody(commentPayload)
                                }
                            );

                            window.location.hash =
                                'revision-discussions';
                            window.location.reload();
                        } catch (error) {
                            showError(
                                error,
                                'The revision note could not be posted.'
                            );
                            submit.disabled = false;
                            visibilitySelect.disabled = false;
                            commentInput.disabled = false;
                            submit.textContent = 'Post note';
                        }
                    });

                    card.append(form);
                }

                list.append(card);
            });

            panel.classList.remove('d-none');
        }

        function renderTimeline(data) {
            const timeline = document.querySelector(
                '[data-timeline]'
            );
            const waitingChip = document.querySelector(
                '[data-current-waiting]'
            );

            timeline.replaceChildren();
            timeline.classList.toggle(
                'initiative-draft-timeline',
                data.status === 'DRAFT'
            );
            timeline.classList.toggle(
                'initiative-cycle-history',
                data.status !== 'DRAFT'
            );

            if (data.status === 'DRAFT') {
                waitingChip.classList.add('d-none');

                const message = document.createElement('div');
                message.className = 'initiative-draft-state';
                message.innerHTML = `
                    <div class="initiative-draft-state-icon" aria-hidden="true">
                        <span>1</span>
                    </div>
                    <div class="initiative-draft-state-copy">
                        <span class="initiative-draft-state-label">Draft saved</span>
                        <h3>Ready to start the approval route</h3>
                        <p>
                            Review the request details, then select
                            <strong>Submit for approval</strong> when it is ready.
                        </p>
                    </div>
                `;
                timeline.append(message);
                return;
            }

            waitingChip.classList.toggle(
                'd-none',
                !data.current_waiting_label
            );

            const stages = Array.isArray(data.stages)
                ? data.stages
                : [];

            if (!stages.length) {
                timeline.textContent =
                    'Approval route is not available yet.';
                return;
            }

            const cycles = new Map();

            stages.forEach((stage) => {
                const cycleNumber = Number(stage.cycle_number) || 0;

                if (!cycles.has(cycleNumber)) {
                    cycles.set(cycleNumber, []);
                }

                cycles.get(cycleNumber).push(stage);
            });

            const cycleNumbers = Array.from(cycles.keys())
                .sort((left, right) => left - right);
            const currentCycle = Number.isFinite(
                Number(data.revision_cycle)
            )
                ? Number(data.revision_cycle)
                : cycleNumbers[cycleNumbers.length - 1];

            function cycleSummary(cycleStages, isCurrent) {
                if (isCurrent) {
                    return 'Current approval cycle';
                }

                if (cycleStages.some(
                    (stage) => stage.status === 'CHANGES_REQUESTED'
                )) {
                    return 'Returned for revision';
                }

                if (cycleStages.some(
                    (stage) => stage.status === 'REJECTED'
                )) {
                    return 'Request rejected';
                }

                if (cycleStages.every(
                    (stage) => ['APPROVED', 'SKIPPED']
                        .includes(stage.status)
                )) {
                    return 'Cycle completed';
                }

                return 'Previous approval cycle';
            }

            function displayStageStatus(stage, isHistoricalCycle) {
                if (
                    isHistoricalCycle
                    && stage.status === 'PENDING'
                ) {
                    return 'NOT_REACHED';
                }

                return String(stage.status || 'PENDING');
            }

            cycleNumbers.forEach((cycleNumber, cycleIndex) => {
                const cycleStages = cycles.get(cycleNumber)
                    .slice()
                    .sort(
                        (left, right) =>
                            Number(left.stage_order)
                            - Number(right.stage_order)
                    );
                const isCurrent = cycleNumber === currentCycle;
                const isHistoricalCycle = cycleNumber < currentCycle;

                if (cycleIndex > 0) {
                    const previousStages = cycles.get(
                        cycleNumbers[cycleIndex - 1]
                    );
                    const revisionStage = previousStages.find(
                        (stage) =>
                            stage.status === 'CHANGES_REQUESTED'
                    );
                    const resubmissionEvent = (data.events || []).find(
                        (event) =>
                            event.event_type === 'REQUEST_RESUBMITTED'
                            && Number(event.cycle_number) === cycleNumber
                    );
                    const revisionAuthor = text(
                        revisionStage?.acted_by_name,
                        'Previous approver'
                    );
                    const resubmittedBy = text(
                        resubmissionEvent?.actor_name,
                        data.requester_name || 'Request creator'
                    );
                    const divider = document.createElement('div');
                    divider.className = 'initiative-revision-divider';
                    divider.innerHTML = `
                        <span class="initiative-revision-divider-icon" aria-hidden="true">↻</span>
                        <div class="initiative-revision-divider-content">
                            <strong>Revision requested</strong>
                            <div class="initiative-revision-authors">
                                <span data-revision-author></span>
                                <span aria-hidden="true">•</span>
                                <span data-resubmitted-by></span>
                            </div>
                            <blockquote data-revision-note></blockquote>
                        </div>
                    `;
                    divider.querySelector('[data-revision-author]').textContent =
                        `Written by ${revisionAuthor}`;
                    divider.querySelector('[data-resubmitted-by]').textContent =
                        `Resubmitted by ${resubmittedBy}`;
                    divider.querySelector('[data-revision-note]').textContent =
                        revisionStage?.decision_comment
                        || 'The creator updated the request and started a new approval cycle.';
                    timeline.append(divider);
                }

                const cycle = document.createElement('section');
                cycle.className =
                    `initiative-cycle-card${isCurrent ? ' is-current' : ''}`;
                cycle.setAttribute(
                    'aria-label',
                    `Approval cycle ${cycleNumber + 1}`
                );

                const header = document.createElement('div');
                header.className = 'initiative-cycle-header';
                header.innerHTML = `
                    <div>
                        <span class="initiative-cycle-eyebrow"></span>
                        <h3></h3>
                    </div>
                    <span class="initiative-cycle-badge"></span>
                `;
                header.querySelector(
                    '.initiative-cycle-eyebrow'
                ).textContent = `Cycle ${cycleNumber + 1}`;
                header.querySelector('h3').textContent =
                    cycleSummary(cycleStages, isCurrent);
                header.querySelector(
                    '.initiative-cycle-badge'
                ).textContent = isCurrent ? 'Current' : 'History';
                cycle.append(header);

                const routeWrap = document.createElement('div');
                routeWrap.className = 'initiative-cycle-route-wrap';

                const route = document.createElement('div');
                route.className = 'initiative-cycle-route';
                route.style.gridTemplateColumns =
                    `repeat(${Math.max(cycleStages.length, 1)}, minmax(10rem, 1fr))`;

                cycleStages.forEach((stage, index) => {
                    const displayedStatus = displayStageStatus(
                        stage,
                        isHistoricalCycle
                    );
                    const item = document.createElement('div');
                    const statusClass = {
                        IN_PROGRESS: ' is-current',
                        DISCUSSING_REVISION: ' is-current',
                        APPROVED: ' is-approved',
                        CHANGES_REQUESTED: ' is-revision',
                        REJECTED: ' is-rejected',
                        SKIPPED: ' is-skipped',
                        NOT_REACHED: ' is-not-reached'
                    }[displayedStatus] || '';

                    item.className =
                        `timeline-step initiative-cycle-step${statusClass}`;
                    item.innerHTML = `
                        <span class="timeline-marker">${index + 1}</span>
                        <strong></strong>
                        <small></small>
                        <span class="initiative-stage-meta"></span>
                    `;
                    item.querySelector('strong').textContent =
                        stage.stage_label;

                    const actedByLabel = stage.acted_by_name
                        && stage.acted_on_behalf_of_name
                        ? `${stage.acted_by_name} on behalf of ${
                            stage.acted_on_behalf_of_name
                        }`
                        : stage.acted_by_name;

                    const personLabel = displayedStatus === 'NOT_REACHED'
                        ? 'Not reached in this cycle'
                        : (
                            actedByLabel
                            || stage.assigned_user_name
                            || 'Pending assignment'
                        );
                    item.querySelector('small').textContent = personLabel;

                    const receivedLabel = stage.received_at
                        ? AgreementApi.formatDate(stage.received_at)
                        : '—';
                    const statusLabel = displayedStatus === 'NOT_REACHED'
                        ? 'NOT REACHED'
                        : displayedStatus.replaceAll('_', ' ');
                    const receivedText = displayedStatus === 'NOT_REACHED'
                        ? ''
                        : ` · received ${receivedLabel}`;

                    item.querySelector(
                        '.initiative-stage-meta'
                    ).textContent = `${statusLabel}${receivedText}`;

                    if (stage.decision_comment) {
                        const note = document.createElement('p');
                        note.className = 'initiative-stage-note';
                        note.textContent = stage.decision_comment;
                        item.append(note);
                    }

                    route.append(item);
                });

                routeWrap.append(route);
                cycle.append(routeWrap);
                timeline.append(cycle);
            });
        }

        try {
            await AgreementApi.requireSession();

            const data = await AgreementApi.request(
                `/initiative-requests/${encodeURIComponent(id)}`
            );

            set(
                '[data-request-code]',
                data.request_code || 'Draft request'
            );
            set('[data-request-title]', data.title);

            const statusWrap = document.querySelector(
                '[data-request-status]'
            );
            statusWrap.replaceChildren(
                AgreementApi.createStatusBadge(data.status)
            );

            set(
                '[data-request-owner]',
                `Requested by ${
                    data.requester_name || 'University user'
                }`
            );
            set(
                '[data-requester-type]',
                labelCode(
                    data.requester_type,
                    requesterTypeLabels,
                    data.requester_type_other
                )
            );
            set('[data-requester-mobile]', data.requester_mobile);
            set(
                '[data-requester-position]',
                data.requester_position_snapshot
            );
            set(
                '[data-requester-entity]',
                data.requester_entity_snapshot
            );
            set(
                '[data-requester-department]',
                data.requester_department_snapshot
            );
            set(
                '[data-requester-email]',
                data.requester_email_snapshot
            );

            set(
                '[data-request-type]',
                labelCode(
                    data.initiative_type,
                    initiativeTypeLabels,
                    data.primary_type_other
                )
            );
            set(
                '[data-secondary-types]',
                labelCodes(
                    data.secondary_types,
                    initiativeTypeLabels
                )
            );
            set('[data-description]', data.description);
            set('[data-objective]', data.objective);
            set('[data-impact]', data.expected_impact);
            set('[data-beneficiaries]', data.beneficiaries);
            set(
                '[data-target-groups]',
                labelCodes(
                    data.target_groups,
                    targetGroupLabels,
                    data.target_group_other
                )
            );
            set(
                '[data-expected-participants]',
                data.expected_participants
            );

            set(
                '[data-proposed-dates]',
                `${data.proposed_start_date || '—'} to ${
                    data.proposed_end_date || '—'
                }`
            );
            set(
                '[data-implementation-scope]',
                labelCode(
                    data.implementation_scope,
                    scopeLabels,
                    data.implementation_scope_other
                )
            );
            const venueNameElement = document.querySelector(
                '[data-proposed-venue-name]'
            );
            const venueName = String(
                data.proposed_venue_name || ''
            ).trim();
            const venueAddress = String(
                data.proposed_venue || ''
            ).trim();
            const showVenueName = (
                venueName !== ''
                && venueName.toLocaleLowerCase()
                    !== venueAddress.toLocaleLowerCase()
            );

            if (venueNameElement) {
                venueNameElement.textContent = showVenueName
                    ? venueName
                    : '';
                venueNameElement.classList.toggle(
                    'd-none',
                    !showVenueName
                );
            }

            set('[data-proposed-venue]', data.proposed_venue);
            renderGoogleMapsLocationLink(data);
            set(
                '[data-implementation-country]',
                data.implementation_country
            );
            set(
                '[data-online-platform-name]',
                data.online_platform_name
            );

            /*
             * Keep the read-only detail page consistent with the form:
             * physical location fields belong to outside/hybrid delivery,
             * while the online platform belongs to virtual/hybrid delivery.
             */
            const detailImplementationScope = String(
                data.implementation_scope || ''
            );
            const showPhysicalLocationDetail = [
                'OUTSIDE_UOB',
                'HYBRID'
            ].includes(detailImplementationScope);
            const showOnlinePlatformDetail = [
                'VIRTUAL',
                'HYBRID'
            ].includes(detailImplementationScope);

            document.querySelector(
                '[data-proposed-venue]'
            )?.closest('div')?.classList.toggle(
                'd-none',
                !showPhysicalLocationDetail
            );
            document.querySelector(
                '[data-implementation-country]'
            )?.closest('div')?.classList.toggle(
                'd-none',
                !showPhysicalLocationDetail
            );
            document.querySelector(
                '[data-online-platform-name]'
            )?.closest('div')?.classList.toggle(
                'd-none',
                !showOnlinePlatformDetail
            );
            const relatedAgreements = Array.isArray(
                data.related_agreements
            )
                ? data.related_agreements
                : [];
            const relatedAgreementSummary = relatedAgreements.length > 0
                ? relatedAgreements.map((agreement) => (
                    `${agreement.agreement_code || ''} — ${
                        agreement.title || ''
                    }`
                )).join(', ')
                : (
                    data.has_related_agreement === null
                    || data.has_related_agreement === undefined
                        ? data.related_agreement_title
                        : booleanValue(data.has_related_agreement)
                            ? data.related_agreement_title
                            : 'No related Agreement'
                );
            set(
                '[data-related-agreement]',
                relatedAgreementSummary
            );
            set(
                '[data-has-external-partner]',
                yesNo(data.has_external_partner)
            );
            set(
                '[data-external-partner-name]',
                data.external_partner_name
            );
            set(
                '[data-external-partner-role]',
                data.external_partner_role
            );

            set(
                '[data-required-resources]',
                labelCodes(
                    data.required_resources,
                    resourceLabels,
                    data.resource_other
                )
            );
            set(
                '[data-estimated-budget]',
                formatBudget(data.estimated_budget)
            );
            set(
                '[data-needs-media]',
                yesNo(data.needs_media_support)
            );
            set(
                '[data-supports-sdg]',
                yesNo(data.supports_sdg)
            );
            set(
                '[data-sdg-goals]',
                labelCodes(data.sdg_goals, sdgLabels)
            );
            set(
                '[data-declaration]',
                booleanValue(data.declaration_confirmed)
                    ? `Confirmed${
                        data.declaration_confirmed_at
                            ? ` · ${AgreementApi.formatDate(
                                data.declaration_confirmed_at
                            )}`
                            : ''
                    }`
                    : 'Not confirmed'
            );
            renderDetailAttachments(data.attachments);
            set(
                '[data-current-waiting]',
                data.current_waiting_label || ''
            );

            renderDraftActions(data);
            renderConversionActions(data);
            renderDecisionPanel(data);
            renderAdminSkipPanel(data);
            renderRevisionDiscussions(data);
            renderTimeline(data);

            const members = document.querySelector(
                '[data-members]'
            );

            (data.members || []).forEach((member) => {
                const item = document.createElement('div');
                item.className = 'initiative-member-item';
                item.innerHTML =
                    '<strong></strong><small></small>';
                item.querySelector('strong').textContent =
                    member.full_name || member.email;
                item.querySelector('small').textContent =
                    displayMemberRole(member.member_role);
                members.append(item);
            });

            if (!members.children.length) {
                members.textContent = 'No collaborators added.';
            }

            const events = document.querySelector(
                '[data-events]'
            );

            (data.events || []).slice(0, 8).forEach((event) => {
                const item = document.createElement('div');
                item.className = 'initiative-event-item';
                item.innerHTML =
                    '<strong></strong><small></small><p></p>';
                item.querySelector('strong').textContent =
                    String(event.event_type || 'Update')
                        .replaceAll('_', ' ');
                let eventData = event.event_data;

                if (typeof eventData === 'string') {
                    try {
                        eventData = JSON.parse(eventData);
                    } catch (_) {
                        eventData = {};
                    }
                }

                const behalfLabel = eventData?.acted_on_behalf_of_name
                    ? ` · on behalf of ${
                        eventData.acted_on_behalf_of_name
                    }`
                    : '';

                item.querySelector('small').textContent =
                    `${event.actor_name || 'System'}${behalfLabel} · ${
                        AgreementApi.formatDate(event.occurred_at)
                    }`;
                item.querySelector('p').textContent =
                    event.event_note || '';
                events.append(item);
            });

            if (!events.children.length) {
                events.textContent = 'No events recorded yet.';
            }

            document
                .querySelector('[data-detail-content]')
                .classList.remove('d-none');
        } catch (error) {
            showError(
                error,
                'The Initiative request could not be loaded.'
            );
        } finally {
            loading.classList.add('d-none');
        }
    }

    if (view === 'form') {
        initializeForm();
    } else if (view === 'convert' || view === 'existing') {
        initializeConversion();
    } else if (view === 'detail') {
        initializeDetail();
    } else if (view === 'notifications') {
        initializeNotifications();
    } else {
        initializeList();
    }
})();
