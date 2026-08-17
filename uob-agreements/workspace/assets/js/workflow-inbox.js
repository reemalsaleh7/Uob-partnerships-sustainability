(function () {
    'use strict';

    const elements = {
        alert: document.getElementById('workflow-alert'),
        feedback: document.getElementById('workflow-feedback'),
        loading: document.getElementById('workflow-loading'),
        empty: document.getElementById('workflow-empty'),
        tableWrap: document.getElementById('workflow-table-wrap'),
        tableBody: document.getElementById('workflow-table-body'),
        summary: document.querySelector('[data-workflow-summary]'),
        refresh: document.querySelector('[data-refresh-inbox]'),
        unreadOnly: document.querySelector('[data-workflow-unread-only]'),
        markAllRead: document.querySelector('[data-mark-all-workflow-read]')
    };

    const stepLabels = Object.freeze({
        VP_INITIAL: 'Initial VP review',
        LEGAL_REVIEW: 'Legal review',
        FINANCE_REVIEW: 'Finance review',
        VP_FINAL: 'Final VP review',
        PRESIDENT_APPROVAL: 'President approval',
        DEPARTMENT_HEAD: 'Department Head',
        DEAN: 'Dean',
        VICE_PRESIDENT: 'Vice President / Office',
        PRESIDENT: 'President / Office'
    });

    let rows = [];

    function booleanValue(value) {
        return value === true
            || value === 1
            || value === '1'
            || value === 't'
            || value === 'true'
            || value === 'yes'
            || value === 'on';
    }

    function cell(text) {
        const td = document.createElement('td');
        td.textContent = text ?? '—';
        return td;
    }

    function canUseInbox(user) {
        return AgreementApi.hasPermission(user, 'APPROVE_AGREEMENT')
            || AgreementApi.hasPermission(user, 'REJECT_AGREEMENT')
            || AgreementApi.hasPermission(user, 'APPROVE_INITIATIVE');
    }

    async function addAgreementDetails(assignments) {
        return Promise.all(assignments.map(async (assignment) => {
            if (!['AGREEMENT', 'AGREEMENT_LIFECYCLE'].includes(assignment.entity_type)) {
                return { ...assignment, agreement: null };
            }

            try {
                const agreement = await AgreementApi.agreement(
                    assignment.subject_agreement_id || assignment.entity_id
                );
                return { ...assignment, agreement };
            } catch (error) {
                return { ...assignment, agreement: null };
            }
        }));
    }

    function assignmentHref(assignment) {
        if (assignment.entity_type === 'INITIATIVE_REQUEST') {
            return `initiative-workflow.php?view=detail&id=${encodeURIComponent(
                assignment.entity_id
            )}`;
        }

        if (assignment.entity_type === 'AGREEMENT_LIFECYCLE') {
            const query = new URLSearchParams({
                instance_id: assignment.workflow_instance_id,
                request_id: assignment.lifecycle_request_id || assignment.entity_id
            });
            return `lifecycle-review.php?${query.toString()}`;
        }

        if (assignment.engine_version === 'CONFIGURABLE') {
            return `workflow-generic-review.php?instance_id=${encodeURIComponent(
                assignment.workflow_instance_id
            )}`;
        }

        const reviewPages = {
            VP_INITIAL: 'workflow-review.php',
            LEGAL_REVIEW: 'legal-review.php',
            FINANCE_REVIEW: 'finance-review.php',
            VP_FINAL: 'vp-review.php',
            PRESIDENT_APPROVAL: 'president-review.php'
        };

        if (reviewPages[assignment.step_key]) {
            const query = new URLSearchParams({
                instance_id: assignment.workflow_instance_id,
                agreement_id: assignment.entity_id
            });
            return `${reviewPages[assignment.step_key]}?${query.toString()}`;
        }

        return `agreement.php?id=${encodeURIComponent(
            assignment.subject_agreement_id || assignment.entity_id
        )}`;
    }

    function assignmentItemTitle(assignment) {
        if (assignment.entity_type === 'INITIATIVE_REQUEST') {
            const code = assignment.initiative_request_code
                || `Initiative request #${assignment.entity_id}`;
            const title = String(assignment.initiative_request_title || '').trim();
            return title ? `${code} · ${title}` : code;
        }

        const agreementTitle = assignment.agreement?.title
            || `Agreement #${assignment.subject_agreement_id || assignment.entity_id}`;

        if (assignment.entity_type === 'AGREEMENT_LIFECYCLE') {
            return `${String(
                assignment.lifecycle_request_type || 'Lifecycle'
            ).replaceAll('_', ' ')} · ${agreementTitle}`;
        }

        return agreementTitle;
    }

    function filteredRows() {
        if (!elements.unreadOnly?.checked) {
            return rows;
        }

        return rows.filter((assignment) => !booleanValue(assignment.is_read));
    }

    async function markRead(assignment) {
        if (booleanValue(assignment.is_read)) {
            return;
        }

        await AgreementApi.request(
            `/workflow-inbox/${encodeURIComponent(assignment.instance_step_id)}/read`,
            { method: 'POST' }
        );
        assignment.is_read = true;
        assignment.read_at = new Date().toISOString();
    }

    async function openAssignment(assignment) {
        if (assignment.__opening) {
            return;
        }

        assignment.__opening = true;
        const href = assignmentHref(assignment);

        try {
            await markRead(assignment);
        } catch (error) {
            // Reading state must never block the reviewer from opening the task.
        } finally {
            window.location.assign(href);
        }
    }

    function render() {
        const visibleRows = filteredRows();

        elements.tableBody.replaceChildren();
        elements.loading.classList.add('d-none');
        elements.empty.classList.toggle('d-none', visibleRows.length !== 0);
        elements.tableWrap.classList.toggle('d-none', visibleRows.length === 0);
        elements.summary.textContent = visibleRows.length === 1
            ? '1 active task'
            : `${visibleRows.length} active tasks`;

        if (elements.markAllRead) {
            elements.markAllRead.disabled = !rows.some(
                (assignment) => !booleanValue(assignment.is_read)
            );
        }

        visibleRows.forEach((assignment) => {
            const tr = document.createElement('tr');
            const isRead = booleanValue(assignment.is_read);
            const href = assignmentHref(assignment);

            tr.className = 'workflow-inbox-row';
            tr.style.cursor = 'pointer';
            tr.tabIndex = 0;
            tr.setAttribute('role', 'link');
            tr.setAttribute('aria-label', `Open ${assignmentItemTitle(assignment)}`);

            const taskCell = document.createElement('td');
            taskCell.className = 'workflow-task-cell';
            const label = document.createElement('span');
            label.className = 'workflow-task-label';
            label.textContent = assignment.task_mode === 'VP_MEDIATION'
                ? 'VP mediation'
                : (
                    assignment.step_label
                    || stepLabels[assignment.step_key]
                    || 'Workflow review'
                );
            const key = document.createElement('span');
            key.className = 'workflow-task-key';
            const isVersioned = String(assignment.engine_version || '')
                .startsWith('CONFIGURABLE');
            const keyText = isVersioned
                ? `Phase ${assignment.phase_order || assignment.step_order} · template v${
                    assignment.template_version_number || 1
                }`
                : (assignment.step_key || 'Unknown step');
            key.textContent = isRead ? keyText : `${keyText} · Unread`;
            taskCell.append(label, key);
            tr.appendChild(taskCell);

            const itemCell = cell(assignmentItemTitle(assignment));
            itemCell.classList.add('agreement-title-cell');
            tr.appendChild(itemCell);

            tr.appendChild(cell(
                assignment.assigned_unit_name
                || assignment.assigned_unit_code
                || '—'
            ));
            tr.appendChild(cell(AgreementApi.formatDate(assignment.started_at)));

            const actionCell = document.createElement('td');
            actionCell.className = 'text-end';
            const link = document.createElement('a');
            link.className = 'btn btn-sm btn-primary';
            link.href = href;
            link.textContent = assignment.entity_type === 'INITIATIVE_REQUEST'
                ? 'Review Initiative'
                : 'Review';
            link.addEventListener('click', (event) => {
                event.preventDefault();
                openAssignment(assignment);
            });
            actionCell.appendChild(link);
            tr.appendChild(actionCell);

            tr.addEventListener('click', (event) => {
                const interactiveTarget = event.target instanceof Element
                    && event.target.closest('a, button, input, select, textarea, label');
                if (interactiveTarget) {
                    return;
                }
                openAssignment(assignment);
            });

            tr.addEventListener('keydown', (event) => {
                if (event.key !== 'Enter' && event.key !== ' ') {
                    return;
                }
                event.preventDefault();
                openAssignment(assignment);
            });

            elements.tableBody.appendChild(tr);
        });
    }

    function showError(error) {
        elements.loading.classList.add('d-none');
        elements.tableWrap.classList.add('d-none');
        elements.empty.classList.add('d-none');
        elements.alert.textContent = error.message || 'The workflow inbox could not be loaded.';
        elements.alert.classList.remove('d-none');
        elements.alert.focus();
        elements.summary.textContent = 'Unable to load assignments';
    }

    function showCompletionFeedback() {
        const query = new URLSearchParams(window.location.search);

        if (query.get('completed') !== '1') {
            return;
        }

        elements.feedback.textContent = 'Workflow decision saved successfully.';
        elements.feedback.classList.remove('d-none');
        window.history.replaceState({}, '', 'workflow-inbox.php');
    }

    async function loadInbox() {
        elements.alert.classList.add('d-none');
        elements.loading.classList.remove('d-none');
        elements.empty.classList.add('d-none');
        elements.tableWrap.classList.add('d-none');
        elements.refresh.disabled = true;
        if (elements.markAllRead) {
            elements.markAllRead.disabled = true;
        }

        try {
            const user = await AgreementApi.requireSession();

            if (!canUseInbox(user)) {
                throw new AgreementApi.ApiError(
                    'You do not have permission to view the workflow inbox.',
                    403,
                    null
                );
            }

            const [legacyAssignments, configurableAssignments] = await Promise.all([
                AgreementApi.workflowInbox(),
                AgreementApi.request('/configurable-workflows/inbox')
            ]);
            const configurable = Array.isArray(configurableAssignments)
                ? configurableAssignments
                : [];
            const configurableStepIds = new Set(
                configurable.map((assignment) => Number(assignment.instance_step_id))
            );
            const legacy = (Array.isArray(legacyAssignments)
                ? legacyAssignments
                : []).filter(
                (assignment) => !configurableStepIds.has(
                    Number(assignment.instance_step_id)
                )
            );
            rows = await addAgreementDetails([
                ...configurable,
                ...legacy
            ]);
            render();
        } catch (error) {
            showError(error);
        } finally {
            elements.refresh.disabled = false;
        }
    }

    elements.refresh.addEventListener('click', loadInbox);
    elements.unreadOnly?.addEventListener('change', render);
    elements.markAllRead?.addEventListener('click', async () => {
        elements.markAllRead.disabled = true;
        elements.alert.classList.add('d-none');

        try {
            await AgreementApi.request('/workflow-inbox/read-all', {
                method: 'POST'
            });
            rows.forEach((assignment) => {
                assignment.is_read = true;
                assignment.read_at = new Date().toISOString();
            });
            render();
        } catch (error) {
            showError(error);
        }
    });

    showCompletionFeedback();
    loadInbox();
})();
