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
        priorities: document.querySelector('[data-dashboard-priorities]'),
        actions: document.querySelector('[data-dashboard-actions]'),
        agreementMetrics: document.querySelector('[data-agreement-metrics]'),
        workTitle: document.querySelector('[data-primary-work-title]'),
        workDescription: document.querySelector('[data-primary-work-description]'),
        workLink: document.querySelector('[data-primary-work-link]'),
        workList: document.querySelector('[data-primary-work-list]'),
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

    function action(
        title,
        description,
        href,
        label,
        module = '',
        actionKey = ''
    ) {
        const link = document.createElement('a');
        link.className = `dashboard-action ${module ? `is-${module}` : ''}`.trim();
        link.href = href;
        if (actionKey) link.dataset.dashboardActionKey = actionKey;

        const heading = document.createElement('strong');
        heading.textContent = title;
        const text = document.createElement('small');
        text.textContent = description;
        const cta = document.createElement('span');
        cta.textContent = `${label} →`;
        link.append(heading, text, cta);
        return link;
    }

    function priority(label, value, detail, href, tone = '', module = '') {
        const card = document.createElement(href ? 'a' : 'article');
        card.className = [
            'dashboard-priority-card',
            tone,
            module ? `is-${module}` : ''
        ].filter(Boolean).join(' ');
        if (href) card.href = href;

        const eyebrow = document.createElement('span');
        eyebrow.textContent = label;
        const number = document.createElement('strong');
        number.textContent = String(value);
        const copy = document.createElement('small');
        copy.textContent = detail;
        card.append(eyebrow, number, copy);
        return card;
    }

    function moduleMetric(value, label, detail, tone = '') {
        const metric = document.createElement('div');
        metric.className = `dashboard-module-metric ${tone}`.trim();
        const number = document.createElement('strong');
        number.textContent = String(value ?? 0);
        const name = document.createElement('span');
        name.textContent = label;
        const copy = document.createElement('small');
        copy.textContent = detail;
        metric.append(number, name, copy);
        return metric;
    }

    function workItem(title, detail, badge, href, module = '') {
        const item = document.createElement('li');
        item.className = `dashboard-list-item ${module ? `is-${module}` : ''}`.trim();
        const copy = document.createElement('div');
        const heading = document.createElement('strong');
        heading.textContent = title;
        const text = document.createElement('small');
        text.textContent = detail;
        copy.append(heading, text);

        const side = document.createElement(href ? 'a' : 'span');
        if (href) side.href = href;
        side.className = href
            ? 'btn btn-sm btn-outline-primary align-self-center'
            : 'align-self-center';
        if (badge instanceof Node) side.append(badge);
        else side.textContent = badge || 'Open';
        item.append(copy, side);
        return item;
    }

    function emptyItem(message) {
        const empty = document.createElement('li');
        empty.className = 'dashboard-empty';
        empty.textContent = message;
        return empty;
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
        const displayName = AgreementApi.displayName(user);
        const firstName = user.first_name || displayName.split(' ')[0];
        const hour = new Date().getHours();
        const timeGreeting = hour < 12
            ? 'Good morning'
            : (hour < 18 ? 'Good afternoon' : 'Good evening');

        elements.greeting.textContent = `${timeGreeting}, ${firstName}`;
                /*
         * OVERVIEW ROLE CONTEXT DEDUPLICATION V1
         * Avoid repeating the same role when primaryContext()
         * already includes it.
         */
        const roleContextParts = [
            roleName(user),
            ...String(
                AgreementApi.primaryContext(user) || ''
            ).split(' · ')
        ]
            .map((part) => part.trim())
            .filter(Boolean);

        const seenRoleContextParts = new Set();

        elements.role.textContent = roleContextParts
            .filter((part) => {
                const key = part.toLocaleLowerCase();

                if (seenRoleContextParts.has(key)) {
                    return false;
                }

                seenRoleContextParts.add(key);
                return true;
            })
            .join(' · ');
        elements.title.textContent = 'Agreements and Initiatives, together in one workspace.';
        elements.description.textContent = 'See what needs your attention, follow both approval routes, and move University partnerships into measurable action.';
    }

    function baseActions(user) {
        const actions = [];

        if (isReviewer(user)) {
            actions.push(action(
                'Review Agreement inbox',
                'Open Agreements currently waiting for your office decision.',
                'workflow-inbox.php',
                'Review now',
                'agreement',
                'agreement-review'
            ));
        }

        if (isAgreementCreator(user)) {
            actions.push(action(
                'Create an Agreement',
                'Start a complete draft for a new partnership.',
                'agreement-form.php',
                'Create draft',
                'agreement',
                'agreement-create'
            ));
        }

        if (
            isAgreementCreator(user)
            || AgreementApi.hasPermission(user, 'VIEW_AGREEMENT')
        ) {
            actions.push(action(
                isAgreementCreator(user)
                    ? 'My Agreement portfolio'
                    : 'Agreement register',
                'Browse visible Agreements, their status, partners, and workflow.',
                'agreements.php',
                'Open Agreements',
                'agreement',
                'agreement-portfolio'
            ));
        }

        if (
            AgreementApi.hasPermission(user, 'MANAGE_AGREEMENT_REPORTS')
            || AgreementApi.hasPermission(user, 'REVIEW_AGREEMENT_REPORTS')
        ) {
            actions.push(action(
                'Annual reports',
                'Prepare or review accepted evidence and Agreement outcomes.',
                'performance-reports.php',
                'Open reports',
                'agreement',
                'agreement-reports'
            ));
        }

        if (
            AgreementApi.hasPermission(user, 'VIEW_AGREEMENT_DASHBOARD')
            || AgreementApi.hasPermission(user, 'MANAGE_AGREEMENT_REPORTS')
        ) {
            actions.push(action(
                'Performance dashboard',
                'See targets, accepted results, deadlines, and programme health.',
                'performance-dashboard.php',
                'View performance',
                'agreement',
                'agreement-performance'
            ));
        }

        actions.push(action(
            'My profile',
            'Review your position, role, and system access.',
            'profile.php',
            'View profile',
            '',
            'profile'
        ));
        return actions;
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
            text.textContent = 'The overview keeps Agreement decisions and visible Initiative work together while preserving their separate approval routes.';
            items = [
                'Open assigned Agreement reviews from the Agreement work list.',
                'Open Initiative requests to continue assigned academic or executive decisions.',
                'Return changes with a specific reason and use accepted reports for performance decisions.'
            ];
        } else if (isAgreementCreator(user)) {
            heading.textContent = 'You manage partnerships and their impact';
            text.textContent = 'Create and follow Agreements, respond to requested changes, and connect approved partnerships to Initiatives and outcomes.';
            items = [
                'Agreement drafts and returns stay separate from Initiative requests.',
                'The overview shows what is under review in both modules.',
                'Annual reporting is based on accepted evidence, not unfinished drafts.'
            ];
        } else if (isInitiativeCreator(user)) {
            heading.textContent = 'You initiate University impact';
            text.textContent = 'Use active Agreements as context for Initiatives or submit an independent Initiative through your academic approval route.';
            items = [
                'Start or continue an Initiative request.',
                'Browse active Agreements for partnership opportunities.',
                'Track Department, College, VP, and President review from one overview.'
            ];
        } else {
            heading.textContent = 'Your access is informational';
            text.textContent = 'This overview shows the Agreement and Initiative records available to your account.';
            items = [
                'Browse visible University records.',
                'Review your profile access.',
                'Contact the administrator if your position is incorrect.'
            ];
        }

        items.forEach((value) => {
            const item = document.createElement('li');
            item.className = 'mb-2';
            item.textContent = value;
            list.append(item);
        });
        elements.guidance.replaceChildren(heading, text, list);
    }

    function renderAgreementMetrics(summary) {
        elements.agreementMetrics.replaceChildren(
            moduleMetric(
                summary.portfolioCount,
                summary.portfolioLabel,
                summary.portfolioDetail
            ),
            moduleMetric(
                summary.underReview,
                'Under review',
                'Moving through Agreement approval'
            ),
            moduleMetric(
                summary.active,
                'Active University Agreements',
                'Available for delivery and Initiative context',
                'is-success'
            ),
            moduleMetric(
                summary.overdue,
                'Overdue annual reports',
                summary.overdue ? 'Requires attention' : 'No overdue reports',
                summary.overdue ? 'is-warning' : ''
            )
        );
    }

    function renderAgreementPriorities(summary) {
        const actionCount = summary.attention + summary.reviewTasks;
        elements.priorities.dataset.agreementAction = String(actionCount);
        elements.priorities.dataset.agreementReview = String(summary.underReview);
        elements.priorities.dataset.agreementOverdue = String(summary.overdue);
        elements.priorities.replaceChildren(
            priority(
                'Agreements',
                actionCount,
                'Drafts, returns, or assigned Agreement reviews requiring action',
                summary.reviewTasks ? 'workflow-inbox.php' : 'agreements.php?scope=mine',
                actionCount ? 'is-danger' : 'is-clear',
                'agreement'
            ),
            priority(
                'Initiatives',
                '…',
                'Loading Initiative drafts, returns, and decisions',
                'initiative-workflow.php',
                '',
                'initiative'
            ),
            priority(
                'In review',
                summary.underReview,
                'Agreement records currently moving through approval',
                'agreements.php?scope=mine'
            ),
            priority(
                'Reports & updates',
                summary.overdue,
                'Overdue Agreement reports; Initiative updates are loading',
                'performance-reports.php',
                summary.overdue ? 'is-warning' : 'is-clear'
            )
        );
    }

    async function agreementRowsWithTimeline(rows) {
        return Promise.all(rows.map(async (agreement) => {
            if (agreement.status !== 'UNDER_REVIEW') {
                return { agreement, timeline: null };
            }
            const timeline = await AgreementApi.agreementTimeline(
                agreement.agreement_id
            ).catch(() => null);
            return { agreement, timeline };
        }));
    }

    async function renderAgreementWork(user, summary) {
        elements.workLink.href = summary.reviewTasks
            ? 'workflow-inbox.php'
            : 'agreements.php';
        elements.workList.replaceChildren();

        if (summary.reviewTasks > 0) {
            elements.workTitle.textContent = 'Agreement decisions';
            elements.workDescription.textContent = 'Assigned Agreement reviews are shown first.';
            summary.tasks.slice(0, 5).forEach((task) => {
                const agreementId = task.subject_agreement_id || task.entity_id;
                const label = String(task.step_key || 'Workflow review')
                    .replaceAll('_', ' ');
                elements.workList.append(workItem(
                    `${label} · Agreement #${agreementId}`,
                    `${task.assigned_unit_name || task.assigned_unit_code || 'Assigned office'} · since ${AgreementApi.formatDate(task.started_at)}`,
                    'Review',
                    'workflow-inbox.php',
                    'agreement'
                ));
            });
            return;
        }

        const source = [
            ...summary.attentionRows,
            ...summary.underReviewRows,
            ...summary.activeRows
        ].filter((item, index, rows) => rows.findIndex(
            (candidate) => String(candidate.agreement_id)
                === String(item.agreement_id)
        ) === index).slice(0, 5);

        elements.workTitle.textContent = summary.attention
            ? 'Agreement work requiring action'
            : 'Agreement portfolio activity';
        elements.workDescription.textContent = summary.attention
            ? 'Drafts and returned Agreements you can continue now.'
            : 'Agreements under review and active University partnerships.';

        const timelineRows = await agreementRowsWithTimeline(source);
        timelineRows.forEach(({ agreement, timeline }) => {
            const current = timeline?.steps?.find(
                (step) => step.status === 'IN_PROGRESS'
            );
            const detail = current
                ? `Currently with ${current.assigned_unit_name || current.assigned_unit_code || 'reviewing office'}${current.assigned_reviewer_names ? ` · ${current.assigned_reviewer_names}` : ''}`
                : (agreement.status === 'REVISION_REQUIRED'
                    ? 'Changes requested — revise and resubmit.'
                    : (agreement.status === 'DRAFT'
                        ? 'Draft — complete and submit it.'
                        : (agreement.partner_name || agreement.partner_names?.[0] || 'University Agreement')));

            elements.workList.append(workItem(
                agreement.title,
                detail,
                AgreementApi.createStatusBadge(agreement.status),
                `agreement.php?id=${encodeURIComponent(agreement.agreement_id)}`,
                'agreement'
            ));
        });

        if (!source.length) {
            elements.workList.append(emptyItem(
                'No Agreement work is visible to your account yet.'
            ));
        }
    }

    async function loadAgreementOverview(user) {
        const mayReadAgreements = isAgreementCreator(user)
            || isReviewer(user)
            || AgreementApi.hasPermission(user, 'VIEW_AGREEMENT');
        const mayReadReports = AgreementApi.hasPermission(
            user,
            'MANAGE_AGREEMENT_REPORTS'
        ) || AgreementApi.hasPermission(
            user,
            'REVIEW_AGREEMENT_REPORTS'
        );

        const [agreementRows, reportPayload, assignments] = await Promise.all([
            mayReadAgreements
                ? AgreementApi.agreements().catch(() => [])
                : Promise.resolve([]),
            mayReadReports
                ? AgreementApi.performanceReports().catch(() => ({ reports: [] }))
                : Promise.resolve({ reports: [] }),
            isReviewer(user)
                ? AgreementApi.workflowInbox().catch(() => [])
                : Promise.resolve([])
        ]);

        const agreements = uniqueAgreements(agreementRows);
        const own = agreements.filter(
            (item) => Number(item.created_by) === Number(user.user_id)
        );
        const scoped = isAgreementCreator(user) ? own : agreements;
        const reports = Array.isArray(reportPayload?.reports)
            ? reportPayload.reports
            : [];
        const tasks = Array.isArray(assignments) ? assignments : [];
        const attentionRows = own.filter((item) =>
            ['DRAFT', 'REVISION_REQUIRED'].includes(item.status)
        );
        const underReviewRows = scoped.filter(
            (item) => item.status === 'UNDER_REVIEW'
        );
        const activeRows = agreements.filter(
            (item) => item.status === 'ACTIVE'
        );

        const summary = {
            agreements,
            own,
            tasks,
            attentionRows,
            underReviewRows,
            activeRows,
            attention: attentionRows.length,
            reviewTasks: tasks.length,
            underReview: underReviewRows.length,
            active: activeRows.length,
            overdue: reports.filter((item) => item.is_overdue === true).length,
            portfolioCount: isAgreementCreator(user) ? own.length : agreements.length,
            portfolioLabel: isAgreementCreator(user) ? 'My Agreements' : 'Visible Agreements',
            portfolioDetail: isAgreementCreator(user)
                ? 'Created and managed by you'
                : 'Available to your role'
        };

        renderAgreementMetrics(summary);
        renderAgreementPriorities(summary);
        await renderAgreementWork(user, summary);

        const navCount = document.querySelector('[data-workflow-nav-count]');
        if (navCount && tasks.length) {
            navCount.textContent = String(tasks.length);
            navCount.classList.remove('d-none');
        }
        return summary;
    }

    async function initialize() {
        try {
            const user = await AgreementApi.requireSession();
            setWelcome(user);
            renderGuidance(user);

            const actions = baseActions(user);
            elements.actions.replaceChildren(...actions.slice(0, 8));
            const agreement = await loadAgreementOverview(user);

            window.UobOverview = {
                user,
                agreement,
                baseActions: actions,
                helpers: {
                    action,
                    priority,
                    moduleMetric,
                    workItem,
                    emptyItem
                },
                role: {
                    isReviewer: isReviewer(user),
                    isAgreementCreator: isAgreementCreator(user),
                    isInitiativeCreator: isInitiativeCreator(user)
                }
            };

            elements.loading.classList.add('d-none');
            elements.content.classList.remove('d-none');
            document.dispatchEvent(new CustomEvent(
                'uob:overview-ready',
                { detail: window.UobOverview }
            ));
        } catch (error) {
            elements.loading.classList.add('d-none');
            elements.alert.textContent = error.message
                || 'Your dashboard could not be loaded.';
            elements.alert.classList.remove('d-none');
            elements.alert.focus();
        }
    }

    initialize();
}());
