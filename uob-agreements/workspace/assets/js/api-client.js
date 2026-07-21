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
    const tabSessionStorageKey = 'uob-agreement-tab-session';
    let volatileTabSessionId = null;

    function newTabSessionId() {
        const bytes = new Uint8Array(32);
        global.crypto.getRandomValues(bytes);

        return Array.from(
            bytes,
            (byte) => byte.toString(16).padStart(2, '0')
        ).join('');
    }

    function tabSessionId() {
        if (volatileTabSessionId) {
            return volatileTabSessionId;
        }

        try {
            volatileTabSessionId = global.sessionStorage.getItem(
                tabSessionStorageKey
            );
        } catch (error) {
            // The in-memory fallback still supports the current page load.
        }

        if (!volatileTabSessionId) {
            volatileTabSessionId = newTabSessionId();
            saveTabSessionId(volatileTabSessionId);
        }

        return volatileTabSessionId;
    }

    function saveTabSessionId(sessionId) {
        volatileTabSessionId = sessionId;

        try {
            global.sessionStorage.setItem(
                tabSessionStorageKey,
                sessionId
            );
        } catch (error) {
            // Some privacy modes disable storage; retain an in-memory session.
        }
    }

    function clearTabSession() {
        volatileTabSessionId = null;

        try {
            global.sessionStorage.removeItem(tabSessionStorageKey);
        } catch (error) {
            // There is nothing else to clear when storage is unavailable.
        }
    }

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
        headers.set('X-UOB-Tab-Session', tabSessionId());

        if (options.body !== undefined && !headers.has('Content-Type')) {
            headers.set('Content-Type', 'application/json');
        }

        let response;

        try {
            response = await fetch(`${apiBase}${path}`, {
                ...options,
                headers,
                cache: 'no-store',
                credentials: 'same-origin'
            });
        } catch (error) {
            throw new ApiError(
                'The server could not be reached. Check Apache and your network connection.',
                0,
                null
            );
        }

        const responseTabSessionId = response.headers.get(
            'X-UOB-Tab-Session'
        );

        if (responseTabSessionId) {
            saveTabSessionId(responseTabSessionId);
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

        const allowedPath = /^(agreements|agreement|agreement-form|workflow-inbox|workflow-review|legal-review|finance-review|vp-review)\.php(?:\?[A-Za-z0-9_=&%.-]*)?$/;

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

        const canReview = hasPermission(user, 'APPROVE_AGREEMENT')
            || hasPermission(user, 'REJECT_AGREEMENT');

        document.querySelectorAll('[data-workflow-nav]').forEach((element) => {
            element.classList.toggle('d-none', !canReview);
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
                    clearTabSession();
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
        resubmitAgreement(id, data = {}) {
            return request(`/agreements/${encodeURIComponent(id)}/resubmit`, {
                method: 'POST',
                body: jsonBody(data)
            });
        },
        versions(id) {
            return request(`/agreements/${encodeURIComponent(id)}/versions`);
        },
        workflowInbox() {
            return request('/workflow-inbox');
        },
        approveInitialVp(instanceId, data) {
            return request(
                `/workflow-instances/${encodeURIComponent(instanceId)}/initial-vp/approve`,
                {
                    method: 'POST',
                    body: jsonBody(data)
                }
            );
        },
        approveSpecialist(instanceId, data) {
            return request(
                `/workflow-instances/${encodeURIComponent(instanceId)}/specialist/approve`,
                {
                    method: 'POST',
                    body: jsonBody(data)
                }
            );
        },
        requestChanges(instanceId, data) {
            return request(
                `/workflow-instances/${encodeURIComponent(instanceId)}/changes/request`,
                {
                    method: 'POST',
                    body: jsonBody(data)
                }
            );
        },
        approveFinalVp(instanceId, data) {
            return request(
                `/workflow-instances/${encodeURIComponent(instanceId)}/final-vp/approve`,
                {
                    method: 'POST',
                    body: jsonBody(data)
                }
            );
        },
        routeByVp(instanceId, data) {
            return request(
                `/workflow-instances/${encodeURIComponent(instanceId)}/vp/route`,
                {
                    method: 'POST',
                    body: jsonBody(data)
                }
            );
        },
        decideVp(instanceId, data) {
            return request(
                `/workflow-instances/${encodeURIComponent(instanceId)}/vp/decide`,
                {
                    method: 'POST',
                    body: jsonBody(data)
                }
            );
        }
    });
})(window);
