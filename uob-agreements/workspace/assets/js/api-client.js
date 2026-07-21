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

        const isFormData = typeof FormData !== 'undefined'
            && options.body instanceof FormData;

        if (
            options.body !== undefined
            && !isFormData
            && !headers.has('Content-Type')
        ) {
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

    async function download(path) {
        const headers = new Headers();
        headers.set('Accept', 'application/octet-stream');
        headers.set('X-UOB-Tab-Session', tabSessionId());

        let response;

        try {
            response = await fetch(`${apiBase}${path}`, {
                method: 'GET',
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

        if (!response.ok) {
            const rawBody = await response.text();
            let payload = null;

            try {
                payload = rawBody === '' ? null : JSON.parse(rawBody);
            } catch (error) {
                payload = null;
            }

            throw new ApiError(
                payload?.error || `Download failed with status ${response.status}.`,
                response.status,
                payload
            );
        }

        return response.blob();
    }

    function jsonBody(value) {
        return JSON.stringify(value);
    }

    function safeReturnPath(defaultPath = 'index.php') {
        const value = new URLSearchParams(global.location.search).get('to');

        if (!value) {
            return defaultPath;
        }

        const allowedPath = /^(index|profile|initiative-hub|agreements|agreement|agreement-form|workflow-inbox|workflow-review|legal-review|finance-review|vp-review|president-review|lifecycle-requests|lifecycle-form|lifecycle-request|lifecycle-review|performance-reports|performance-report|performance-dashboard)\.php(?:\?[A-Za-z0-9_=&%.-]*)?$/;

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

    function initials(user) {
        const name = String(displayName(user)).trim();
        const words = name.split(/\s+/).filter(Boolean);

        if (words.length === 0) {
            return 'U';
        }

        return words.slice(0, 2).map((word) => word[0]).join('').toUpperCase();
    }

    function primaryContext(user) {
        const position = Array.isArray(user?.positions)
            ? user.positions[0]
            : null;

        if (position?.position && position?.organizational_unit) {
            return `${position.position} · ${position.organizational_unit}`;
        }

        if (Array.isArray(user?.roles) && user.roles.length > 0) {
            return user.roles[0];
        }

        return 'University account';
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

        document.querySelectorAll('[data-user-initials]').forEach((element) => {
            element.textContent = initials(user);
        });

        document.querySelectorAll('[data-user-context]').forEach((element) => {
            element.textContent = primaryContext(user);
        });

        document.querySelectorAll('[data-agreement-nav]').forEach((element) => {
            element.classList.toggle(
                'd-none',
                !hasPermission(user, 'VIEW_AGREEMENT')
            );
        });

        const canReview = hasPermission(user, 'APPROVE_AGREEMENT')
            || hasPermission(user, 'REJECT_AGREEMENT');

        document.querySelectorAll('[data-workflow-nav]').forEach((element) => {
            element.classList.toggle('d-none', !canReview);
        });

        document.querySelectorAll('[data-lifecycle-nav]').forEach((element) => {
            element.classList.toggle(
                'd-none',
                !hasPermission(user, 'CREATE_AGREEMENT')
            );
        });

        const canUseReports = hasPermission(user, 'MANAGE_AGREEMENT_REPORTS')
            || hasPermission(user, 'REVIEW_AGREEMENT_REPORTS');
        document.querySelectorAll('[data-performance-nav]').forEach((element) => {
            element.classList.toggle('d-none', !canUseReports);
        });
        document.querySelectorAll('[data-performance-dashboard-nav]').forEach((element) => {
            element.classList.toggle(
                'd-none',
                !hasPermission(user, 'VIEW_AGREEMENT_DASHBOARD')
                    && !hasPermission(user, 'MANAGE_AGREEMENT_REPORTS')
            );
        });

        const sidebar = document.getElementById('workspaceSidebar');
        const sidebarToggle = document.querySelector('[data-sidebar-toggle]');

        if (sidebar && sidebarToggle && sidebarToggle.dataset.bound !== 'true') {
            sidebarToggle.dataset.bound = 'true';
            sidebarToggle.addEventListener('click', () => {
                const isOpen = sidebar.classList.toggle('is-open');
                sidebarToggle.setAttribute('aria-expanded', String(isOpen));
            });

            document.addEventListener('click', (event) => {
                if (
                    window.innerWidth < 992
                    && sidebar.classList.contains('is-open')
                    && !sidebar.contains(event.target)
                    && !sidebarToggle.contains(event.target)
                ) {
                    sidebar.classList.remove('is-open');
                    sidebarToggle.setAttribute('aria-expanded', 'false');
                }
            });
        }

        if (document.body.dataset.legacyHandoffBound !== 'true') {
            document.body.dataset.legacyHandoffBound = 'true';
            document.addEventListener('click', async (event) => {
                const link = event.target.closest('[data-legacy-initiative]');
                if (!link) return;

                event.preventDefault();
                if (link.dataset.handoffBusy === 'true') return;
                link.dataset.handoffBusy = 'true';
                link.setAttribute('aria-busy', 'true');

                try {
                    const handoff = await request(
                        '/legacy-initiative-handoff',
                        { method: 'POST' }
                    );
                    const target = link.dataset.legacyInitiative
                        || 'request-initiative.php?lang=en';
                    const destination = new URL('../workspace-handoff.php', global.location.href);
                    destination.searchParams.set('token', handoff.token);
                    destination.searchParams.set('to', target);
                    global.location.assign(destination.toString());
                } catch (error) {
                    link.dataset.handoffBusy = 'false';
                    link.removeAttribute('aria-busy');
                    global.alert(
                        error.message
                        || 'The Initiative portal could not be opened.'
                    );
                }
            });
        }

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
            SUBMITTED: 'status-review',
            REVISION_REQUIRED: 'status-revision',
            RETURNED: 'status-revision',
            APPROVED: 'status-approved',
            ACCEPTED: 'status-approved',
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
        initials,
        primaryContext,
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
        agreementTimeline(id) {
            return request(`/agreements/${encodeURIComponent(id)}/workflow-timeline`);
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
        documents(id) {
            return request(`/agreements/${encodeURIComponent(id)}/documents`);
        },
        uploadDocument(id, file, documentType) {
            const body = new FormData();
            body.append('file', file);
            body.append('document_type', documentType);

            return request(
                `/agreements/${encodeURIComponent(id)}/documents`,
                {
                    method: 'POST',
                    body
                }
            );
        },
        downloadDocument(id) {
            return download(
                `/documents/${encodeURIComponent(id)}/download`
            );
        },
        deleteDocument(id) {
            return request(`/documents/${encodeURIComponent(id)}`, {
                method: 'DELETE'
            });
        },
        agreementOperations(id) {
            return request(`/agreements/${encodeURIComponent(id)}/operations`);
        },
        finalizeAgreementSigning(id, data) {
            return request(`/agreements/${encodeURIComponent(id)}/signing-record`, {
                method: 'POST',
                body: jsonBody(data)
            });
        },
        performanceReports() {
            return request('/agreement-performance-reports');
        },
        agreementPerformanceReports(id) {
            return request(`/agreements/${encodeURIComponent(id)}/performance-reports`);
        },
        performanceReport(id) {
            return request(`/agreement-performance-reports/${encodeURIComponent(id)}`);
        },
        updatePerformanceReport(id, data) {
            return request(`/agreement-performance-reports/${encodeURIComponent(id)}`, {
                method: 'PUT',
                body: jsonBody(data)
            });
        },
        submitPerformanceReport(id) {
            return request(`/agreement-performance-reports/${encodeURIComponent(id)}/submit`, {
                method: 'POST'
            });
        },
        reviewPerformanceReport(id, data) {
            return request(`/agreement-performance-reports/${encodeURIComponent(id)}/review`, {
                method: 'POST',
                body: jsonBody(data)
            });
        },
        performanceDashboard(year) {
            return request(`/agreement-performance-dashboard?year=${encodeURIComponent(year)}`);
        },
        lifecycleRequests() {
            return request('/agreement-lifecycle-requests');
        },
        lifecycleRequest(id) {
            return request(`/agreement-lifecycle-requests/${encodeURIComponent(id)}`);
        },
        lifecycleVersions(id) {
            return request(`/agreement-lifecycle-requests/${encodeURIComponent(id)}/versions`);
        },
        lifecycleDocuments(id) {
            return request(`/agreement-lifecycle-requests/${encodeURIComponent(id)}/documents`);
        },
        uploadLifecycleDocument(id, file, documentType) {
            const body = new FormData();
            body.append('file', file);
            body.append('document_type', documentType);
            return request(
                `/agreement-lifecycle-requests/${encodeURIComponent(id)}/documents`,
                { method: 'POST', body }
            );
        },
        downloadLifecycleDocument(id) {
            return download(
                `/lifecycle-request-documents/${encodeURIComponent(id)}/download`
            );
        },
        deleteLifecycleDocument(id) {
            return request(`/lifecycle-request-documents/${encodeURIComponent(id)}`, {
                method: 'DELETE'
            });
        },
        createLifecycleRequest(agreementId, data) {
            return request(`/agreements/${encodeURIComponent(agreementId)}/lifecycle-requests`, {
                method: 'POST',
                body: jsonBody(data)
            });
        },
        updateLifecycleRequest(id, data) {
            return request(`/agreement-lifecycle-requests/${encodeURIComponent(id)}`, {
                method: 'PUT',
                body: jsonBody(data)
            });
        },
        submitLifecycleRequest(id) {
            return request(`/agreement-lifecycle-requests/${encodeURIComponent(id)}/submit`, {
                method: 'POST'
            });
        },
        decideLifecycle(instanceId, data) {
            return request(`/lifecycle-workflow-instances/${encodeURIComponent(instanceId)}/decide`, {
                method: 'POST',
                body: jsonBody(data)
            });
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
        approvePresident(instanceId, data) {
            return request(
                `/workflow-instances/${encodeURIComponent(instanceId)}/president/approve`,
                {
                    method: 'POST',
                    body: jsonBody(data)
                }
            );
        },
        rejectPresident(instanceId, data) {
            return request(
                `/workflow-instances/${encodeURIComponent(instanceId)}/president/reject`,
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
