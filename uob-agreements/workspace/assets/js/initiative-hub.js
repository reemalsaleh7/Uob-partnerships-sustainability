(function () {
    'use strict';

    (async function initialize() {
        const createLink = document.querySelector('[data-create-initiative]');
        const accessLabel = document.querySelector('[data-initiative-access]');

        try {
            const user = await AgreementApi.requireSession();

            const canCreate =
                AgreementApi.hasPermission(user, 'CREATE_INITIATIVE')
                || (
                    Array.isArray(user.roles)
                    && user.roles.includes('Initiative Creator')
                );

            if (createLink) {
                createLink.classList.toggle('d-none', !canCreate);
                createLink.href =
                    'initiative-module/initiative-create.php';
            }

            if (accessLabel) {
                accessLabel.textContent = canCreate
                    ? 'You are authorized to propose initiatives'
                    : 'You can explore initiatives; creation is not assigned to your role';
            }
        } catch (error) {
            console.error('Initiative hub error:', error);

            if (accessLabel) {
                accessLabel.textContent =
                    'Initiative access could not be checked';
            }
        }
    })();
})();