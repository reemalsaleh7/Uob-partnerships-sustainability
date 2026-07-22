(function () {
    'use strict';

    (async function initialize() {
        try {
            const user = await AgreementApi.requireSession();
            const canCreate = AgreementApi.hasPermission(user, 'CREATE_INITIATIVE')
                || (Array.isArray(user.roles) && user.roles.includes('Initiative Creator'));
            document.querySelector('[data-create-initiative]').classList.toggle('d-none', !canCreate);
            document.querySelector('[data-initiative-access]').textContent = canCreate
                ? 'You are authorized to propose initiatives'
                : 'You can explore initiatives; creation is not assigned to your role';
        } catch (error) {
            document.querySelector('[data-initiative-access]').textContent = 'Initiative access could not be checked';
        }
    })();
})();
