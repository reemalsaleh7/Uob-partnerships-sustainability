(function () {
    'use strict';

    const elements = {
        alert: document.querySelector('[data-performance-alert]'),
        feedback: document.querySelector('[data-performance-feedback]'),
        loading: document.querySelector('[data-performance-loading]'),
        content: document.querySelector('[data-performance-content]'),
        title: document.querySelector('[data-report-agreement-title]'),
        status: document.querySelector('[data-report-status]'),
        period: document.querySelector('[data-report-period]'),
        overdue: document.querySelector('[data-report-overdue]'),
        agreementLink: document.querySelector('[data-report-agreement-link]'),
        uploadLink: document.querySelector('[data-upload-report-document]'),
        fields: [...document.querySelectorAll('[data-report-field]')],
        metrics: document.querySelector('[data-performance-metrics]'),
        programs: document.querySelector('[data-performance-programs]'),
        document: document.querySelector('[data-report-document]'),
        actions: document.querySelector('[data-report-actions]'),
        save: document.querySelector('[data-save-report]'),
        submit: document.querySelector('[data-submit-report]'),
        reviewPanel: document.querySelector('[data-review-panel]'),
        reviewComments: document.querySelector('[data-review-comments]'),
        accept: document.querySelector('[data-accept-report]'),
        returnReport: document.querySelector('[data-return-report]'),
        reviewHistory: document.querySelector('[data-review-history]'),
        events: document.querySelector('[data-report-events]')
    };
    const state = { id: null, report: null, busy: false };

    function id() {
        const value = new URLSearchParams(window.location.search).get('id');
        if (!value || !/^\d+$/.test(value)) {
            throw new AgreementApi.ApiError('A valid performance report ID is required.', 422, null);
        }
        return value;
    }

    function message(target, value) {
        target.textContent = value;
        target.classList.remove('d-none');
        target.focus();
    }

    function clearMessages() {
        elements.alert.classList.add('d-none');
        elements.feedback.classList.add('d-none');
    }

    function input(type, value = '') {
        const control = document.createElement('input');
        control.className = 'form-control form-control-sm';
        control.type = type;
        control.min = '0';
        control.value = value ?? '';
        return control;
    }

    function renderMetrics(metrics, editable) {
        elements.metrics.replaceChildren();
        metrics.forEach((metric) => {
            const row = document.createElement('tr');
            row.dataset.metricId = metric.agreement_metric_id || '';
            row.dataset.metricCode = metric.metric_code;
            row.dataset.metricLabel = metric.metric_label;
            const label = document.createElement('td');
            label.className = 'fw-semibold';
            label.textContent = metric.metric_label;
            const planned = document.createElement('td');
            const plannedInput = input('number', metric.planned_value);
            plannedInput.dataset.metricPlanned = '';
            plannedInput.disabled = true;
            planned.append(plannedInput);
            const actual = document.createElement('td');
            const actualInput = input('number', metric.actual_value);
            actualInput.step = '0.01';
            actualInput.dataset.metricActual = '';
            actualInput.disabled = !editable;
            actual.append(actualInput);
            const unit = document.createElement('td');
            const unitInput = input('text', metric.unit || 'COUNT');
            unitInput.dataset.metricUnit = '';
            unitInput.disabled = !editable;
            unit.append(unitInput);
            const notes = document.createElement('td');
            const notesInput = input('text', metric.notes);
            notesInput.dataset.metricNotes = '';
            notesInput.disabled = !editable;
            notes.append(notesInput);
            row.append(label, planned, actual, unit, notes);
            elements.metrics.append(row);
        });
        if (!metrics.length) {
            const row = document.createElement('tr');
            const cell = document.createElement('td');
            cell.colSpan = 5;
            cell.className = 'text-secondary text-center py-4';
            cell.textContent = 'This Agreement has no baseline outcome metrics.';
            row.append(cell);
            elements.metrics.append(row);
        }
    }

    function textarea(labelText, key, value, editable) {
        const wrapper = document.createElement('div');
        wrapper.className = 'col-md-6';
        const label = document.createElement('label');
        label.className = 'form-label small fw-semibold';
        label.textContent = labelText;
        const control = document.createElement('textarea');
        control.className = 'form-control';
        control.rows = 3;
        control.value = value || '';
        control.dataset.programField = key;
        control.disabled = !editable;
        wrapper.append(label, control);
        return wrapper;
    }

    function renderPrograms(programs, editable) {
        elements.programs.replaceChildren();
        programs.forEach((program, index) => {
            const panel = document.createElement('article');
            panel.className = `program-progress-editor ${index ? 'mt-4 pt-4 border-top' : ''}`;
            panel.dataset.programId = program.executive_program_id || '';
            panel.dataset.programTitle = program.program_title;
            const heading = document.createElement('h3');
            heading.className = 'h6 mb-3';
            heading.textContent = program.program_title;
            const controls = document.createElement('div');
            controls.className = 'row g-3';
            const statusWrap = document.createElement('div');
            statusWrap.className = 'col-md-6';
            const statusLabel = document.createElement('label');
            statusLabel.className = 'form-label small fw-semibold';
            statusLabel.textContent = 'Progress status';
            const status = document.createElement('select');
            status.className = 'form-select';
            status.dataset.programField = 'progress_status';
            ['NOT_STARTED', 'ON_TRACK', 'AT_RISK', 'DELAYED', 'COMPLETED', 'CANCELLED'].forEach((value) => {
                const option = document.createElement('option');
                option.value = value;
                option.textContent = value.replaceAll('_', ' ');
                status.append(option);
            });
            status.value = program.progress_status;
            status.disabled = !editable;
            statusWrap.append(statusLabel, status);
            const completionWrap = document.createElement('div');
            completionWrap.className = 'col-md-6';
            const completionLabel = document.createElement('label');
            completionLabel.className = 'form-label small fw-semibold';
            completionLabel.textContent = 'Completion percentage';
            const completion = input('number', program.completion_percent);
            completion.max = '100';
            completion.step = '0.01';
            completion.dataset.programField = 'completion_percent';
            completion.disabled = !editable;
            completionWrap.append(completionLabel, completion);
            controls.append(
                statusWrap,
                completionWrap,
                textarea('Achievements', 'achievements', program.achievements, editable),
                textarea('Outputs delivered', 'outputs_delivered', program.outputs_delivered, editable),
                textarea('Challenges', 'challenges', program.challenges, editable),
                textarea('Next steps', 'next_steps', program.next_steps, editable)
            );
            panel.append(heading, controls);
            elements.programs.append(panel);
        });
        if (!programs.length) {
            elements.programs.textContent = 'This Agreement has no executive programs to track.';
            elements.programs.className = 'form-section text-secondary';
        }
    }

    function renderEvents(events) {
        elements.events.replaceChildren();
        if (!events.length) {
            elements.events.textContent = 'No status event has occurred yet.';
            elements.events.className = 'form-section text-secondary';
            return;
        }
        const list = document.createElement('ol');
        list.className = 'list-group list-group-numbered list-group-flush';
        events.forEach((event) => {
            const item = document.createElement('li');
            item.className = 'list-group-item px-0';
            const actor = event.performed_by_name || event.performed_by_email || 'System';
            item.textContent = `${event.from_status || 'CREATED'} → ${event.to_status} · ${actor} · ${AgreementApi.formatDate(event.created_at)}${event.comments ? ` · ${event.comments}` : ''}`;
            list.append(item);
        });
        elements.events.className = 'form-section';
        elements.events.append(list);
    }

    function render(report) {
        state.report = report;
        const editable = report.can_manage === true;
        elements.title.textContent = report.agreement_title;
        elements.status.replaceChildren(AgreementApi.createStatusBadge(report.status));
        elements.period.textContent = `${report.period_start} – ${report.period_end} · due ${report.due_date}`;
        elements.overdue.classList.toggle('d-none', report.is_overdue !== true);
        elements.agreementLink.href = `agreement.php?id=${encodeURIComponent(report.agreement_id)}`;
        elements.uploadLink.href = `${elements.agreementLink.href}#agreement-documents-title`;
        elements.fields.forEach((field) => {
            field.value = report[field.dataset.reportField] || '';
            field.disabled = !editable;
        });
        renderMetrics(report.metrics || [], editable);
        renderPrograms(report.program_updates || [], editable);

        elements.document.replaceChildren();
        const prompt = document.createElement('option');
        prompt.value = '';
        prompt.textContent = report.report_document_name || 'Select the final annual report';
        elements.document.append(prompt);
        (report.eligible_documents || []).forEach((record) => {
            const option = document.createElement('option');
            option.value = record.document_id;
            option.textContent = record.file_name;
            elements.document.append(option);
        });
        elements.document.value = report.report_document_id || '';
        elements.document.disabled = !editable;
        elements.actions.classList.toggle('d-none', !editable);
        elements.reviewPanel.classList.toggle('d-none', report.can_review !== true);
        elements.reviewHistory.classList.toggle('d-none', !report.reviewed_at);
        if (report.reviewed_at) {
            document.querySelector('[data-review-field="reviewer"]').textContent = report.reviewer_name || report.reviewer_email || '—';
            document.querySelector('[data-review-field="reviewed_at"]').textContent = AgreementApi.formatDate(report.reviewed_at);
            document.querySelector('[data-review-field="comments"]').textContent = report.reviewer_comments || '—';
        }
        renderEvents(report.events || []);
        elements.loading.classList.add('d-none');
        elements.content.classList.remove('d-none');
    }

    function payload() {
        return {
            executive_summary: document.querySelector('[data-report-field="executive_summary"]').value.trim(),
            achievements: document.querySelector('[data-report-field="achievements"]').value.trim(),
            challenges: document.querySelector('[data-report-field="challenges"]').value.trim(),
            corrective_actions: document.querySelector('[data-report-field="corrective_actions"]').value.trim(),
            next_period_plan: document.querySelector('[data-report-field="next_period_plan"]').value.trim(),
            report_document_id: elements.document.value || null,
            metrics: [...elements.metrics.querySelectorAll('tr[data-metric-code]')].map((row) => ({
                agreement_metric_id: row.dataset.metricId || null,
                metric_code: row.dataset.metricCode,
                metric_label: row.dataset.metricLabel,
                planned_value: row.querySelector('[data-metric-planned]').value || null,
                actual_value: row.querySelector('[data-metric-actual]').value || null,
                unit: row.querySelector('[data-metric-unit]').value.trim(),
                notes: row.querySelector('[data-metric-notes]').value.trim()
            })),
            program_updates: [...elements.programs.querySelectorAll('[data-program-id]')].map((panel) => ({
                executive_program_id: panel.dataset.programId || null,
                program_title: panel.dataset.programTitle,
                progress_status: panel.querySelector('[data-program-field="progress_status"]').value,
                completion_percent: panel.querySelector('[data-program-field="completion_percent"]').value,
                achievements: panel.querySelector('[data-program-field="achievements"]').value.trim(),
                outputs_delivered: panel.querySelector('[data-program-field="outputs_delivered"]').value.trim(),
                challenges: panel.querySelector('[data-program-field="challenges"]').value.trim(),
                next_steps: panel.querySelector('[data-program-field="next_steps"]').value.trim()
            }))
        };
    }

    function busy(value) {
        state.busy = value;
        [elements.save, elements.submit, elements.accept, elements.returnReport].forEach((button) => {
            button.disabled = value;
        });
    }

    async function reload() {
        render(await AgreementApi.performanceReport(state.id));
    }

    elements.save.addEventListener('click', async () => {
        if (state.busy) return;
        clearMessages();
        busy(true);
        try {
            await AgreementApi.updatePerformanceReport(state.id, payload());
            await reload();
            message(elements.feedback, 'Performance report draft saved.');
        } catch (error) {
            message(elements.alert, error.message || 'The report could not be saved.');
        } finally { busy(false); }
    });

    elements.submit.addEventListener('click', async () => {
        if (state.busy || !window.confirm('Submit this report for management review?')) return;
        clearMessages();
        busy(true);
        try {
            await AgreementApi.updatePerformanceReport(state.id, payload());
            await AgreementApi.submitPerformanceReport(state.id);
            await reload();
            message(elements.feedback, 'Performance report submitted for review.');
        } catch (error) {
            message(elements.alert, error.message || 'The report could not be submitted.');
        } finally { busy(false); }
    });

    async function decide(decision) {
        if (state.busy) return;
        const comments = elements.reviewComments.value.trim();
        if (decision === 'RETURN' && !comments) {
            message(elements.alert, 'Explain the required changes before returning the report.');
            return;
        }
        if (!window.confirm(decision === 'ACCEPT' ? 'Accept this performance report?' : 'Return this report for changes?')) return;
        clearMessages();
        busy(true);
        try {
            await AgreementApi.reviewPerformanceReport(state.id, { decision, comments });
            await reload();
            message(elements.feedback, decision === 'ACCEPT' ? 'Performance report accepted.' : 'Performance report returned for changes.');
        } catch (error) {
            message(elements.alert, error.message || 'The review decision could not be recorded.');
        } finally { busy(false); }
    }
    elements.accept.addEventListener('click', () => decide('ACCEPT'));
    elements.returnReport.addEventListener('click', () => decide('RETURN'));

    (async function initialize() {
        try {
            state.id = id();
            await AgreementApi.requireSession();
            await reload();
        } catch (error) {
            elements.loading.classList.add('d-none');
            message(elements.alert, error.message || 'The performance report could not be loaded.');
        }
    })();
})();
