(function () {
    'use strict';

    const badge = document.querySelector(
        '[data-initiative-notification-count]'
    );
    const monitoringNav = document.querySelector(
        '[data-initiative-monitoring-nav]'
    );

    if (
        (!badge && !monitoringNav)
        || typeof AgreementApi === 'undefined'
    ) {
        return;
    }

    function hideBadge() {
        if (!badge) {
            return;
        }

        badge.textContent = '';
        badge.classList.add('d-none');
    }

    async function loadSidebarInitiativeState() {
        try {
            await AgreementApi.requireSession();

            if (badge) {
                try {
                    const payload = await AgreementApi.request(
                        '/initiative-requests/notifications/unread-count'
                    );
                    const count = Number(payload?.unread_count || 0);

                    if (!Number.isFinite(count) || count < 1) {
                        hideBadge();
                    } else {
                        badge.textContent =
                            count > 99 ? '99+' : String(count);
                        badge.classList.remove('d-none');
                    }
                } catch (error) {
                    hideBadge();
                }
            }

            if (monitoringNav) {
                try {
                    const access = await AgreementApi.request(
                        '/initiative-access'
                    );

                    monitoringNav.classList.toggle(
                        'd-none',
                        !access?.can_administer_initiatives
                    );
                } catch (error) {
                    monitoringNav.classList.add('d-none');
                }
            }
        } catch (error) {
            hideBadge();
            monitoringNav?.classList.add('d-none');
        }
    }

    loadSidebarInitiativeState();
}());