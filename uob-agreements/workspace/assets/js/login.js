(function () {
    'use strict';

    const form = document.getElementById('login-form');
    const alert = document.getElementById('login-alert');
    const button = document.getElementById('login-button');
    const label = button.querySelector('[data-button-label]');
    const spinner = button.querySelector('[data-button-spinner]');

    function setBusy(isBusy) {
        button.disabled = isBusy;
        label.textContent = isBusy ? 'Signing in…' : 'Sign in';
        spinner.classList.toggle('d-none', !isBusy);
    }

    function showError(message) {
        alert.textContent = message;
        alert.classList.remove('d-none');
    }

    AgreementApi.me()
        .then(() => {
            window.location.replace(AgreementApi.safeReturnPath());
        })
        .catch(() => {
            // Remaining on the login page is correct when no session exists.
        });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        event.stopPropagation();
        alert.classList.add('d-none');
        form.classList.add('was-validated');

        if (!form.checkValidity()) {
            return;
        }

        setBusy(true);

        try {
            await AgreementApi.login(
                form.elements.email.value.trim(),
                form.elements.password.value
            );

            window.location.replace(AgreementApi.safeReturnPath());
        } catch (error) {
            showError(error.message || 'Sign-in failed.');
            setBusy(false);
        }
    });
})();

