(function () {
    'use strict';

    const badge = document.querySelector(
        '[data-initiative-notification-count]'
    );

    if (!badge || typeof AgreementApi === 'undefined') {
        return;
    }

    async function loadUnreadCount() {
        try {
            await AgreementApi.requireSession();
            const payload = await AgreementApi.request(
                '/initiative-requests/notifications/unread-count'
            );
            const count = Number(payload?.unread_count || 0);

            if (!Number.isFinite(count) || count < 1) {
                badge.textContent = '';
                badge.classList.add('d-none');
                return;
            }

            badge.textContent = count > 99 ? '99+' : String(count);
            badge.classList.remove('d-none');
        } catch (error) {
            badge.textContent = '';
            badge.classList.add('d-none');
        }
    }

    loadUnreadCount();
}());
