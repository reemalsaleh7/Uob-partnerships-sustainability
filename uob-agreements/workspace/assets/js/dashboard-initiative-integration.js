(function () {
    'use strict';

    const elements = {
        actions: document.querySelector('[data-dashboard-actions]'),
        priorities: document.querySelector('[data-dashboard-priorities]'),
        metrics: document.querySelector('[data-initiative-metrics]'),
        workList: document.querySelector('[data-initiative-work-list]')
    };

    if (!elements.actions || !elements.priorities || !elements.metrics) {
        return;
    }

    function requestItems(payload) {
        return Array.isArray(payload)
            ? payload
            : (Array.isArray(payload?.items) ? payload.items : []);
    }

    function normalizedStatus(status) {
        return String(status || '').toUpperCase();
    }

    function requestDetail(item) {
        if (item.current_stage_label) {
            return `Current stage: ${item.current_stage_label}`;
        }

        const messages = {
            DRAFT: 'Draft — complete and submit it.',
            UNDER_REVIEW: 'Moving through the Initiative approval route.',
            RESUBMITTED: 'Resubmitted and moving through approval.',
            REVISION_REQUIRED: 'Changes requested — revise and resubmit.',
            APPROVED: item.can_convert
                ? 'Approved — ready to convert to a final Initiative.'
                : 'Approved Initiative request.',
            CONVERTING: 'Final Initiative form is in progress.',
            CONVERTED: 'Converted to a final Initiative.',
            REJECTED: 'Request rejected.'
        };

        return messages[normalizedStatus(item.status)]
            || 'Open the request to view its progress.';
    }

    function waitForOverview() {
        if (window.UobOverview) {
            return Promise.resolve(window.UobOverview);
        }

        return new Promise((resolve) => {
            document.addEventListener(
                'uob:overview-ready',
                (event) => resolve(event.detail),
                { once: true }
            );
        });
    }

    function initiativeActions(overview, access, unreadCount) {
        const { action } = overview.helpers;
        const cards = [];

        if (access.can_create_initiative) {
            cards.push(action(
                'Start an Initiative request',
                'Create a draft, add collaborators, and submit it through the University approval route.',
                'initiative-workflow.php?view=form',
                'Start request',
                'initiative'
            ));
        }

        cards.push(action(
            'Initiative requests',
            unreadCount > 0
                ? `${unreadCount} unread update${unreadCount === 1 ? '' : 's'} across requests you can access.`
                : 'Open requests you created, joined, review, or administer.',
            'initiative-workflow.php',
            'Open requests',
            'initiative'
        ));
        cards.push(action(
            'Final Initiatives',
            'Open approved requests converted into final Initiative records.',
            'initiative-portfolio.php',
            'Open portfolio',
            'initiative'
        ));

        if (!access.can_create_initiative && unreadCount > 0) {
            cards.push(action(
                'Initiative notifications',
                `${unreadCount} unread Initiative update${unreadCount === 1 ? '' : 's'} needs attention.`,
                'initiative-workflow.php?view=notifications',
                'Open updates',
                'initiative'
            ));
        }

        return cards;
    }

    function renderActions(overview, access, unreadCount) {
        const agreementCards = overview.baseActions.filter((card) =>
            card.dataset.dashboardActionKey !== 'profile'
        ).slice(0, 4);
        const profile = overview.baseActions.find((card) =>
            card.dataset.dashboardActionKey === 'profile'
        );
        const initiativeCards = initiativeActions(
            overview,
            access,
            unreadCount
        ).slice(0, Math.max(3, 7 - agreementCards.length));
        const cards = [...agreementCards, ...initiativeCards];

        if (profile && cards.length < 8) {
            cards.push(profile);
        }
        elements.actions.replaceChildren(...cards.slice(0, 8));
    }

    function renderMetrics(overview, summary) {
        const { moduleMetric } = overview.helpers;
        elements.metrics.replaceChildren(
            moduleMetric(
                summary.requests.length,
                'Visible requests',
                'Created, joined, reviewed, or administered by you'
            ),
            moduleMetric(
                summary.underReview,
                'Under review',
                'Moving through Initiative approval'
            ),
            moduleMetric(
                summary.finalInitiatives.length,
                'Final Initiatives',
                'Approved and converted records',
                'is-success'
            ),
            moduleMetric(
                summary.unreadCount,
                'Unread updates',
                summary.unreadCount ? 'Open notifications for details' : 'You are up to date',
                summary.unreadCount ? 'is-warning' : ''
            )
        );
    }

    function renderPriorities(overview, summary) {
        const { priority } = overview.helpers;
        const agreement = overview.agreement;
        const agreementAction = agreement.attention + agreement.reviewTasks;
        const initiativeAction = summary.attentionRows.length;
        const totalInReview = agreement.underReview + summary.underReview;
        const reportsAndUpdates = agreement.overdue + summary.unreadCount;
        const inReviewHref = summary.underReview > 0
            ? 'initiative-workflow.php'
            : 'agreements.php?scope=mine';
        const reportsAndUpdatesHref = summary.unreadCount > 0
            ? 'initiative-workflow.php?view=notifications'
            : 'performance-reports.php';

        elements.priorities.replaceChildren(
            priority(
                'Agreements',
                agreementAction,
                'Drafts, returns, or assigned Agreement reviews requiring action',
                agreement.reviewTasks ? 'workflow-inbox.php' : 'agreements.php?scope=mine',
                agreementAction ? 'is-danger' : 'is-clear',
                'agreement'
            ),
            priority(
                'Initiatives',
                initiativeAction,
                'Drafts, returns, or approved requests ready for your next step',
                'initiative-workflow.php',
                initiativeAction ? 'is-danger' : 'is-clear',
                'initiative'
            ),
            priority(
                'In review',
                totalInReview,
                'Agreements and Initiatives moving through their approval routes',
                inReviewHref
            ),
            priority(
                'Reports & updates',
                reportsAndUpdates,
                'Overdue Agreement reports and unread Initiative notifications',
                reportsAndUpdatesHref,
                reportsAndUpdates ? 'is-warning' : 'is-clear'
            )
        );
    }

    function requestPriority(item) {
        const status = normalizedStatus(item.status);
        if (status === 'REVISION_REQUIRED') return 0;
        if (status === 'DRAFT') return 1;
        if (item.can_convert) return 2;
        if (['UNDER_REVIEW', 'RESUBMITTED'].includes(status)) return 3;
        return 4;
    }

    function renderWork(overview, summary) {
        const { workItem, emptyItem } = overview.helpers;
        elements.workList.replaceChildren();
        const sorted = [...summary.requests].sort((left, right) =>
            requestPriority(left) - requestPriority(right)
        );

        sorted.slice(0, 5).forEach((item) => {
            const status = String(item.status || 'Open').replaceAll('_', ' ');
            const row = workItem(
                item.title || item.request_code || 'Initiative request',
                `${item.request_code || 'Initiative request'} · ${requestDetail(item)}`,
                status,
                `initiative-workflow.php?view=detail&id=${encodeURIComponent(item.request_id)}`,
                'initiative'
            );
            row.addEventListener('click', (event) => {
                if (event.target.closest('a, button')) return;
                window.location.assign(
                    `initiative-workflow.php?view=detail&id=${encodeURIComponent(item.request_id)}`
                );
            });
            row.style.cursor = 'pointer';
            elements.workList.append(row);
        });

        if (!sorted.length) {
            elements.workList.append(emptyItem(
                summary.canCreate
                    ? 'No Initiative requests yet. Start a request when you are ready.'
                    : 'No Initiative work is visible to your account yet.'
            ));
        }
    }

    function showUnavailable(overview, error) {
        const { moduleMetric, emptyItem } = overview.helpers;
        elements.metrics.replaceChildren(moduleMetric(
            '—',
            'Initiative data unavailable',
            'Agreement information remains available on this overview',
            'is-warning'
        ));
        elements.workList.replaceChildren(emptyItem(
            'Initiative work could not be loaded. Open the Initiative module to try again.'
        ));
        console.error('Initiative overview integration failed:', error);
    }

    async function initialize() {
        const overview = await waitForOverview();

        try {
            const [access, requestPayload, unreadPayload, initiativePayload] =
                await Promise.all([
                    AgreementApi.request('/initiative-access'),
                    AgreementApi.request('/initiative-requests'),
                    AgreementApi.request(
                        '/initiative-requests/notifications/unread-count'
                    ),
                    AgreementApi.request(
                        '/initiative-requests/converted-initiatives'
                    )
                ]);

            const requests = requestItems(requestPayload);
            const finalInitiatives = requestItems(initiativePayload);
            const unreadCount = Number(unreadPayload?.unread_count || 0);
            const underReview = requests.filter((item) =>
                ['UNDER_REVIEW', 'RESUBMITTED'].includes(
                    normalizedStatus(item.status)
                )
            ).length;
            const attentionRows = requests.filter((item) =>
                ['DRAFT', 'REVISION_REQUIRED'].includes(
                    normalizedStatus(item.status)
                ) || item.can_convert === true
            );
            const summary = {
                requests,
                finalInitiatives,
                unreadCount,
                underReview,
                attentionRows,
                canCreate: access.can_create_initiative === true
            };

            overview.initiative = summary;
            renderActions(overview, access, unreadCount);
            renderMetrics(overview, summary);
            renderPriorities(overview, summary);
            renderWork(overview, summary);
        } catch (error) {
            showUnavailable(overview, error);
        }
    }

    initialize();
}());
