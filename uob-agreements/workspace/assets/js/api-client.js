(function (global) {
    'use strict';

    function applicationRoot() {
        const path = global.location.pathname.replace(/\\/g, '/');
        const marker = '/uob-agreements/workspace/';
        const markerIndex = path.toLowerCase().indexOf(marker);

        if (markerIndex < 0) {
            return '';
        }

        return path.slice(0, markerIndex);
    }

    const apiBase = `${applicationRoot()}/api/index.php`;

    class ApiError extends Error {
        constructor(message, status, payload) {
            super(message);
            this.name = 'ApiError';
            this.status = status;
            this.payload = payload;
        }
    }

    async function request(path, options = {}) {
        const headers = new Headers(options.headers || {});
        headers.set('Accept', 'application/json');

        if (options.body !== undefined && !headers.has('Content-Type')) {
            headers.set('Content-Type', 'application/json');
        }

        let response;

        try {
            response = await fetch(`${apiBase}${path}`, {
                ...options,
                headers,
                credentials: 'same-origin'
            });
        } catch (error) {
            throw new ApiError(
                'The server could not be reached. Check Apache and your network connection.',
                0,
                null
            );
        }

        const rawBody = await response.text();
        let payload = null;

        if (rawBody !== '') {
            try {
                payload = JSON.parse(rawBody);
            } catch (error) {
                throw new ApiError(
                    'The server returned an invalid response.',
                    response.status,
                    rawBody
                );
            }
        }

        if (!response.ok || payload?.success === false) {
            throw new ApiError(
                payload?.error || `Request failed with status ${response.status}.`,
                response.status,
                payload
            );
        }

        return payload?.data ?? payload;
    }

    function jsonBody(value) {
        return JSON.stringify(value);
    }

    function safeReturnPath(defaultPath = 'agreements.php') {
        const value = new URLSearchParams(global.location.search).get('to');

        if (!value) {
            return defaultPath;
        }

        const allowedPath = /^(agreements|agreement|agreement-form)\.php(?:\?[A-Za-z0-9_=&%.-]*)?$/;

        return allowedPath.test(value)
            ? value
            : defaultPath;
    }

    function loginPath() {
        const current = `${global.location.pathname.split('/').pop()}${global.location.search}`;
        return `login.php?to=${encodeURIComponent(current)}`;
    }

    function hasPermission(user, permission) {
        return Array.isArray(user?.permissions)
            && user.permissions.includes(permission);
    }

    function displayName(user) {
        return user?.full_name || user?.email || 'Signed-in user';
    }

    async function requireSession(permission = null) {
        try {
            const user = await request('/me');

            if (permission && !hasPermission(user, permission)) {
                throw new ApiError(
                    'You do not have permission to view this page.',
                    403,
                    null
                );
            }

            bindSessionControls(user);
            return user;
        } catch (error) {
            if (error instanceof ApiError && error.status === 401) {
                global.location.replace(loginPath());
                return new Promise(() => {});
            }

            throw error;
        }
    }

    function bindSessionControls(user) {
        document.querySelectorAll('[data-session-panel]').forEach((element) => {
            element.classList.remove('d-none');
            element.classList.add('d-flex');
        });

        document.querySelectorAll('[data-user-name]').forEach((element) => {
            element.textContent = displayName(user);
        });

        document.querySelectorAll('[data-logout]').forEach((button) => {
            if (button.dataset.bound === 'true') {
                return;
            }

            button.dataset.bound = 'true';
            button.addEventListener('click', async () => {
                button.disabled = true;

                try {
                    await request('/logout', { method: 'POST' });
                } finally {
                    global.location.replace('login.php');
                }
            });
        });
    }

    function formatDate(value) {
        if (!value) {
            return '—';
        }

        const normalized = String(value).includes('T')
            ? String(value)
            : String(value).replace(' ', 'T');
        const date = new Date(normalized);

        if (Number.isNaN(date.getTime())) {
            return String(value);
        }

        return new Intl.DateTimeFormat('en-BH', {
            dateStyle: 'medium',
            timeStyle: 'short'
        }).format(date);
    }

    function statusClass(status) {
        const normalized = String(status || '').toUpperCase();
        const classes = {
            DRAFT: 'status-draft',
            UNDER_REVIEW: 'status-review',
            REVISION_REQUIRED: 'status-revision',
            APPROVED: 'status-approved',
            ACTIVE: 'status-active',
            REJECTED: 'status-rejected',
            EXPIRED: 'status-expired',
            TERMINATED: 'status-terminated'
        };

        return classes[normalized] || 'status-default';
    }

    function createStatusBadge(status) {
        const badge = document.createElement('span');
        badge.className = `status-badge ${statusClass(status)}`;
        badge.textContent = String(status || 'Unknown').replaceAll('_', ' ');
        return badge;
    }

    global.AgreementApi = Object.freeze({
        ApiError,
        apiBase,
        request,
        jsonBody,
        safeReturnPath,
        hasPermission,
        displayName,
        requireSession,
        formatDate,
        createStatusBadge,
        login(email, password) {
            return request('/login', {
                method: 'POST',
                body: jsonBody({ email, password })
            });
        },
        me() {
            return request('/me');
        },
        agreements() {
            return request('/agreements');
        },
        partners() {
            return request('/partners');
        },
        agreement(id) {
            return request(`/agreements/${encodeURIComponent(id)}`);
        },
        createAgreement(data) {
            return request('/agreements', {
                method: 'POST',
                body: jsonBody(data)
            });
        },
        updateAgreement(id, data) {
            return request(`/agreements/${encodeURIComponent(id)}`, {
                method: 'PUT',
                body: jsonBody(data)
            });
        },
        submitAgreement(id) {
            return request(`/agreements/${encodeURIComponent(id)}/submit`, {
                method: 'POST'
            });
        },
        versions(id) {
            return request(`/agreements/${encodeURIComponent(id)}/versions`);
        }
    });
})(window);
