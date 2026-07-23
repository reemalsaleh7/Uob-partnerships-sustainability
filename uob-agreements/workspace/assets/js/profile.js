(function () {
    'use strict';

    const loading = document.querySelector('[data-profile-loading]');
    const content = document.querySelector('[data-profile-content]');
    const alert = document.querySelector('[data-profile-alert]');

    function text(name, value) {
        const element = document.querySelector(`[data-profile-field="${name}"]`);
        if (element) element.textContent = value || '—';
    }

    function tag(value) {
        const item = document.createElement('li');
        item.className = 'profile-tag';
        item.textContent = String(value).replaceAll('_', ' ');
        return item;
    }

    function renderList(selector, values, emptyMessage) {
        const list = document.querySelector(selector);
        list.replaceChildren();
        const items = Array.isArray(values) ? values : [];
        if (!items.length) {
            list.append(tag(emptyMessage));
            return;
        }
        list.append(...items.map(tag));
    }

    function renderPositions(positions) {
        const container = document.querySelector('[data-profile-positions]');
        container.replaceChildren();
        const items = Array.isArray(positions) ? positions : [];
        if (!items.length) {
            const empty = document.createElement('div');
            empty.className = 'dashboard-empty';
            empty.textContent = 'No active organizational position is assigned.';
            container.append(empty);
            return;
        }
        const list = document.createElement('ul');
        list.className = 'dashboard-list';
        items.forEach((position) => {
            const item = document.createElement('li');
            item.className = 'dashboard-list-item';
            const copy = document.createElement('div');
            const title = document.createElement('strong');
            title.textContent = position.position || 'Position';
            const unit = document.createElement('small');
            unit.textContent = position.organizational_unit || 'University of Bahrain';
            copy.append(title, unit);
            item.append(copy);
            list.append(item);
        });
        container.append(list);
    }

    (async function initialize() {
        try {
            const user = await AgreementApi.requireSession();
            document.querySelector('[data-profile-initials]').textContent = AgreementApi.initials(user);
            document.querySelector('[data-profile-name]').textContent = AgreementApi.displayName(user);
            document.querySelector('[data-profile-email]').textContent = user.email || '—';
            text('university_id', user.university_id);
            text('phone', user.phone);
            text('last_login', AgreementApi.formatDate(user.last_login));
            text('account_created_at', AgreementApi.formatDate(user.account_created_at));
            text('password_changed_at', AgreementApi.formatDate(user.password_changed_at));
            renderPositions(user.positions);
            renderList('[data-profile-roles]', user.roles, 'No assigned business role');
            renderList('[data-profile-permissions]', user.permissions, 'No operational permissions');
            loading.classList.add('d-none');
            content.classList.remove('d-none');
        } catch (error) {
            loading.classList.add('d-none');
            alert.textContent = error.message || 'Your profile could not be loaded.';
            alert.classList.remove('d-none');
            alert.focus();
        }
    })();
})();
