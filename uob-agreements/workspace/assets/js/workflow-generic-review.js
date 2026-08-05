(function () {
    'use strict';

    const isArabic = document.documentElement.lang === 'ar';
    const t = (english, arabic) => isArabic ? arabic : english;
    const params = new URLSearchParams(window.location.search);
    const instanceId = Number(params.get('instance_id') || params.get('id'));

    const elements = {
        alert: document.querySelector('[data-review-alert]'),
        success: document.querySelector('[data-review-success]'),
        loading: document.querySelector('[data-review-loading]'),
        content: document.querySelector('[data-review-content]'),
        pageTitle: document.querySelector('[data-review-page-title]'),
        pageMeta: document.querySelector('[data-review-page-meta]'),
        phase: document.querySelector('[data-review-phase]'),
        stageLabel: document.querySelector('[data-review-stage-label]'),
        responsibility: document.querySelector('[data-review-responsibility]'),
        agreementTitle: document.querySelector('[data-review-agreement-title]'),
        agreementDescription: document.querySelector('[data-review-agreement-description]'),
        agreementLink: document.querySelector('[data-review-agreement-link]'),
        phaseSteps: document.querySelector('[data-review-phase-steps]'),
        optionalPanel: document.querySelector('[data-review-optional-panel]'),
        optionalList: document.querySelector('[data-review-optional-list]'),
        form: document.querySelector('[data-review-form]'),
        comment: document.querySelector('[data-review-comment]'),
        actions: Array.from(document.querySelectorAll('[data-review-action]'))
    };

    const state = {
        detail: null,
        busy: false
    };

    function showError(message) {
        elements.success.classList.add('d-none');
        elements.alert.textContent = message;
        elements.alert.classList.remove('d-none');
        elements.alert.focus({ preventScroll: true });
    }

    function showSuccess(message) {
        elements.alert.classList.add('d-none');
        elements.success.textContent = message;
        elements.success.classList.remove('d-none');
        elements.success.focus({ preventScroll: true });
    }

    function statusLabel(status) {
        const value = String(status || '').toUpperCase();
        const labels = {
            IN_PROGRESS: t('In progress', 'قيد المراجعة'),
            APPROVED: t('Approved', 'معتمدة'),
            PENDING: t('Pending', 'بانتظار الدور'),
            SKIPPED: t('Skipped', 'متخطاة'),
            REJECTED: t('Rejected', 'مرفوضة'),
            CHANGES_REQUESTED: t('Changes requested', 'مطلوب تعديل')
        };
        return labels[value] || value.replaceAll('_', ' ');
    }

    function renderPhaseSteps(steps) {
        elements.phaseSteps.replaceChildren();
        steps.forEach((step) => {
            const row = document.createElement('div');
            row.className = 'generic-phase-step';
            const copy = document.createElement('div');
            const title = document.createElement('strong');
            title.textContent = step.step_label;
            const meta = document.createElement('small');
            meta.textContent = [
                step.assigned_position_name,
                step.assigned_unit_name,
                step.reviewer_names
            ].filter(Boolean).join(' · ') || t('Responsibility resolved by the template', 'المسؤولية محددة حسب القالب');
            copy.append(title, meta);
            const status = document.createElement('span');
            status.className = 'generic-step-status';
            status.classList.toggle(
                'is-progress',
                String(step.status).toUpperCase() === 'IN_PROGRESS'
            );
            status.classList.toggle(
                'is-approved',
                String(step.status).toUpperCase() === 'APPROVED'
            );
            status.textContent = statusLabel(step.status);
            row.append(copy, status);
            elements.phaseSteps.append(row);
        });
    }

    function renderOptionalSteps(steps) {
        elements.optionalList.replaceChildren();
        elements.optionalPanel.classList.toggle('d-none', steps.length === 0);
        steps.forEach((step, index) => {
            const wrapper = document.createElement('div');
            wrapper.className = 'form-check form-switch';
            const input = document.createElement('input');
            input.className = 'form-check-input';
            input.type = 'checkbox';
            input.id = `optional-stage-${index}`;
            input.value = step.step_key;
            input.dataset.optionalStep = '';
            const label = document.createElement('label');
            label.className = 'form-check-label';
            label.htmlFor = input.id;
            label.textContent = step.step_label;
            wrapper.append(input, label);
            elements.optionalList.append(wrapper);
        });
    }

    function render(detail) {
        state.detail = detail;
        elements.loading.classList.add('d-none');
        elements.content.classList.remove('d-none');
        elements.pageTitle.textContent = detail.agreement_title;
        elements.pageMeta.textContent = t(
            `Workflow version ${detail.template_version_number} · phase ${detail.phase_order}`,
            `إصدار سير العمل ${detail.template_version_number} · المجموعة ${detail.phase_order}`
        );
        elements.phase.textContent = String(detail.phase_order);
        elements.stageLabel.textContent = detail.step_label;
        elements.responsibility.textContent = [
            detail.assigned_position_name,
            detail.assigned_unit_name
        ].filter(Boolean).join(' · ');
        elements.agreementTitle.textContent = detail.agreement_title;
        elements.agreementDescription.textContent = detail.agreement_description
            || t('No description was provided.', 'لا يوجد وصف مسجل.');
        elements.agreementLink.href = `agreement.php?id=${encodeURIComponent(detail.agreement_id)}`;
        renderPhaseSteps(detail.phase_steps || []);
        renderOptionalSteps(detail.next_optional_steps || []);

        const returnButton = elements.actions.find(
            (button) => button.dataset.reviewAction === 'REQUEST_CHANGES'
        );
        if (returnButton) {
            returnButton.classList.toggle('d-none', !detail.allow_revision);
        }
    }

    async function load() {
        if (!Number.isInteger(instanceId) || instanceId < 1) {
            throw new Error(t(
                'The Workflow instance identifier is missing.',
                'رقم نسخة سير العمل غير موجود.'
            ));
        }
        const detail = await AgreementApi.request(
            `/configurable-workflows/${encodeURIComponent(instanceId)}`
        );
        render(detail);
    }

    async function decide(action) {
        if (state.busy) return;
        const comment = elements.comment.value.trim();
        if (['REQUEST_CHANGES', 'REJECT'].includes(action) && comment.length < 10) {
            showError(t(
                'Enter a reason of at least 10 characters.',
                'اكتبي سببًا لا يقل عن 10 أحرف.'
            ));
            elements.comment.focus();
            return;
        }

        const includeOptional = Array.from(
            document.querySelectorAll('[data-optional-step]:checked')
        ).map((input) => input.value);

        state.busy = true;
        elements.actions.forEach((button) => {
            button.disabled = true;
        });
        try {
            const result = await AgreementApi.request(
                `/configurable-workflows/${encodeURIComponent(instanceId)}/decision`,
                {
                    method: 'POST',
                    body: JSON.stringify({
                        action,
                        comment: comment || null,
                        include_optional_step_keys: includeOptional
                    })
                }
            );
            showSuccess(t(
                'The Workflow decision was saved.',
                'تم حفظ قرار سير العمل.'
            ));
            window.setTimeout(() => {
                if (result.status === 'DRAFT') {
                    window.location.assign(
                        `agreement-form.php?id=${encodeURIComponent(result.agreement_id)}`
                    );
                } else {
                    window.location.assign('workflow-inbox.php');
                }
            }, 650);
        } finally {
            state.busy = false;
            elements.actions.forEach((button) => {
                button.disabled = false;
            });
        }
    }

    elements.actions.forEach((button) => {
        button.addEventListener('click', () => {
            decide(button.dataset.reviewAction).catch((error) => {
                showError(error?.message || t(
                    'The decision could not be saved.',
                    'تعذر حفظ القرار.'
                ));
            });
        });
    });

    AgreementApi.requireSession()
        .then(load)
        .catch((error) => {
            elements.loading.classList.add('d-none');
            showError(error?.message || t(
                'The review assignment could not be loaded.',
                'تعذر تحميل مهمة المراجعة.'
            ));
        });
}());
