(function () {
    'use strict';

    async function initializeInitiativeHub() {
        const createInitiativeElements = document.querySelectorAll(
            '[data-create-initiative]'
        );

        const initiativeAccessElement = document.querySelector(
            '[data-initiative-access]'
        );

        try {
            const user = await AgreementApi.requireSession();

            const canCreateInitiative = AgreementApi.hasPermission(
                user,
                'CREATE_INITIATIVE'
            );

            createInitiativeElements.forEach((element) => {
                element.classList.toggle(
                    'd-none',
                    !canCreateInitiative
                );

                element.setAttribute(
                    'aria-hidden',
                    canCreateInitiative ? 'false' : 'true'
                );
            });

            if (initiativeAccessElement) {
                initiativeAccessElement.textContent =
                    canCreateInitiative
                        ? 'You are authorized to propose initiatives'
                        : 'You can review initiatives assigned to your role';
            }
        } catch (error) {
            createInitiativeElements.forEach((element) => {
                element.classList.add('d-none');
                element.setAttribute('aria-hidden', 'true');
            });

            if (initiativeAccessElement) {
                initiativeAccessElement.textContent =
                    'Initiative access could not be checked';
            }

            console.error(
                'Initiative hub initialization failed:',
                error
            );
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener(
            'DOMContentLoaded',
            initializeInitiativeHub
        );
    } else {
        initializeInitiativeHub();
    }
})();