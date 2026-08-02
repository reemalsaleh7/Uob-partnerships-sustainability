(function () {
    'use strict';

    const tableBody = document.getElementById(
        'agreement-table-body'
    );

    if (!tableBody) {
        return;
    }

    const eligibleStatusText = new Set([
        'ACTIVE',
        'APPROVED',
        'نشط',
        'معتمد'
    ]);

    let canCreateInitiative = false;

    function requestUrl(agreementId) {
        return (
            'initiative-workflow.php'
            + '?view=form'
            + '&agreement_id='
            + encodeURIComponent(agreementId)
        );
    }

    function rowAgreementId(row) {
        const firstCell = row?.querySelector('td');

        if (!firstCell) {
            return '';
        }

        const match = String(firstCell.textContent || '')
            .match(/\d+/);

        return match ? match[0] : '';
    }

    function agreementIdFromLink(link) {
        const raw = link?.dataset?.legacyInitiative
            || link?.getAttribute('href')
            || '';

        try {
            const parsed = new URL(raw, window.location.href);
            return parsed.searchParams.get('agreement_id') || '';
        } catch (error) {
            return '';
        }
    }

    function eligibleStatus(row) {
        const badge = row.querySelector('.status-badge');

        if (!badge) {
            return false;
        }

        if (
            badge.classList.contains('status-active')
            || badge.classList.contains('status-approved')
        ) {
            return true;
        }

        const label = String(badge.textContent || '')
            .replace(/_/g, ' ')
            .replace(/\s+/g, ' ')
            .trim()
            .toUpperCase();

        return eligibleStatusText.has(label)
            || eligibleStatusText.has(
                String(badge.textContent || '').trim()
            );
    }

    function actionContainer(row) {
        return row.querySelector('.agreement-row-actions')
            || row.querySelector('td:last-child > div');
    }

    function configureExistingLink(row) {
        const link = row.querySelector(
            '[data-legacy-initiative],'
            + ' a[href*="request-initiative.php"]'
        );

        if (!link) {
            return false;
        }

        const agreementId = agreementIdFromLink(link)
            || rowAgreementId(row);

        if (!agreementId) {
            return false;
        }

        link.href = requestUrl(agreementId);
        link.removeAttribute('data-legacy-initiative');
        link.dataset.initiativeRequestLink = 'true';
        link.textContent = 'Use for Initiative';

        if (typeof window.workspaceTranslateNode === 'function') {
            window.workspaceTranslateNode(link);
        }

        return true;
    }

    function addApprovedLink(row) {
        if (
            !canCreateInitiative
            || !eligibleStatus(row)
            || configureExistingLink(row)
            || row.querySelector(
                '[data-initiative-request-link],'
                + ' a[href*="initiative-workflow.php"]'
                + '[href*="view=form"]'
                + '[href*="agreement_id="]'
            )
        ) {
            return;
        }

        const agreementId = rowAgreementId(row);
        const actions = actionContainer(row);

        if (!agreementId || !actions) {
            return;
        }

        const link = document.createElement('a');
        link.className = 'btn btn-sm btn-primary';
        link.href = requestUrl(agreementId);
        link.textContent = 'Use for Initiative';
        link.dataset.initiativeRequestLink = 'true';
        actions.append(link);

        if (typeof window.workspaceTranslateNode === 'function') {
            window.workspaceTranslateNode(link);
        }
    }

    function refreshRows() {
        tableBody.querySelectorAll('tr').forEach((row) => {
            configureExistingLink(row);
            addApprovedLink(row);
        });
    }

    /*
     * Capture old legacy links before sidebar-initiative.js can send
     * them to the removed request-initiative.php route.
     */
    document.addEventListener('click', (event) => {
        const link = event.target.closest(
            '#agreement-table-body '
            + '[data-legacy-initiative],'
            + '#agreement-table-body '
            + 'a[href*="request-initiative.php"]'
        );

        if (!link) {
            return;
        }

        const row = link.closest('tr');
        const agreementId = agreementIdFromLink(link)
            || rowAgreementId(row);

        if (!agreementId) {
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();
        window.location.assign(requestUrl(agreementId));
    }, true);

    let refreshQueued = false;

    const observer = new MutationObserver(() => {
        if (refreshQueued) {
            return;
        }

        refreshQueued = true;

        window.requestAnimationFrame(() => {
            refreshQueued = false;
            refreshRows();
        });
    });

    observer.observe(tableBody, {
        childList: true,
        subtree: false
    });

    (async function initialize() {
        try {
            const user = await AgreementApi.requireSession();

            canCreateInitiative = (
                AgreementApi.hasPermission(
                    user,
                    'CREATE_INITIATIVE'
                )
                || (
                    Array.isArray(user.roles)
                    && user.roles.includes(
                        'Initiative Creator'
                    )
                )
            );
        } catch (error) {
            canCreateInitiative = false;
        }

        refreshRows();
    }());
}());
