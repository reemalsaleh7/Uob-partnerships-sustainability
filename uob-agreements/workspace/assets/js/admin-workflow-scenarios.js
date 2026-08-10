(function (root, factory) {
    'use strict';

    const api = factory();
    if (typeof module === 'object' && module.exports) {
        module.exports = api;
    }

    if (typeof document === 'undefined') {
        return;
    }

    const isArabic = document.documentElement.lang === 'ar';
    const t = (english, arabic) => isArabic ? arabic : english;
    const PAGE_SIZE = 12;

    const elements = {
        explorer: document.querySelector('[data-scenario-explorer]'),
        tabs: Array.from(document.querySelectorAll('[data-scenario-mode]')),
        current: document.querySelector('[data-scenario-current]'),
        all: document.querySelector('[data-scenario-all]'),
        summary: document.querySelector('[data-scenario-summary]'),
        total: document.querySelector('[data-scenario-total]'),
        optional: document.querySelector('[data-scenario-optional]'),
        countBadge: document.querySelector('[data-scenario-count-badge]'),
        list: document.querySelector('[data-scenario-list]'),
        more: document.querySelector('[data-scenario-more]'),
        moreLabel: document.querySelector('[data-scenario-more-label]')
    };

    if (!elements.explorer || !elements.list) {
        return;
    }

    const state = {
        stages: [],
        loaded: 0,
        mode: 'current',
        renderFrame: null
    };

    function setMode(mode) {
        state.mode = mode === 'all' ? 'all' : 'current';
        const allMode = state.mode === 'all';

        elements.current?.classList.toggle('d-none', allMode);
        elements.all?.classList.toggle('d-none', !allMode);

        elements.tabs.forEach((button) => {
            const active = button.dataset.scenarioMode === state.mode;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-selected', active ? 'true' : 'false');
            button.tabIndex = active ? 0 : -1;
        });

        if (allMode && elements.list.childElementCount === 0) {
            renderMore();
        }
    }

    function formatCount(value) {
        const text = String(value);
        return text.replace(/\B(?=(\d{3})+(?!\d))/g, isArabic ? '٬' : ',');
    }

    function stageName(stage) {
        return String(stage?.step_label || t('Unnamed stage', 'مرحلة بلا اسم'));
    }

    function createBadge(text, className = '') {
        const badge = document.createElement('span');
        badge.className = `workflow-scenario-badge ${className}`.trim();
        badge.textContent = text;
        return badge;
    }

    function createStageNode(stage) {
        const node = document.createElement('div');
        node.className = 'workflow-scenario-stage-node';
        if (stage.is_optional) {
            node.classList.add('is-optional');
        }
        if (stage.step_key === 'CREATOR') {
            node.classList.add('is-creator');
        }

        const name = document.createElement('strong');
        name.textContent = stageName(stage);
        node.append(name);

        const meta = document.createElement('small');
        if (stage.step_key === 'CREATOR') {
            meta.textContent = t('Starting point', 'نقطة البداية');
        } else if (stage.is_optional) {
            meta.textContent = t('Optional stage included', 'مرحلة اختيارية مضمّنة');
        } else {
            meta.textContent = t('Required stage', 'مرحلة إلزامية');
        }
        node.append(meta);
        return node;
    }

    function createPhaseNode(phase) {
        if (phase.stages.length === 1) {
            return createStageNode(phase.stages[0]);
        }

        const group = document.createElement('div');
        group.className = 'workflow-scenario-parallel-group';

        const label = document.createElement('span');
        label.className = 'workflow-scenario-parallel-label';
        label.textContent = t('Parallel phase', 'مرحلة متوازية');
        group.append(label);

        const branches = document.createElement('div');
        branches.className = 'workflow-scenario-parallel-branches';
        phase.stages.forEach((stage) => {
            branches.append(createStageNode(stage));
        });
        group.append(branches);
        return group;
    }

    function createArrow() {
        const arrow = document.createElement('span');
        arrow.className = 'workflow-scenario-connector';
        arrow.setAttribute('aria-hidden', 'true');
        arrow.textContent = isArabic ? '←' : '→';
        return arrow;
    }

    function renderScenario(scenario, ordinal) {
        const card = document.createElement('article');
        card.className = 'workflow-scenario-card';

        const header = document.createElement('header');
        header.className = 'workflow-scenario-card-header';

        const titleWrap = document.createElement('div');
        const eyebrow = document.createElement('span');
        eyebrow.className = 'workflow-scenario-card-eyebrow';
        eyebrow.textContent = t(`Scenario ${ordinal}`, `السيناريو ${ordinal}`);
        const title = document.createElement('h4');
        title.className = 'h6 mb-0';
        title.textContent = scenario.skipped.length === 0
            ? t('Complete route', 'المسار الكامل')
            : t('Alternative route', 'مسار بديل');
        titleWrap.append(eyebrow, title);

        const badges = document.createElement('div');
        badges.className = 'workflow-scenario-card-badges';
        badges.append(
            createBadge(
                t(
                    `${scenario.includedStageCount} stages`,
                    `${scenario.includedStageCount} مراحل`
                )
            ),
            createBadge(
                t(
                    `${scenario.phases.length} phases`,
                    `${scenario.phases.length} مجموعات تنفيذ`
                )
            )
        );
        header.append(titleWrap, badges);
        card.append(header);

        const route = document.createElement('div');
        route.className = 'workflow-scenario-route';
        route.setAttribute('role', 'list');
        scenario.phases.forEach((phase, index) => {
            if (index > 0) {
                route.append(createArrow());
            }
            const phaseNode = createPhaseNode(phase);
            phaseNode.setAttribute('role', 'listitem');
            route.append(phaseNode);
        });
        card.append(route);

        if (scenario.skipped.length > 0) {
            const skipped = document.createElement('div');
            skipped.className = 'workflow-scenario-skipped';
            const label = document.createElement('strong');
            label.textContent = t('Skipped optional stages:', 'المراحل الاختيارية المتجاوزة:');
            skipped.append(label);
            scenario.skipped.forEach((stage) => {
                skipped.append(createBadge(stageName(stage), 'is-muted'));
            });
            card.append(skipped);
        }

        return card;
    }

    function updateMetrics() {
        const optionalCount = api.optionalStageCount(state.stages);
        const total = api.totalScenarioCount(state.stages);
        const totalText = formatCount(total);

        if (elements.total) {
            elements.total.textContent = totalText;
        }
        if (elements.optional) {
            elements.optional.textContent = String(optionalCount);
        }
        if (elements.countBadge) {
            elements.countBadge.textContent = totalText;
        }
        if (elements.summary) {
            elements.summary.textContent = optionalCount === 0
                ? t(
                    'This template has one possible route because every stage is required.',
                    'هذا القالب يملك مسارًا واحدًا لأن جميع مراحله إلزامية.'
                )
                : t(
                    `${totalText} visual routes are generated from ${optionalCount} optional stage${optionalCount === 1 ? '' : 's'}.`,
                    `تم توليد ${totalText} مسارات مرئية من ${optionalCount} مراحل اختيارية.`
                );
        }
    }

    function updateMoreButton() {
        const total = api.totalScenarioCountBigInt(state.stages);
        const remaining = total - BigInt(state.loaded);
        const hasMore = remaining > 0n;
        elements.more?.classList.toggle('d-none', !hasMore);
        if (!hasMore || !elements.moreLabel) {
            return;
        }
        const next = remaining > BigInt(PAGE_SIZE)
            ? BigInt(PAGE_SIZE)
            : remaining;
        elements.moreLabel.textContent = t(
            `Show ${formatCount(next)} more scenarios`,
            `عرض ${formatCount(next)} سيناريوهات إضافية`
        );
    }

    function renderMore() {
        const total = api.totalScenarioCountBigInt(state.stages);
        if (BigInt(state.loaded) >= total) {
            updateMoreButton();
            return;
        }

        const remaining = total - BigInt(state.loaded);
        const count = Number(
            remaining > BigInt(PAGE_SIZE)
                ? BigInt(PAGE_SIZE)
                : remaining
        );

        const fragment = document.createDocumentFragment();
        for (let offset = 0; offset < count; offset += 1) {
            const ordinal = state.loaded + offset + 1;
            const scenario = api.scenarioAt(state.stages, state.loaded + offset);
            fragment.append(renderScenario(scenario, ordinal));
        }
        elements.list.append(fragment);
        state.loaded += count;
        updateMoreButton();
    }

    function renderAll() {
        state.loaded = 0;
        elements.list.replaceChildren();
        updateMetrics();
        updateMoreButton();
        if (state.mode === 'all') {
            renderMore();
        }
    }

    function scheduleStages(stages) {
        state.stages = Array.isArray(stages)
            ? stages.map(api.normalizeStage)
            : [];

        if (state.renderFrame !== null) {
            cancelAnimationFrame(state.renderFrame);
        }
        state.renderFrame = requestAnimationFrame(() => {
            state.renderFrame = null;
            renderAll();
        });
    }

    elements.tabs.forEach((button) => {
        button.addEventListener('click', () => {
            setMode(button.dataset.scenarioMode);
        });
        button.addEventListener('keydown', (event) => {
            if (!['ArrowLeft', 'ArrowRight'].includes(event.key)) {
                return;
            }
            event.preventDefault();
            const nextMode = state.mode === 'current' ? 'all' : 'current';
            setMode(nextMode);
            elements.tabs.find(
                (item) => item.dataset.scenarioMode === nextMode
            )?.focus();
        });
    });

    elements.more?.addEventListener('click', renderMore);

    window.addEventListener('uob:workflow-stages-changed', (event) => {
        scheduleStages(event.detail?.stages || []);
    });

    setMode('current');
}(typeof globalThis !== 'undefined' ? globalThis : this, function () {
    'use strict';

    function booleanValue(value) {
        return value === true
            || value === 1
            || value === '1'
            || value === 't'
            || value === 'true';
    }

    function normalizeStage(stage) {
        return {
            step_key: String(stage?.step_key || ''),
            step_label: String(stage?.step_label || ''),
            execution_mode: String(stage?.execution_mode || 'SEQUENTIAL')
                .toUpperCase() === 'PARALLEL'
                ? 'PARALLEL'
                : 'SEQUENTIAL',
            is_optional: booleanValue(stage?.is_optional)
        };
    }

    function phaseData(stages) {
        let phaseOrder = 0;
        return stages.map((rawStage, index) => {
            const stage = normalizeStage(rawStage);
            const mode = index === 0
                ? 'SEQUENTIAL'
                : stage.execution_mode;
            if (mode === 'SEQUENTIAL') {
                phaseOrder += 1;
            }
            return {
                ...stage,
                phase_order: phaseOrder
            };
        });
    }

    function optionalStageCount(stages) {
        return phaseData(stages).filter((stage) => stage.is_optional).length;
    }

    function totalScenarioCountBigInt(stages) {
        return 1n << BigInt(optionalStageCount(stages));
    }

    function totalScenarioCount(stages) {
        return totalScenarioCountBigInt(stages).toString();
    }

    function scenarioAt(stages, index) {
        const phased = phaseData(stages);
        const optional = phased.filter((stage) => stage.is_optional);
        const total = 1n << BigInt(optional.length);
        const requestedIndex = BigInt(index);

        if (requestedIndex < 0n || requestedIndex >= total) {
            throw new RangeError('Scenario index is outside the available range.');
        }

        // The first visual scenario is the complete route. Following scenarios
        // progressively remove optional stages according to the binary mask.
        const mask = total - 1n - requestedIndex;
        const optionalBitByStage = new Map();
        optional.forEach((stage, bit) => {
            optionalBitByStage.set(stage, BigInt(bit));
        });

        const included = [];
        const skipped = [];
        phased.forEach((stage) => {
            if (!stage.is_optional) {
                included.push(stage);
                return;
            }
            const bit = optionalBitByStage.get(stage);
            const include = ((mask >> bit) & 1n) === 1n;
            (include ? included : skipped).push(stage);
        });

        const phaseMap = new Map();
        included.forEach((stage) => {
            if (!phaseMap.has(stage.phase_order)) {
                phaseMap.set(stage.phase_order, []);
            }
            phaseMap.get(stage.phase_order).push(stage);
        });

        const phases = Array.from(phaseMap.entries())
            .sort((left, right) => left[0] - right[0])
            .map(([phaseOrder, phaseStages]) => ({
                phase_order: phaseOrder,
                stages: phaseStages
            }));

        return {
            mask: mask.toString(),
            phases,
            skipped,
            includedStageCount: included.length
        };
    }

    return Object.freeze({
        normalizeStage,
        phaseData,
        optionalStageCount,
        totalScenarioCount,
        totalScenarioCountBigInt,
        scenarioAt
    });
}));
