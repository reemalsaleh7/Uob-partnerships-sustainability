(function () {
    'use strict';

    const isArabic = document.documentElement.lang === 'ar';
    const t = (english, arabic) => isArabic ? arabic : english;

    const elements = {
        alert: document.querySelector('[data-workflow-alert]'),
        success: document.querySelector('[data-workflow-success]'),
        refresh: document.querySelector('[data-workflow-refresh]'),
        templateList: document.querySelector('[data-template-list]'),
        versionList: document.querySelector('[data-version-list]'),
        loading: document.querySelector('[data-workflow-loading]'),
        form: document.querySelector('[data-workflow-form]'),
        title: document.querySelector('[data-template-title]'),
        version: document.querySelector('[data-template-version]'),
        meta: document.querySelector('[data-template-meta]'),
        description: document.querySelector('[data-template-description]'),
        summary: document.querySelector('[data-route-summary]'),
        stageList: document.querySelector('[data-stage-list]'),
        addStage: document.querySelector('[data-add-stage]'),
        reason: document.querySelector('[data-publish-reason]'),
        publish: document.querySelector('[data-publish-button]'),
        publishLabel: document.querySelector('[data-publish-label]'),
        publishSpinner: document.querySelector('[data-publish-spinner]'),
        stageTemplate: document.querySelector('[data-stage-template]')
    };

    const definitions = Object.freeze({
        AGREEMENT_APPROVAL: {
            title: t('Agreement approval', 'اعتماد الاتفاقيات'),
            description: t(
                'Creator, review offices, and final approval route',
                'مسار المنشئ ومكاتب المراجعة والاعتماد النهائي'
            )
        },
        INITIATIVE_APPROVAL: {
            title: t('Initiative approval', 'اعتماد المبادرات'),
            description: t(
                'Hierarchy and office approval route',
                'مسار الاعتماد الإداري والمكاتب'
            )
        }
    });

    const state = {
        templates: [],
        options: { units: [], positions: [] },
        selectedKey: null,
        activeTemplateId: null,
        sourceTemplate: null,
        versions: [],
        stages: [],
        openStageIndex: 0,
        busy: false
    };

    function showError(message) {
        elements.success.classList.add('d-none');
        elements.alert.textContent = message;
        elements.alert.classList.remove('d-none');
        elements.alert.focus({ preventScroll: true });
    }

    function showSuccess(message) {
        elements.alert.classList.add('d-none');
        elements.success.textContent = message;
        elements.success.classList.remove('d-none');
        elements.success.focus({ preventScroll: true });
    }

    function clearMessages() {
        elements.alert.classList.add('d-none');
        elements.success.classList.add('d-none');
    }

    function booleanValue(value) {
        return value === true
            || value === 1
            || value === '1'
            || value === 't'
            || value === 'true';
    }

    function cloneStage(stage) {
        return {
            template_step_id: stage.template_step_id ?? null,
            step_key: String(stage.step_key || ''),
            step_label: String(stage.step_label || ''),
            execution_mode: String(stage.execution_mode || 'SEQUENTIAL'),
            is_optional: booleanValue(stage.is_optional),
            responsibility_type: String(
                stage.responsibility_type
                || (stage.step_key === 'CREATOR' ? 'CREATOR' : 'POSITION')
            ),
            responsibility_scope: String(
                stage.responsibility_scope
                || (stage.step_key === 'CREATOR' ? 'NONE' : 'FIXED_UNIT')
            ),
            required_unit_id: stage.required_unit_id === null
                || stage.required_unit_id === undefined
                ? null
                : Number(stage.required_unit_id),
            required_position_id: stage.required_position_id === null
                || stage.required_position_id === undefined
                ? null
                : Number(stage.required_position_id),
            reminder_after_days: Number(stage.reminder_after_days || 3),
            is_system_step: booleanValue(stage.is_system_step),
            allow_revision: booleanValue(stage.allow_revision ?? true)
        };
    }

    function templateDefinition(key) {
        return definitions[key] || {
            title: key,
            description: ''
        };
    }

    function renderTemplateList() {
        elements.templateList.replaceChildren();
        state.templates.forEach((template) => {
            const definition = templateDefinition(template.template_key);
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'workflow-template-option';
            button.classList.toggle(
                'is-active',
                template.template_key === state.selectedKey
            );
            button.dataset.templateKey = template.template_key;

            const title = document.createElement('strong');
            title.textContent = definition.title;
            const details = document.createElement('small');
            details.textContent = t(
                `Version ${template.version_number} · ${template.stage_count} stages`,
                `الإصدار ${template.version_number} · ${template.stage_count} مراحل`
            );
            button.append(title, details);
            button.addEventListener('click', () => {
                loadTemplate(template.template_key).catch(handleError);
            });
            elements.templateList.append(button);
        });
    }

    function renderVersions() {
        elements.versionList.replaceChildren();
        const list = document.createElement('div');
        list.className = 'workflow-version-list';
        state.versions.forEach((version) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'workflow-version-item';
            const label = document.createElement('span');
            label.textContent = t(
                `Version ${version.version_number}`,
                `الإصدار ${version.version_number}`
            );
            const count = document.createElement('span');
            count.textContent = t(
                `${version.stage_count} stages`,
                `${version.stage_count} مراحل`
            );
            button.append(label, count);
            button.title = version.change_reason || '';
            button.addEventListener('click', () => {
                loadVersion(version.workflow_template_id).catch(handleError);
            });
            list.append(button);
        });
        elements.versionList.append(list);
    }

    function stagePhaseData() {
        let phase = 0;
        return state.stages.map((stage, index) => {
            const mode = index === 0 ? 'SEQUENTIAL' : stage.execution_mode;
            if (mode === 'SEQUENTIAL') {
                phase += 1;
            }
            return { phase, mode };
        });
    }

    function renderRouteSummary() {
        elements.summary.replaceChildren();
        const phases = stagePhaseData();
        state.stages.forEach((stage, index) => {
            if (index > 0) {
                const arrow = document.createElement('span');
                arrow.className = 'workflow-route-arrow';
                arrow.textContent = phases[index].mode === 'PARALLEL'
                    ? '＋'
                    : (isArabic ? '←' : '→');
                elements.summary.append(arrow);
            }
            const node = document.createElement('span');
            node.className = 'workflow-route-node';
            node.classList.toggle(
                'is-parallel',
                phases[index].mode === 'PARALLEL'
            );
            node.textContent = `${phases[index].phase}. ${stage.step_label || t('New stage', 'مرحلة جديدة')}`;
            elements.summary.append(node);
        });

        // WORKFLOW_SCENARIO_VISUAL_PREVIEW_V1
        window.dispatchEvent(
            new CustomEvent('uob:workflow-stages-changed', {
                detail: {
                    stages: state.stages.map(cloneStage)
                }
            })
        );
    }

    function createSelectOption(value, label) {
        const option = document.createElement('option');
        option.value = String(value ?? '');
        option.textContent = label;
        return option;
    }

    function populateUnits(select, selectedId) {
        select.replaceChildren(createSelectOption('', t('Choose unit…', 'اختاري الوحدة…')));
        state.options.units.forEach((unit) => {
            const indent = '—'.repeat(Math.max(0, Number(unit.depth || 0)));
            const label = `${indent}${indent ? ' ' : ''}${unit.path || unit.name}`;
            const option = createSelectOption(unit.unit_id, label);
            option.selected = Number(selectedId) === Number(unit.unit_id);
            select.append(option);
        });
    }

    function populatePositions(select, selectedId) {
        select.replaceChildren(createSelectOption('', t('Choose position…', 'اختاري المنصب…')));
        state.options.positions.forEach((position) => {
            const option = createSelectOption(
                position.position_id,
                `${position.name} · ${position.position_type}`
            );
            option.selected = Number(selectedId) === Number(position.position_id);
            select.append(option);
        });
    }

    function decorateStageAccordions() {
        const cards = Array.from(
            elements.stageList.querySelectorAll(
                '.workflow-stage-card'
            )
        );

        cards.forEach((card, index) => {
            const actions = card.querySelector(
                '.workflow-stage-actions'
            );

            if (!actions) {
                return;
            }

            let toggle = card.querySelector(
                '[data-stage-toggle]'
            );

            if (!toggle) {
                toggle = document.createElement('button');
                toggle.type = 'button';
                toggle.className =
                    'btn btn-sm btn-outline-primary workflow-stage-toggle';
                toggle.dataset.stageToggle = '';
                actions.prepend(toggle);
            }

            const isOpen =
                state.openStageIndex === index;

            card.classList.toggle(
                'is-collapsed',
                !isOpen
            );

            toggle.setAttribute(
                'aria-expanded',
                isOpen ? 'true' : 'false'
            );

            toggle.setAttribute(
                'aria-label',
                isOpen
                    ? t(
                        `Collapse stage ${index + 1}`,
                        `طي المرحلة ${index + 1}`
                    )
                    : t(
                        `Edit stage ${index + 1}`,
                        `تعديل المرحلة ${index + 1}`
                    )
            );

            toggle.textContent = isOpen
                ? t('Close', 'إغلاق')
                : t('Edit', 'تعديل');

            toggle.onclick = () => {
                state.openStageIndex =
                    state.openStageIndex === index
                        ? -1
                        : index;

                decorateStageAccordions();
            };
        });
    }
    function renderStages() {
        elements.stageList.replaceChildren();
        const phases = stagePhaseData();

        state.stages.forEach((stage, index) => {
            const fragment = elements.stageTemplate.content.cloneNode(true);
            const card = fragment.querySelector('.workflow-stage-card');
            const indexBox = fragment.querySelector('[data-stage-index]');
            const heading = fragment.querySelector('[data-stage-heading]');
            const phase = fragment.querySelector('[data-stage-phase]');
            const label = fragment.querySelector('[data-stage-label]');
            const execution = fragment.querySelector('[data-stage-execution]');
            const optional = fragment.querySelector('[data-stage-optional]');
            const responsibility = fragment.querySelector('[data-stage-responsibility]');
            const scope = fragment.querySelector('[data-stage-scope]');
            const unit = fragment.querySelector('[data-stage-unit]');
            const position = fragment.querySelector('[data-stage-position]');
            const unitField = fragment.querySelector('[data-unit-field]');
            const positionField = fragment.querySelector('[data-position-field]');
            const reminder = fragment.querySelector('[data-stage-reminder]');
            const revision = fragment.querySelector('[data-stage-revision]');
            const moveUp = fragment.querySelector('[data-stage-up]');
            const moveDown = fragment.querySelector('[data-stage-down]');
            const remove = fragment.querySelector('[data-stage-delete]');
            const lock = fragment.querySelector('[data-stage-lock]');
            const isCreator = stage.step_key === 'CREATOR';

            card.classList.toggle('is-system', stage.is_system_step);
            indexBox.textContent = String(index + 1);
            heading.textContent = stage.step_label || t('New stage', 'مرحلة جديدة');
            phase.textContent = t(
                `Phase ${phases[index].phase}${phases[index].mode === 'PARALLEL' ? ' · parallel' : ''}`,
                `المرحلة ${phases[index].phase}${phases[index].mode === 'PARALLEL' ? ' · متوازية' : ''}`
            );
            label.value = stage.step_label;
            execution.value = index === 0 ? 'SEQUENTIAL' : stage.execution_mode;
            optional.value = String(stage.is_optional);
            responsibility.value = isCreator ? 'POSITION' : stage.responsibility_type;
            scope.value = isCreator ? 'FIXED_UNIT' : stage.responsibility_scope;
            populateUnits(unit, stage.required_unit_id);
            populatePositions(position, stage.required_position_id);
            reminder.value = String(stage.reminder_after_days || 3);
            revision.checked = stage.allow_revision;

            const syncVisibility = () => {
                const type = responsibility.value;
                const scopeValue = scope.value;
                unitField.classList.toggle('d-none', scopeValue !== 'FIXED_UNIT');
                positionField.classList.toggle('d-none', type !== 'POSITION');
            };
            syncVisibility();

            label.addEventListener('input', () => {
                stage.step_label = label.value;
                renderRouteSummary();
                heading.textContent = label.value || t('New stage', 'مرحلة جديدة');
            });
            execution.addEventListener('change', () => {
                stage.execution_mode = execution.value;
                renderStages();
                renderRouteSummary();
            });
            optional.addEventListener('change', () => {
                stage.is_optional = optional.value === 'true';
                renderRouteSummary();
            });
            responsibility.addEventListener('change', () => {
                stage.responsibility_type = responsibility.value;
                if (responsibility.value === 'UNIT') {
                    stage.required_position_id = null;
                }
                syncVisibility();
            });
            scope.addEventListener('change', () => {
                stage.responsibility_scope = scope.value;
                if (scope.value !== 'FIXED_UNIT') {
                    stage.required_unit_id = null;
                    unit.value = '';
                }
                syncVisibility();
            });
            unit.addEventListener('change', () => {
                stage.required_unit_id = unit.value ? Number(unit.value) : null;
            });
            position.addEventListener('change', () => {
                stage.required_position_id = position.value
                    ? Number(position.value)
                    : null;
            });
            reminder.addEventListener('input', () => {
                stage.reminder_after_days = Number(reminder.value || 3);
            });
            revision.addEventListener('change', () => {
                stage.allow_revision = revision.checked;
            });

            moveUp.disabled = index === 0 || isCreator;
            moveDown.disabled = index === state.stages.length - 1 || isCreator;
            moveUp.addEventListener('click', () => moveStage(index, -1));
            moveDown.addEventListener('click', () => moveStage(index, 1));

            remove.disabled = stage.is_system_step || isCreator;
            remove.addEventListener('click', () => {
                state.stages.splice(index, 1);
                renderStages();
                renderRouteSummary();
            });

            if (isCreator) {
                label.readOnly = true;
                execution.disabled = true;
                optional.disabled = true;
                responsibility.disabled = true;
                scope.disabled = true;
                unit.disabled = true;
                position.disabled = true;
                reminder.disabled = true;
                revision.disabled = true;
                lock.textContent = t(
                    'The Creator is a protected first stage and cannot be changed or deleted.',
                    'المنشئ مرحلة نظام محمية ويجب أن تبقى أول مرحلة ولا يمكن حذفها.'
                );
                lock.classList.remove('d-none');
            } else if (stage.is_system_step) {
                lock.textContent = t(
                    'This is a protected core stage. Its settings may be edited, but it cannot be deleted.',
                    'هذه مرحلة أساسية محمية. يمكن تعديل إعداداتها، لكن لا يمكن حذفها.'
                );
                lock.classList.remove('d-none');
            }

            elements.stageList.append(fragment);
        });
            decorateStageAccordions();
    }

    function moveStage(index, direction) {
        const target = index + direction;
        if (target < 1 || target >= state.stages.length) {
            return;
        }
        const [stage] = state.stages.splice(index, 1);
        state.stages.splice(target, 0, stage);
                state.openStageIndex = target;
        renderStages();
        renderRouteSummary();
    }

    function addStage() {
        state.stages.push({
            template_step_id: null,
            step_key: '',
            step_label: t('New approval stage', 'مرحلة اعتماد جديدة'),
            execution_mode: 'SEQUENTIAL',
            is_optional: false,
            responsibility_type: 'POSITION',
            responsibility_scope: 'FIXED_UNIT',
            required_unit_id: null,
            required_position_id: null,
            reminder_after_days: 3,
            is_system_step: false,
            allow_revision: true
        });
        state.openStageIndex = state.stages.length - 1;
        renderStages();
        renderRouteSummary();
        elements.stageList.lastElementChild?.scrollIntoView({
            behavior: 'smooth',
            block: 'center'
        });
    }

    function applyTemplate(template, versions, preserveActiveId) {
        state.sourceTemplate = template;
        state.versions = versions || state.versions;
        state.stages = (template.steps || []).map(cloneStage);
        state.openStageIndex = 0;
        if (!preserveActiveId) {
            state.activeTemplateId = template.workflow_template_id;
        }
        const definition = templateDefinition(state.selectedKey);
        elements.title.textContent = definition.title;
        elements.version.textContent = t(
            `Based on version ${template.version_number}`,
            `مبني على الإصدار ${template.version_number}`
        );
        elements.meta.textContent = t(
            `${state.stages.length} stages · ${new Set(stagePhaseData().map((item) => item.phase)).size} phases`,
            `${state.stages.length} مراحل · ${new Set(stagePhaseData().map((item) => item.phase)).size} مجموعات تنفيذ`
        );
        elements.description.value = template.description || '';
        elements.reason.value = '';
        elements.loading.classList.add('d-none');
        elements.form.classList.remove('d-none');
        renderStages();
        renderRouteSummary();
        renderVersions();
        renderTemplateList();
    }

    async function loadTemplate(key) {
        clearMessages();
        state.selectedKey = key;
        elements.form.classList.add('d-none');
        elements.loading.classList.remove('d-none');
        const payload = await AgreementApi.request(`/admin/workflows/${encodeURIComponent(key)}`);
        applyTemplate(payload.template, payload.versions, false);
    }

    async function loadVersion(templateId) {
        clearMessages();
        const payload = await AgreementApi.request(
            `/admin/workflows/${encodeURIComponent(state.selectedKey)}/versions/${encodeURIComponent(templateId)}`
        );
        applyTemplate(payload.template, state.versions, true);
        showSuccess(t(
            'Historical version loaded as an editable draft. Publishing will create a new version.',
            'تم تحميل الإصدار السابق كمسودة قابلة للتعديل. النشر سينشئ إصدارًا جديدًا.'
        ));
    }

    function stagePayload() {
        return state.stages.map((stage) => ({
            step_key: stage.step_key,
            step_label: stage.step_label,
            execution_mode: stage.execution_mode,
            is_optional: stage.is_optional,
            responsibility_type: stage.step_key === 'CREATOR'
                ? 'CREATOR'
                : stage.responsibility_type,
            responsibility_scope: stage.step_key === 'CREATOR'
                ? 'NONE'
                : stage.responsibility_scope,
            required_unit_id: stage.required_unit_id,
            required_position_id: stage.required_position_id,
            reminder_after_days: stage.reminder_after_days,
            is_system_step: stage.is_system_step,
            allow_revision: stage.allow_revision
        }));
    }

    async function publish(event) {
        event.preventDefault();
        if (state.busy) return;
        clearMessages();
        if (!elements.form.reportValidity()) return;

        state.busy = true;
        elements.publish.disabled = true;
        elements.publishSpinner.classList.remove('d-none');
        elements.publishLabel.textContent = t('Publishing…', 'جاري النشر…');

        try {
            const payload = await AgreementApi.request(
                `/admin/workflows/${encodeURIComponent(state.selectedKey)}`,
                {
                    method: 'POST',
                    body: JSON.stringify({
                        expected_template_id: state.activeTemplateId,
                        description: elements.description.value,
                        reason: elements.reason.value,
                        stages: stagePayload()
                    })
                }
            );
            applyTemplate(payload.template, payload.versions, false);
            await loadIndex(false);
            showSuccess(t(
                `Version ${payload.template.version_number} was published. Open workflows were not changed.`,
                `تم نشر الإصدار ${payload.template.version_number}. مسارات العمل المفتوحة لم تتغير.`
            ));
        } finally {
            state.busy = false;
            elements.publish.disabled = false;
            elements.publishSpinner.classList.add('d-none');
            elements.publishLabel.textContent = t(
                'Publish new version',
                'نشر إصدار جديد'
            );
        }
    }

    async function loadIndex(selectFirst = true) {
        const [index, options] = await Promise.all([
            AgreementApi.request('/admin/workflows'),
            AgreementApi.request('/admin/workflows/options')
        ]);
        state.templates = Array.isArray(index.templates) ? index.templates : [];
        state.options = options || { units: [], positions: [] };
        renderTemplateList();
        if (selectFirst) {
            const firstKey = state.selectedKey
                || state.templates[0]?.template_key;
            if (firstKey) {
                await loadTemplate(firstKey);
            }
        }
    }

    function handleError(error) {
        showError(error?.message || t(
            'The Workflow template could not be loaded.',
            'تعذر تحميل قالب سير العمل.'
        ));
    }

    elements.addStage.addEventListener('click', addStage);
    elements.form.addEventListener('submit', (event) => {
        publish(event).catch(handleError);
    });
    elements.refresh.addEventListener('click', () => {
        loadTemplate(state.selectedKey).catch(handleError);
    });

    AgreementApi.requireSession('MANAGE_WORKFLOW_TEMPLATES')
        .then(() => loadIndex(true))
        .catch(handleError);
}());
