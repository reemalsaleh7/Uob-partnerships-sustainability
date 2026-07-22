(function () {
    'use strict';

    const elements = {
        alert: document.getElementById('detail-alert'),
        feedback: document.getElementById('detail-feedback'),
        loading: document.getElementById('detail-loading'),
        content: document.getElementById('detail-content'),
        versionLoading: document.getElementById('version-loading'),
        versionEmpty: document.getElementById('version-empty'),
        versionWrap: document.getElementById('version-table-wrap'),
        versionBody: document.getElementById('version-table-body'),
        relationshipSection: document.querySelector('[data-relationship-section]'),
        relationshipRows: document.querySelector('[data-relationship-rows]'),
        lifecycle: document.querySelector('[data-lifecycle-request]'),
        edit: document.querySelector('[data-edit-agreement]'),
        submit: document.querySelector('[data-submit-agreement]'),
        submitLabel: document.querySelector('[data-submit-label]'),
        submitSpinner: document.querySelector('[data-submit-spinner]'),
        workflowState: document.querySelector('[data-workflow-state]'),
        workflowLoading: document.querySelector('[data-workflow-timeline-loading]'),
        workflowContent: document.querySelector('[data-workflow-timeline-content]'),
        workflowEmpty: document.querySelector('[data-workflow-timeline-empty]'),
        workflowTimeline: document.querySelector('[data-workflow-timeline]'),
        workflowCurrent: document.querySelector('[data-workflow-current]'),
        workflowHistoryWrap: document.querySelector('[data-workflow-history-wrap]'),
        workflowHistory: document.querySelector('[data-workflow-history]')
    };

    const state = {
        agreementId: null,
        agreement: null
    };

    function setText(selector, value) {
        const element = document.querySelector(selector);

        if (element) {
            element.textContent = value ?? '—';
        }
    }

    function yesNo(value) {
        return value === true || value === 1 || value === '1' || value === 't' || value === 'true'
            ? 'Yes'
            : 'No';
    }

    function setField(name, value) {
        setText(`[data-field="${name}"]`, value === '' || value == null ? '—' : value);
    }

    function summaryItem(label, value) {
        const item = document.createElement('div');
        item.className = 'mb-3';
        const heading = document.createElement('strong');
        heading.className = 'd-block small text-secondary';
        heading.textContent = label;
        const content = document.createElement('span');
        content.textContent = value || '—';
        item.append(heading, content);
        return item;
    }

    function renderRelatedRecords(agreement) {
        const contacts = document.querySelector('[data-contact-summary]');
        contacts.replaceChildren();
        if (!(agreement.contacts || []).length) {
            contacts.append(summaryItem('Contacts', 'No coordinators or signatories recorded.'));
        } else {
            agreement.contacts.forEach((contact) => {
                const label = `${contact.party_type === 'UOB' ? 'UOB' : 'Partner'} ${String(contact.contact_role).toLowerCase()}`;
                const value = [contact.full_name, contact.job_title, contact.email, contact.phone].filter(Boolean).join(' · ');
                contacts.append(summaryItem(label, value));
            });
        }

        const programs = document.querySelector('[data-program-summary]');
        programs.replaceChildren();
        (agreement.executive_programs || []).forEach((program) => {
            programs.append(
                summaryItem('Program', program.title),
                summaryItem('Description', program.description),
                summaryItem('Objectives', program.objectives),
                summaryItem('Expected outputs', program.expected_outputs),
                summaryItem('Timeline', [program.start_date, program.end_date].filter(Boolean).join(' to ')),
                summaryItem('Responsible entity', program.responsible_entity)
            );
        });
        (agreement.metrics || []).forEach((metric) => {
            const label = String(metric.metric_code).replaceAll('_', ' ').toLowerCase();
            const value = [
                metric.planned_value != null ? `Planned: ${metric.planned_value}` : '',
                metric.actual_value != null ? `Actual: ${metric.actual_value}` : '',
                metric.notes
            ].filter(Boolean).join(' · ');
            programs.append(summaryItem(label, value));
        });
        if (!(agreement.executive_programs || []).length && !(agreement.metrics || []).length) {
            programs.append(summaryItem('Programs and outcomes', 'No executive program or outcome metrics recorded.'));
        }

        const relationships = agreement.relationships || [];
        elements.relationshipSection.classList.toggle('d-none', relationships.length === 0);
        elements.relationshipRows.replaceChildren();
        relationships.forEach((relationship) => {
            const row = document.createElement('tr');
            const type = document.createElement('td');
            const linked = document.createElement('td');
            const status = document.createElement('td');
            const action = document.createElement('td');
            const link = document.createElement('a');
            type.textContent = `${relationship.relationship_type} ${relationship.direction === 'SOURCE' ? 'of this Agreement' : 'successor'}`;
            linked.textContent = relationship.linked_agreement_title || `Agreement #${relationship.linked_agreement_id}`;
            status.append(AgreementApi.createStatusBadge(relationship.linked_agreement_status));
            action.className = 'text-end';
            link.className = 'btn btn-sm btn-outline-primary';
            link.href = `agreement.php?id=${encodeURIComponent(relationship.linked_agreement_id)}`;
            link.textContent = 'Open';
            action.append(link);
            row.append(type, linked, status, action);
            elements.relationshipRows.append(row);
        });
    }

    function agreementId() {
        const value = new URLSearchParams(window.location.search).get('id');

        if (!value || !/^\d+$/.test(value)) {
            throw new AgreementApi.ApiError('A valid Agreement ID is required.', 422, null);
        }

        return value;
    }

    function renderAgreement(agreement) {
        setText('[data-agreement-id]', `#${agreement.agreement_id}`);
        setText('[data-agreement-title]', agreement.title);
        setText('[data-agreement-type]', agreement.agreement_type);
        setText(
            '[data-partner-name]',
            (agreement.partner_names || []).join(', ') || agreement.partner_name || (
                agreement.partner_id
                    ? `Partner #${agreement.partner_id}`
                    : '—'
            )
        );
        setText('[data-agreement-description]', agreement.description || 'No description provided.');
        setText('[data-created-by]', agreement.created_by);
        setText('[data-created-at]', AgreementApi.formatDate(agreement.created_at));
        setText('[data-updated-at]', AgreementApi.formatDate(agreement.updated_at));

        [
            'title_ar', 'geographic_scope', 'start_date', 'end_date',
            'signing_date', 'effective_date', 'legal_binding_status',
            'responsible_unit_name', 'need_justification', 'objectives',
            'expected_value', 'focus_areas', 'collaboration_areas',
            'implementation_methods', 'monitoring_plan', 'confidentiality_terms',
            'intellectual_property_terms', 'compliance_terms',
            'relationship_disclaimer', 'amendment_terms',
            'dispute_resolution_terms', 'other_terms', 'signing_link'
        ].forEach((field) => setField(field, agreement[field]));
        setField('auto_renew', yesNo(agreement.auto_renew));
        setField('annual_report_required', yesNo(agreement.annual_report_required));
        setField('renewal_term_months', agreement.renewal_term_months == null ? '—' : `${agreement.renewal_term_months} months`);
        setField('non_renewal_notice_months', agreement.non_renewal_notice_months == null ? '—' : `${agreement.non_renewal_notice_months} months`);
        setField('termination_notice_months', agreement.termination_notice_months == null ? '—' : `${agreement.termination_notice_months} months`);
        setField(
            'financial_summary',
            yesNo(agreement.financial_commitments) === 'Yes'
                ? [agreement.financial_amount, agreement.financial_currency, agreement.financial_description].filter(Boolean).join(' · ')
                : 'None'
        );
        setField('human_resources_summary', yesNo(agreement.human_resources_commitments) === 'Yes' ? (agreement.human_resources_description || 'Yes') : 'None');
        setField('training_programs_summary', yesNo(agreement.training_programs) === 'Yes' ? (agreement.training_programs_description || 'Yes') : 'None');
        setField('rankings_summary', (agreement.rankings || []).map((value) => value.replaceAll('_', ' ')).join(', ') || 'Not applicable');
        setField('sdgs_summary', (agreement.sdgs || []).map((value) => `SDG ${value}`).join(', ') || 'None selected');
        renderRelatedRecords(agreement);

        const status = document.querySelector('[data-agreement-status]');
        status.replaceChildren(AgreementApi.createStatusBadge(agreement.status));
    }

    function renderVersions(versions) {
        const rows = Array.isArray(versions) ? versions : [];
        elements.versionLoading.classList.add('d-none');
        elements.versionEmpty.classList.toggle('d-none', rows.length !== 0);
        elements.versionWrap.classList.toggle('d-none', rows.length === 0);
        elements.versionBody.replaceChildren();

        rows.forEach((version) => {
            const tr = document.createElement('tr');

            [
                version.version_number,
                version.change_summary || 'Initial version',
                version.created_by,
                AgreementApi.formatDate(version.created_at)
            ].forEach((value) => {
                const td = document.createElement('td');
                td.textContent = value ?? '—';
                tr.appendChild(td);
            });

            const action = document.createElement('td');
            action.className = 'text-end';
            if (Number(version.version_number) > 1) {
                const compare = document.createElement('button');
                compare.className = 'btn btn-sm btn-outline-primary';
                compare.type = 'button';
                compare.dataset.compareVersion = String(version.version_number);
                compare.textContent = 'View changes';
                action.append(compare);
            }
            tr.append(action);

            elements.versionBody.appendChild(tr);
        });
    }

    const workflowLabels = Object.freeze({
        CREATOR: 'Creator submission',
        VP_INITIAL: 'Initial VP review',
        LEGAL_REVIEW: 'Legal review',
        FINANCE_REVIEW: 'Finance review',
        VP_FINAL: 'Final VP review',
        PRESIDENT_APPROVAL: 'President approval'
    });

    function workflowStepClass(status) {
        const classes = {
            APPROVED: 'is-complete',
            COMPLETED: 'is-complete',
            IN_PROGRESS: 'is-current',
            CHANGES_REQUESTED: 'is-blocked',
            REJECTED: 'is-blocked',
            SKIPPED: 'is-skipped'
        };
        return classes[String(status || '').toUpperCase()] || '';
    }

    function renderWorkflow(timeline) {
        elements.workflowLoading.classList.add('d-none');
        const steps = Array.isArray(timeline?.steps) ? timeline.steps : [];
        const workflow = timeline?.workflow || null;

        if (!workflow || !steps.length) {
            elements.workflowState.textContent = 'Not submitted';
            elements.workflowEmpty.classList.remove('d-none');
            return;
        }

        elements.workflowState.replaceChildren(
            AgreementApi.createStatusBadge(workflow.status || 'IN_PROGRESS')
        );
        elements.workflowTimeline.replaceChildren();

        steps.forEach((step, index) => {
            const item = document.createElement('div');
            item.className = `timeline-step ${workflowStepClass(step.status)}`.trim();
            const marker = document.createElement('span');
            marker.className = 'timeline-marker';
            marker.textContent = ['APPROVED', 'COMPLETED'].includes(step.status)
                ? '✓'
                : String(index + 1);
            const title = document.createElement('strong');
            title.textContent = workflowLabels[step.step_key]
                || String(step.step_key || 'Workflow step').replaceAll('_', ' ');
            const office = document.createElement('small');
            office.textContent = step.step_key === 'CREATOR'
                ? (step.acted_by_name || step.acted_by_email || 'Agreement creator')
                : (step.assigned_unit_name || step.assigned_unit_code || 'University reviewer');
            item.append(marker, title, office);

            if (step.status === 'IN_PROGRESS') {
                const reviewer = document.createElement('span');
                reviewer.textContent = step.assigned_reviewer_names
                    || step.assigned_reviewer_emails
                    || 'Reviewer assignment active';
                item.append(reviewer);
            } else if (step.acted_by_name || step.acted_by_email) {
                const actor = document.createElement('span');
                actor.textContent = `Completed by ${step.acted_by_name || step.acted_by_email}`;
                item.append(actor);
            } else if (step.status === 'SKIPPED') {
                const skipped = document.createElement('span');
                skipped.textContent = 'Not required';
                item.append(skipped);
            }

            elements.workflowTimeline.append(item);
        });

        const current = steps.find((step) => step.status === 'IN_PROGRESS');
        if (current) {
            const copy = document.createElement('div');
            const title = document.createElement('strong');
            title.textContent = `Currently under review: ${workflowLabels[current.step_key] || current.step_key}`;
            const detail = document.createElement('small');
            const reviewer = current.assigned_reviewer_names
                || current.assigned_reviewer_emails
                || 'Assigned reviewer';
            detail.textContent = `${current.assigned_unit_name || current.assigned_unit_code || 'Reviewing office'} · ${reviewer} · Started ${AgreementApi.formatDate(current.started_at)}`;
            copy.append(title, detail);
            elements.workflowCurrent.replaceChildren(copy);
            elements.workflowCurrent.classList.remove('d-none');
        }

        const history = Array.isArray(timeline?.history) ? timeline.history : [];
        elements.workflowHistory.replaceChildren();
        history.forEach((event) => {
            const item = document.createElement('li');
            item.className = 'dashboard-list-item';
            const copy = document.createElement('div');
            const title = document.createElement('strong');
            title.textContent = String(event.action || 'Workflow action').replaceAll('_', ' ');
            const detail = document.createElement('small');
            detail.textContent = [
                workflowLabels[event.step_key] || event.step_key,
                event.performed_by_name || event.performed_by_email,
                AgreementApi.formatDate(event.created_at),
                event.comments
            ].filter(Boolean).join(' · ');
            copy.append(title, detail);
            item.append(copy);
            elements.workflowHistory.append(item);
        });
        elements.workflowHistoryWrap.classList.toggle('d-none', history.length === 0);
        elements.workflowContent.classList.remove('d-none');
    }

    function showError(error) {
        elements.loading.classList.add('d-none');
        elements.content.classList.add('d-none');
        elements.alert.textContent = error.message || 'Agreement details could not be loaded.';
        elements.alert.classList.remove('d-none');
        elements.alert.focus();
    }

    function showFeedbackFromQuery() {
        const query = new URLSearchParams(window.location.search);
        let message = '';

        if (query.get('created') === '1') {
            message = 'Agreement draft created successfully.';
        } else if (query.get('updated') === '1') {
            message = 'Agreement draft updated successfully.';
        } else if (query.get('submitted') === '1') {
            message = 'Agreement submitted for review successfully.';
        } else if (query.get('revised') === '1') {
            message = 'Revised Agreement version saved. Review it, then resubmit it.';
        } else if (query.get('resubmitted') === '1') {
            message = 'Revised Agreement resubmitted to Initial VP review successfully.';
        }

        if (message) {
            elements.feedback.textContent = message;
            elements.feedback.classList.remove('d-none');
        }
    }

    function configureActions(user, agreement) {
        const isDraft = agreement.status === 'DRAFT';
        const isRevision = agreement.status === 'REVISION_REQUIRED';
        const isCreator = Number(agreement.created_by) === Number(user.user_id);
        const canEdit = AgreementApi.hasPermission(user, 'EDIT_AGREEMENT');
        const canSubmit = AgreementApi.hasPermission(user, 'SUBMIT_AGREEMENT');
        const canCreate = AgreementApi.hasPermission(user, 'CREATE_AGREEMENT');

        elements.lifecycle.classList.toggle(
            'd-none',
            !['APPROVED', 'ACTIVE'].includes(agreement.status) || !canCreate
        );
        elements.lifecycle.href = `lifecycle-form.php?agreement_id=${encodeURIComponent(agreement.agreement_id)}`;

        elements.edit.classList.toggle(
            'd-none',
            (!isDraft && !isRevision) || !isCreator || !canEdit
        );
        elements.edit.href = `agreement-form.php?id=${encodeURIComponent(agreement.agreement_id)}`;
        elements.edit.textContent = isRevision ? 'Revise Agreement' : 'Edit Agreement';
        elements.submit.classList.toggle(
            'd-none',
            (!isDraft && !isRevision) || !isCreator || !canSubmit
        );
        elements.submitLabel.textContent = isRevision
            ? 'Resubmit revised Agreement'
            : 'Submit for review';
    }

    function setSubmitBusy(isBusy) {
        elements.submit.disabled = isBusy;
        elements.submitLabel.textContent = isBusy
            ? 'Submitting…'
            : (
                state.agreement?.status === 'REVISION_REQUIRED'
                    ? 'Resubmit revised Agreement'
                    : 'Submit for review'
            );
        elements.submitSpinner.classList.toggle('d-none', !isBusy);
    }

    async function initialize() {
        try {
            const id = agreementId();
            state.agreementId = id;
            const user = await AgreementApi.requireSession('VIEW_AGREEMENT');

            const [agreement, versions, timeline] = await Promise.all([
                AgreementApi.agreement(id),
                AgreementApi.versions(id),
                AgreementApi.agreementTimeline(id)
            ]);

            if (!agreement) {
                throw new AgreementApi.ApiError('Agreement not found.', 404, null);
            }

            state.agreement = agreement;
            renderAgreement(agreement);
            renderVersions(versions);
            renderWorkflow(timeline);
            configureActions(user, agreement);
            showFeedbackFromQuery();
            elements.loading.classList.add('d-none');
            elements.content.classList.remove('d-none');
        } catch (error) {
            showError(error);
        }
    }

    elements.submit.addEventListener('click', async () => {
        if (
            !state.agreement
            || !['DRAFT', 'REVISION_REQUIRED'].includes(state.agreement.status)
        ) {
            return;
        }

        const isRevision = state.agreement.status === 'REVISION_REQUIRED';

        const confirmed = window.confirm(
            isRevision
                ? 'Resubmit this revised Agreement? It will return to Initial VP review.'
                : 'Submit this Agreement for formal review? You will not be able to edit it as a draft after submission.'
        );

        if (!confirmed) {
            return;
        }

        elements.alert.classList.add('d-none');
        elements.feedback.classList.add('d-none');
        setSubmitBusy(true);

        try {
            if (isRevision) {
                await AgreementApi.resubmitAgreement(state.agreementId, {
                    comments: 'Revised Agreement resubmitted through the workspace'
                });
            } else {
                await AgreementApi.submitAgreement(state.agreementId);
            }
            window.location.replace(
                `agreement.php?id=${encodeURIComponent(state.agreementId)}&${isRevision ? 'resubmitted=1' : 'submitted=1'}`
            );
        } catch (error) {
            elements.alert.textContent = error.message || 'The Agreement could not be submitted.';
            elements.alert.classList.remove('d-none');
            elements.alert.focus();
            setSubmitBusy(false);
        }
    });

    initialize();
})();
