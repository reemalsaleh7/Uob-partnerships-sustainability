(function (global) {
    'use strict';

    const modalElement = document.getElementById('workspace-confirm-modal');
    const messageElement = modalElement?.querySelector('[data-dialog-message]');
    const titleElement = modalElement?.querySelector('[data-dialog-title]');
    const confirmButton = modalElement?.querySelector('[data-dialog-confirm]');
    const modal = modalElement && global.bootstrap
        ? new global.bootstrap.Modal(modalElement)
        : null;

    function confirm(message, options = {}) {
        if (!modal) return Promise.resolve(global.confirm(message));
        titleElement.textContent = options.title || 'Confirm action';
        messageElement.textContent = message;
        confirmButton.textContent = options.confirmLabel || 'Confirm';
        confirmButton.className = options.danger
            ? 'btn btn-danger'
            : 'btn btn-primary';

        return new Promise((resolve) => {
            let settled = false;
            const finish = (value) => {
                if (settled) return;
                settled = true;
                resolve(value);
            };
            const approve = () => {
                finish(true);
                modal.hide();
            };
            const hidden = () => {
                confirmButton.removeEventListener('click', approve);
                modalElement.removeEventListener('hidden.bs.modal', hidden);
                finish(false);
            };
            confirmButton.addEventListener('click', approve, { once: true });
            modalElement.addEventListener('hidden.bs.modal', hidden, { once: true });
            modal.show();
        });
    }

    global.WorkspaceDialog = Object.freeze({ confirm });
})(window);
