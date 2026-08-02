(function () {
    'use strict';

    const actionContainer = document.querySelector(
        '[data-dashboard-actions]'
    );
    const dashboardContent = document.querySelector(
        '[data-dashboard-content]'
    );

    if (!actionContainer || !dashboardContent) {
        return;
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

    function requestItems(payload) {
        return Array.isArray(payload)
            ? payload
            : (payload?.items || []);
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
            REVISION_REQUIRED: 'Changes requested — revise and resubmit.',
            APPROVED: 'Approved — ready to convert.',
            CONVERTING: 'Conversion draft in progress.',
            CONVERTED: 'Converted to a final Initiative.',
            REJECTED: 'Request rejected.'
        };

        return messages[normalizedStatus(item.status)]
            || 'Open the request to view its progress.';
    }

    function buildRequestRow(item) {
        const row = document.createElement('li');
        row.className = 'dashboard-list-item';

        const copy = document.createElement('div');
        const title = document.createElement('strong');
        title.textContent = item.title || item.request_code;
        const detail = document.createElement('small');
        detail.textContent = `${item.request_code || 'Initiative request'} · ${requestDetail(item)}`;
        copy.append(title, detail);

        const open = document.createElement('a');
        open.className = 'btn btn-sm btn-outline-primary align-self-center';
        open.href = `initiative-workflow.php?view=detail&id=${
            encodeURIComponent(item.request_id)
        }`;
        open.textContent = String(item.status || 'Open')
            .replaceAll('_', ' ');

        row.append(copy, open);
        row.addEventListener('click', (event) => {
            if (event.target.closest('a, button')) {
                return;
            }
            window.location.assign(open.href);
        });
        row.style.cursor = 'pointer';
        return row;
    }

    function integrateActions(user, access, unreadCount) {
        const existing = Array.from(actionContainer.children);
        const retained = existing.filter((card) => {
            const title = card.querySelector('strong')?.textContent || '';
            return ![
                'Start an initiative',
                'Initiative hub',
                'My profile'
            ].includes(title);
        });

        const integrated = [];

        if (access.can_create_initiative) {
            integrated.push(action(
                'Start an Initiative request',
                'Create a draft, add collaborators, and submit it through the University approval route.',
                'initiative-workflow.php?view=form',
                'Start request'
            ));
        }

        integrated.push(action(
            'Initiative requests',
            'Open requests you created, joined, review, or administer.',
            'initiative-workflow.php',
            'Open requests'
        ));

        integrated.push(action(
            'Initiative notifications',
            unreadCount > 0
                ? `${unreadCount} unread Initiative update${unreadCount === 1 ? '' : 's'}.`
                : 'Approval assignments, revisions, decisions, and reminders.',
            'initiative-workflow.php?view=notifications',
            unreadCount > 0 ? `View ${unreadCount} unread` : 'Open notifications'
        ));

        integrated.push(action(
            'Final Initiatives',
            'Open approved requests that were converted into final Initiative records.',
            'initiative-portfolio.php',
            'Open portfolio'
        ));

        retained.forEach((card) => {
            if (integrated.length < 5) {
                integrated.push(card);
            }
        });

        integrated.push(action(
            'My profile',
            'Review your position, role, and system access.',
            'profile.php',
            'View profile'
        ));

        actionContainer.replaceChildren(...integrated.slice(0, 6));
    }

    function addInitiativeSection(
        requests,
        initiatives,
        unreadCount
    ) {
        document.querySelector(
            '[data-dashboard-initiative-section]'
        )?.remove();

        const section = document.createElement('section');
        section.dataset.dashboardInitiativeSection = 'true';
        section.className = 'mt-5';

        const heading = document.createElement('div');
        heading.className = 'dashboard-section-title';
        const copy = document.createElement('div');
        const title = document.createElement('h2');
        title.textContent = 'Initiative workflow';
        const description = document.createElement('p');
        description.textContent = 'Your requests, decisions, notifications, and converted Initiatives in one place.';
        copy.append(title, description);
        const all = document.createElement('a');
        all.href = 'initiative-workflow.php';
        all.textContent = 'View all requests';
        heading.append(copy, all);

        const underReview = requests.filter((item) =>
            ['UNDER_REVIEW', 'RESUBMITTED'].includes(
                normalizedStatus(item.status)
            )
        ).length;
        const revision = requests.filter((item) =>
            normalizedStatus(item.status) === 'REVISION_REQUIRED'
        ).length;

        const kpis = document.createElement('div');
        kpis.className = 'dashboard-kpi-grid mb-4';
        kpis.append(
            kpi(requests.length, 'Visible requests', 'Created, joined, reviewed, or administered by you'),
            kpi(underReview, 'Under review', 'Currently moving through approval'),
            kpi(revision, 'Revision required', revision ? 'Requests waiting for changes' : 'No revision work waiting'),
            kpi(unreadCount, 'Unread updates', unreadCount ? 'Open notifications for details' : 'You are up to date')
        );

        const workHeading = document.createElement('div');
        workHeading.className = 'dashboard-section-title';
        const workCopy = document.createElement('div');
        const workTitle = document.createElement('h2');
        workTitle.textContent = 'Recent Initiative requests';
        const workDescription = document.createElement('p');
        workDescription.textContent = 'Open any row to continue the exact workflow where it stopped.';
        workCopy.append(workTitle, workDescription);
        const portfolio = document.createElement('a');
        portfolio.href = 'initiative-portfolio.php';
        portfolio.textContent = `Final Initiatives (${initiatives.length})`;
        workHeading.append(workCopy, portfolio);

        const card = document.createElement('section');
        card.className = 'workspace-card';
        const list = document.createElement('ul');
        list.className = 'dashboard-list';

        requests.slice(0, 6).forEach((item) => {
            list.append(buildRequestRow(item));
        });

        if (requests.length === 0) {
            const empty = document.createElement('li');
            empty.className = 'dashboard-empty';
            empty.textContent = 'No Initiative requests are visible to your account yet.';
            list.append(empty);
        }

        card.append(list);
        section.append(heading, kpis, workHeading, card);

        const actionsSection = actionContainer;
        actionsSection.insertAdjacentElement('afterend', section);
    }

    function connectAgreementUseButtons() {
        document.querySelectorAll(
            '[data-legacy-initiative]'
        ).forEach((link) => {
            const legacyTarget = String(
                link.dataset.legacyInitiative || ''
            );
            const legacyUrl = new URL(
                legacyTarget,
                'http://uob.local/'
            );
            const agreementId = Number(
                legacyUrl.searchParams.get('agreement_id')
            );

            if (
                !Number.isInteger(agreementId)
                || agreementId < 1
            ) {
                return;
            }

            delete link.dataset.legacyInitiative;
            link.removeAttribute('data-legacy-initiative');
            link.href =
                `initiative-workflow.php?view=form&agreement_id=${
                    encodeURIComponent(agreementId)
                }`;
            link.title =
                'Create an Initiative request using this Agreement';
        });
    }

    async function waitForDashboard() {
        const startedAt = Date.now();
        while (
            dashboardContent.classList.contains('d-none')
            && Date.now() - startedAt < 5000
        ) {
            await new Promise((resolve) => setTimeout(resolve, 80));
        }
    }

    async function initialize() {
        try {
            const user = await AgreementApi.requireSession();
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

            await waitForDashboard();
            connectAgreementUseButtons();

            const requests = requestItems(requestPayload);
            const initiatives = requestItems(initiativePayload);
            const unreadCount = Number(
                unreadPayload?.unread_count || 0
            );

            integrateActions(user, access, unreadCount);
            addInitiativeSection(
                requests,
                initiatives,
                unreadCount
            );
        } catch (error) {
            console.error(
                'Initiative dashboard integration failed:',
                error
            );
        }
    }

    initialize();
}());
