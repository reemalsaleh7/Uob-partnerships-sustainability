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
        refresh: document.querySelector('[data-refresh-inbox]')
    };

    const stepLabels = Object.freeze({
        VP_INITIAL: 'Initial VP review',
        LEGAL_REVIEW: 'Legal review',
        FINANCE_REVIEW: 'Finance review',
        VP_FINAL: 'Final VP review',
        PRESIDENT_APPROVAL: 'President approval'
    });

    function cell(text) {
        const td = document.createElement('td');
        td.textContent = text ?? '—';
        return td;
    }

    function canUseInbox(user) {
        return AgreementApi.hasPermission(user, 'APPROVE_AGREEMENT')
            || AgreementApi.hasPermission(user, 'REJECT_AGREEMENT');
    }

    async function addAgreementDetails(assignments) {
        return Promise.all(assignments.map(async (assignment) => {
            if (assignment.entity_type !== 'AGREEMENT') {
                return { ...assignment, agreement: null };
            }

            try {
                const agreement = await AgreementApi.agreement(assignment.entity_id);
                return { ...assignment, agreement };
            } catch (error) {
                return { ...assignment, agreement: null };
            }
        }));
    }

    function render(rows) {
        elements.tableBody.replaceChildren();
        elements.loading.classList.add('d-none');
        elements.empty.classList.toggle('d-none', rows.length !== 0);
        elements.tableWrap.classList.toggle('d-none', rows.length === 0);
        elements.summary.textContent = rows.length === 1
            ? '1 active task'
            : `${rows.length} active tasks`;

        rows.forEach((assignment) => {
            const tr = document.createElement('tr');

            const taskCell = document.createElement('td');
            taskCell.className = 'workflow-task-cell';
            const label = document.createElement('span');
            label.className = 'workflow-task-label';
            label.textContent = stepLabels[assignment.step_key] || 'Workflow review';
            const key = document.createElement('span');
            key.className = 'workflow-task-key';
            key.textContent = assignment.step_key || 'Unknown step';
            taskCell.append(label, key);
            tr.appendChild(taskCell);

            const agreementTitle = assignment.agreement?.title
                || `Agreement #${assignment.entity_id}`;
            const agreementCell = cell(agreementTitle);
            agreementCell.classList.add('agreement-title-cell');
            tr.appendChild(agreementCell);

            tr.appendChild(cell(
                assignment.assigned_unit_name
                || assignment.assigned_unit_code
                || '—'
            ));
            tr.appendChild(cell(AgreementApi.formatDate(assignment.started_at)));

            const actionCell = document.createElement('td');
            actionCell.className = 'text-end';

            if (
                ['VP_INITIAL', 'LEGAL_REVIEW', 'FINANCE_REVIEW']
                    .includes(assignment.step_key)
            ) {
                const link = document.createElement('a');
                link.className = 'btn btn-sm btn-primary';
                const query = new URLSearchParams({
                    instance_id: assignment.workflow_instance_id,
                    agreement_id: assignment.entity_id
                });
                const reviewPages = {
                    VP_INITIAL: 'workflow-review.php',
                    LEGAL_REVIEW: 'legal-review.php',
                    FINANCE_REVIEW: 'finance-review.php'
                };
                link.href = `${reviewPages[assignment.step_key]}?${query.toString()}`;
                link.textContent = 'Review';
                actionCell.appendChild(link);
            } else {
                const note = document.createElement('span');
                note.className = 'small text-secondary';
                note.textContent = 'Review screen coming next';
                actionCell.appendChild(note);
            }

            tr.appendChild(actionCell);
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

        try {
            const user = await AgreementApi.requireSession();

            if (!canUseInbox(user)) {
                throw new AgreementApi.ApiError(
                    'You do not have permission to view the workflow inbox.',
                    403,
                    null
                );
            }

            const assignments = await AgreementApi.workflowInbox();
            const rows = await addAgreementDetails(
                Array.isArray(assignments) ? assignments : []
            );
            render(rows);
        } catch (error) {
            showError(error);
        } finally {
            elements.refresh.disabled = false;
        }
    }

    elements.refresh.addEventListener('click', loadInbox);
    showCompletionFeedback();
    loadInbox();
})();
