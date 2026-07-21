(function () {
    'use strict';

    const elements = {
        alert: document.querySelector('[data-dashboard-alert]'),
        loading: document.querySelector('[data-dashboard-loading]'),
        content: document.querySelector('[data-dashboard-content]'),
        greeting: document.querySelector('[data-dashboard-greeting]'),
        title: document.querySelector('[data-dashboard-title]'),
        description: document.querySelector('[data-dashboard-description]'),
        role: document.querySelector('[data-dashboard-role]'),
        actions: document.querySelector('[data-dashboard-actions]'),
        kpiHeading: document.querySelector('[data-kpi-heading]'),
        kpiTitle: document.querySelector('[data-kpi-title]'),
        kpiDescription: document.querySelector('[data-kpi-description]'),
        kpis: document.querySelector('[data-dashboard-kpis]'),
        workColumn: document.querySelector('[data-primary-work-column]'),
        workTitle: document.querySelector('[data-primary-work-title]'),
        workDescription: document.querySelector('[data-primary-work-description]'),
        workLink: document.querySelector('[data-primary-work-link]'),
        workList: document.querySelector('[data-primary-work-list]'),
        guidanceColumn: document.querySelector('[data-role-guidance-column]'),
        guidance: document.querySelector('[data-role-guidance]')
    };

    function hasRole(user, role) {
        return Array.isArray(user.roles) && user.roles.includes(role);
    }

    function isReviewer(user) {
        return AgreementApi.hasPermission(user, 'APPROVE_AGREEMENT')
            || AgreementApi.hasPermission(user, 'REJECT_AGREEMENT');
    }

    function isAgreementCreator(user) {
        return AgreementApi.hasPermission(user, 'CREATE_AGREEMENT');
    }

    function isInitiativeCreator(user) {
        return AgreementApi.hasPermission(user, 'CREATE_INITIATIVE')
            || hasRole(user, 'Initiative Creator');
    }

    function action(title, description, href, label) {
        const link = document.createElement('a');
        link.className = 'dashboard-action';
        link.href = href;

        const heading = document.createElement('strong');
        heading.textContent = title;
        const text = document.createElement('small');
        text.textContent = description;
        const cta = document.createElement('span');
        cta.textContent = `${label} →`;
        link.append(heading, text, cta);
        return link;
    }

    function kpi(value, label, detail) {
        const card = document.createElement('div');
        card.className = 'dashboard-kpi-card';
        const number = document.createElement('strong');
        number.textContent = String(value ?? 0);
        const name = document.createElement('span');
        name.textContent = label;
        const context = document.createElement('small');
        context.textContent = detail;
        card.append(number, name, context);
        return card;
    }

    function uniqueAgreements(rows) {
        const agreements = new Map();
        (Array.isArray(rows) ? rows : []).forEach((row) => {
            agreements.set(String(row.agreement_id), row);
        });
        return Array.from(agreements.values());
    }

    function roleName(user) {
        const position = Array.isArray(user.positions) ? user.positions[0] : null;
        return position?.position || user.roles?.[0] || 'University user';
    }

    function setWelcome(user) {
        const firstName = user.first_name || AgreementApi.displayName(user).split(' ')[0];
        const hour = new Date().getHours();
        const timeGreeting = hour < 12 ? 'Good morning' : (hour < 18 ? 'Good afternoon' : 'Good evening');
        elements.greeting.textContent = `${timeGreeting}, ${firstName}`;
        elements.role.textContent = `${roleName(user)} · ${AgreementApi.primaryContext(user)}`;

        if (isReviewer(user)) {
            elements.title.textContent = 'Make the next decision with the full context in view.';
            elements.description.textContent = 'Your assigned reviews, deadlines, and institutional performance are prioritized below.';
        } else if (isAgreementCreator(user)) {
            elements.title.textContent = 'Know where every Agreement stands—and whether it is delivering.';
            elements.description.textContent = 'Track your portfolio from draft through approval, signing, implementation, and annual reporting.';
        } else if (isInitiativeCreator(user)) {
            elements.title.textContent = 'Turn your idea into a University initiative.';
            elements.description.textContent = 'Start an initiative request, understand the approval path, and explore active partnerships you can build on.';
        }
    }

    function renderActions(user) {
        const actions = [];

        if (isAgreementCreator(user)) {
            actions.push(action('Create an Agreement', 'Start a complete draft for a new partnership.', 'agreement-form.php', 'Create draft'));
            actions.push(action('My Agreement portfolio', 'See drafts, reviews, approvals, and active Agreements.', 'agreements.php', 'Open portfolio'));
        } else if (AgreementApi.hasPermission(user, 'VIEW_AGREEMENT')) {
            actions.push(action('Agreement register', 'Open Agreements visible to you.', 'agreements.php', 'Browse records'));
        }

        if (isReviewer(user)) {
            actions.unshift(action('Review inbox', 'Open Agreements currently waiting for your decision.', 'workflow-inbox.php', 'Review now'));
        }

        if (
            AgreementApi.hasPermission(user, 'MANAGE_AGREEMENT_REPORTS')
            || AgreementApi.hasPermission(user, 'REVIEW_AGREEMENT_REPORTS')
        ) {
            actions.push(action('Annual reports', 'Prepare or review performance evidence and outcomes.', 'performance-reports.php', 'Open reports'));
        }

        if (
            AgreementApi.hasPermission(user, 'VIEW_AGREEMENT_DASHBOARD')
            || AgreementApi.hasPermission(user, 'MANAGE_AGREEMENT_REPORTS')
        ) {
            actions.push(action('Performance dashboard', 'See targets, actual outcomes, deadlines, and program health.', 'performance-dashboard.php', 'View performance'));
        }

        if (isInitiativeCreator(user)) {
            const startInitiative = action(
                'Start an initiative',
                'Submit a new initiative idea from your college or department.',
                '#',
                'Start request'
            );
            startInitiative.dataset.legacyInitiative = 'request-initiative.php?lang=en';
            actions.unshift(startInitiative);
        }

        actions.push(action('Initiative hub', 'Explore initiatives, SDGs, and the Initiative module.', 'initiative-hub.php', 'Open hub'));
        actions.push(action('My profile', 'Review your position, role, and system access.', 'profile.php', 'View profile'));

        elements.actions.replaceChildren(...actions.slice(0, 6));
    }

    function renderGuidance(user) {
        const heading = document.createElement('h3');
        heading.className = 'h6 fw-bold mb-2';
        const text = document.createElement('p');
        text.className = 'small text-secondary mb-3';
        const list = document.createElement('ul');
        list.className = 'small text-secondary ps-3 mb-0';
        let items = [];

        if (isReviewer(user)) {
            heading.textContent = 'You are a decision-maker';
            text.textContent = 'Your home page prioritizes active assignments. Open a task to review the complete Agreement, evidence, and earlier decisions.';
            items = ['Act only on tasks assigned to your office.', 'Return changes with a specific reason.', 'Use the performance dashboard for accepted operational results.'];
        } else if (isAgreementCreator(user)) {
            heading.textContent = 'You own the Agreement portfolio';
            text.textContent = 'You create Agreements, respond to requested changes, finalize signing, and submit annual performance evidence.';
            items = ['The timeline shows the current reviewer.', 'Returned Agreements appear as work requiring attention.', 'Your performance view is limited to your portfolio.'];
        } else if (isInitiativeCreator(user)) {
            heading.textContent = 'You initiate University impact';
            text.textContent = 'Faculty members do not approve or create Agreements. Your operational path is to propose initiatives and connect them to approved partnerships.';
            items = ['Start an initiative request.', 'Use active Agreements as partnership context.', 'Your department and college route the request upward.'];
        } else {
            heading.textContent = 'Your access is informational';
            text.textContent = 'Your account currently has no operational role. Your profile shows the exact roles and permissions assigned to you.';
            items = ['Browse the public portal.', 'Review your profile access.', 'Contact the administrator if your position is incorrect.'];
        }

        items.forEach((value) => {
            const item = document.createElement('li');
            item.className = 'mb-2';
            item.textContent = value;
            list.append(item);
        });
        elements.guidance.replaceChildren(heading, text, list);
    }

    function workItem(title, detail, badge, href) {
        const item = document.createElement('li');
        item.className = 'dashboard-list-item';
        const copy = document.createElement('div');
        const heading = document.createElement('strong');
        heading.textContent = title;
        const text = document.createElement('small');
        text.textContent = detail;
        copy.append(heading, text);
        const side = document.createElement(href ? 'a' : 'span');
        if (href) side.href = href;
        side.className = href ? 'btn btn-sm btn-outline-primary align-self-center' : 'align-self-center';
        if (badge instanceof Node) side.append(badge); else side.textContent = badge || 'Open';
        item.append(copy, side);
        return item;
    }

    async function loadAgreementCreatorView(user) {
        const [agreementRows, reportPayload] = await Promise.all([
            AgreementApi.agreements(),
            AgreementApi.performanceReports().catch(() => ({ reports: [] }))
        ]);
        const agreements = uniqueAgreements(agreementRows);
        const own = agreements.filter((item) => Number(item.created_by) === Number(user.user_id));
        const reports = Array.isArray(reportPayload?.reports) ? reportPayload.reports : [];
        const overdue = reports.filter((item) => item.is_overdue === true).length;
        const underReview = own.filter((item) => item.status === 'UNDER_REVIEW');
        const attention = own.filter((item) => ['DRAFT', 'REVISION_REQUIRED'].includes(item.status));

        elements.kpiHeading.classList.remove('d-none');
        elements.kpis.classList.remove('d-none');
        elements.kpiTitle.textContent = 'Your Agreement portfolio';
        elements.kpiDescription.textContent = 'Records created and managed by you.';
        elements.kpis.replaceChildren(
            kpi(own.length, 'Total Agreements', 'Your complete portfolio'),
            kpi(underReview.length, 'Under review', 'Currently with an approving office'),
            kpi(own.filter((item) => item.status === 'ACTIVE').length, 'Active', 'In operational delivery'),
            kpi(overdue, 'Overdue reports', overdue ? 'Requires attention' : 'No overdue reporting work')
        );

        elements.workColumn.classList.remove('d-none');
        elements.guidanceColumn.className = 'col-xl-5';
        elements.workTitle.textContent = attention.length ? 'Work requiring your action' : 'Where your reviews are now';
        elements.workDescription.textContent = attention.length
            ? 'Drafts and returned Agreements you can act on now.'
            : 'The current review office for submitted Agreements.';
        elements.workLink.href = 'agreements.php';
        elements.workList.replaceChildren();

        const source = attention.length ? attention.slice(0, 5) : underReview.slice(0, 5);
        const timelineRows = await Promise.all(source.map(async (agreement) => {
            if (agreement.status !== 'UNDER_REVIEW') return { agreement, timeline: null };
            const timeline = await AgreementApi.agreementTimeline(agreement.agreement_id).catch(() => null);
            return { agreement, timeline };
        }));

        timelineRows.forEach(({ agreement, timeline }) => {
            const current = timeline?.steps?.find((step) => step.status === 'IN_PROGRESS');
            const detail = current
                ? `Currently with ${current.assigned_unit_name || current.assigned_unit_code || 'reviewing office'}${current.assigned_reviewer_names ? ` · ${current.assigned_reviewer_names}` : ''}`
                : (agreement.status === 'REVISION_REQUIRED' ? 'Changes were requested; revise and resubmit.' : 'Draft is ready for completion.');
            elements.workList.append(workItem(
                agreement.title,
                detail,
                AgreementApi.createStatusBadge(agreement.status),
                `agreement.php?id=${encodeURIComponent(agreement.agreement_id)}`
            ));
        });

        if (!source.length) {
            const empty = document.createElement('li');
            empty.className = 'dashboard-empty';
            empty.textContent = 'No Agreement work needs your attention right now.';
            elements.workList.append(empty);
        }
    }

    async function loadReviewerView() {
        const [assignments, dashboard] = await Promise.all([
            AgreementApi.workflowInbox(),
            AgreementApi.performanceDashboard(new Date().getFullYear()).catch(() => null)
        ]);
        const tasks = Array.isArray(assignments) ? assignments : [];
        const navCount = document.querySelector('[data-workflow-nav-count]');
        if (navCount && tasks.length) {
            navCount.textContent = String(tasks.length);
            navCount.classList.remove('d-none');
        }

        elements.kpiHeading.classList.remove('d-none');
        elements.kpis.classList.remove('d-none');
        elements.kpiTitle.textContent = 'Decision and delivery overview';
        elements.kpiDescription.textContent = 'Your queue and institutional reporting position.';
        elements.kpis.replaceChildren(
            kpi(tasks.length, 'Assigned reviews', 'Waiting for your decision'),
            kpi(dashboard?.reports?.submitted || 0, 'Reports awaiting review', 'Submitted performance evidence'),
            kpi(dashboard?.agreements?.active_agreements || 0, 'Active Agreements', 'Currently delivering outcomes'),
            kpi(dashboard?.reports?.overdue || 0, 'Overdue reports', 'Institutional reporting risk')
        );

        elements.workColumn.classList.remove('d-none');
        elements.guidanceColumn.className = 'col-xl-5';
        elements.workTitle.textContent = 'Decisions waiting for you';
        elements.workDescription.textContent = 'Oldest active assignments are shown first.';
        elements.workLink.href = 'workflow-inbox.php';
        elements.workList.replaceChildren();
        tasks.slice(0, 6).forEach((task) => {
            const agreementId = task.subject_agreement_id || task.entity_id;
            const label = String(task.step_key || 'Workflow review').replaceAll('_', ' ');
            elements.workList.append(workItem(
                `${label} · Agreement #${agreementId}`,
                `${task.assigned_unit_name || task.assigned_unit_code || 'Assigned office'} · since ${AgreementApi.formatDate(task.started_at)}`,
                'Review',
                'workflow-inbox.php'
            ));
        });
        if (!tasks.length) {
            const empty = document.createElement('li');
            empty.className = 'dashboard-empty';
            empty.textContent = 'Your review inbox is clear.';
            elements.workList.append(empty);
        }
    }

    async function loadInitiativeCreatorView(user) {
        const position = user.positions?.[0] || {};
        const agreements = uniqueAgreements(await AgreementApi.agreements());
        const active = agreements.filter((agreement) => agreement.status === 'ACTIVE');
        elements.kpiHeading.classList.remove('d-none');
        elements.kpis.classList.remove('d-none');
        elements.kpiTitle.textContent = 'Your initiative context';
        elements.kpiDescription.textContent = 'Active partnership opportunities and the organizational route attached to your account.';
        elements.kpis.replaceChildren(
            kpi(active.length, 'Active Agreements', 'Available partnership contexts'),
            kpi(position.organizational_unit || '—', 'Organizational unit', 'Your routing context'),
            kpi('5', 'Approval stages', 'Creator, Department, College, VP, President'),
            kpi('17', 'SDG goals', 'Available impact classifications')
        );

        elements.workColumn.classList.remove('d-none');
        elements.guidanceColumn.className = 'col-xl-5';
        elements.workTitle.textContent = 'Active partnerships you can build on';
        elements.workDescription.textContent = 'Review an Agreement or use it as the context for a new Initiative.';
        elements.workLink.href = 'agreements.php';
        elements.workList.replaceChildren();

        active.slice(0, 5).forEach((agreement) => {
            const item = document.createElement('li');
            item.className = 'dashboard-list-item';
            const copy = document.createElement('div');
            const title = document.createElement('strong');
            title.textContent = agreement.title;
            const detail = document.createElement('small');
            detail.textContent = agreement.partner_name
                || agreement.partner_names?.[0]
                || 'Active University partnership';
            copy.append(title, detail);
            const actions = document.createElement('div');
            actions.className = 'agreement-row-actions';
            const view = document.createElement('a');
            view.className = 'btn btn-sm btn-outline-primary';
            view.href = `agreement.php?id=${encodeURIComponent(agreement.agreement_id)}`;
            view.textContent = 'View';
            const use = document.createElement('a');
            use.className = 'btn btn-sm btn-primary';
            use.href = '#';
            use.dataset.legacyInitiative = `request-initiative.php?lang=en&agreement_id=${encodeURIComponent(agreement.agreement_id)}&agreement_code=${encodeURIComponent(agreement.agreement_code || '')}`;
            use.textContent = 'Use for Initiative';
            actions.append(view, use);
            item.append(copy, actions);
            elements.workList.append(item);
        });

        if (!active.length) {
            const empty = document.createElement('li');
            empty.className = 'dashboard-empty';
            empty.textContent = 'No active Agreements are available yet.';
            elements.workList.append(empty);
        }
    }

    async function initialize() {
        try {
            const user = await AgreementApi.requireSession();
            setWelcome(user);
            renderActions(user);
            renderGuidance(user);

            if (isReviewer(user)) {
                await loadReviewerView(user);
            } else if (isAgreementCreator(user)) {
                await loadAgreementCreatorView(user);
            } else if (isInitiativeCreator(user)) {
                await loadInitiativeCreatorView(user);
            }

            elements.loading.classList.add('d-none');
            elements.content.classList.remove('d-none');
        } catch (error) {
            elements.loading.classList.add('d-none');
            elements.alert.textContent = error.message || 'Your dashboard could not be loaded.';
            elements.alert.classList.remove('d-none');
            elements.alert.focus();
        }
    }

    initialize();
})();
