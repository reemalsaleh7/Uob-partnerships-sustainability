(function () {
    'use strict';

    const params = new URLSearchParams(window.location.search);
    const initiativeId = Number(params.get('id'));
    const alert = document.querySelector(
        '[data-initiative-alert]'
    );
    const loading = document.querySelector(
        '[data-initiative-loading]'
    );
    const content = document.querySelector(
        '[data-initiative-content]'
    );

    function value(value) {
        const normalized = String(value ?? '').trim();
        return normalized === '' ? '—' : normalized;
    }

    function setText(selector, data) {
        const element = document.querySelector(selector);
        if (element) {
            element.textContent = value(data);
        }
    }

    function label(valueToFormat) {
        return value(valueToFormat)
            .replaceAll('_', ' ')
            .replace(/\b\w/g, (letter) => letter.toUpperCase());
    }

    function showError(error) {
        alert.textContent =
            error?.message
            || 'The Initiative could not be loaded.';
        alert.classList.remove('d-none');
    }

    function renderParticipants(participants) {
        const container = document.querySelector(
            '[data-initiative-participants]'
        );
        container.replaceChildren();

        if (!Array.isArray(participants) || participants.length === 0) {
            const empty = document.createElement('p');
            empty.className = 'text-secondary mb-0';
            empty.textContent = 'No additional participants.';
            container.append(empty);
            return;
        }

        participants.forEach((participant) => {
            const row = document.createElement('div');
            row.className = 'initiative-record-person';

            const identity = document.createElement('div');
            const name = document.createElement('strong');
            name.textContent = value(
                participant.full_name || participant.email
            );
            const email = document.createElement('div');
            email.className = 'small text-secondary';
            email.textContent = value(participant.email);
            identity.append(name, email);

            const role = document.createElement('span');
            role.className = 'initiative-record-role';
            role.textContent = label(
                participant.participant_role
            );

            row.append(identity, role);
            container.append(row);
        });
    }

    function renderSource(data) {
        const source = document.querySelector(
            '[data-initiative-source]'
        );
        source.replaceChildren();

        if (!data.source_request_id) {
            return;
        }

        const line = document.createElement('p');
        const strong = document.createElement('strong');
        strong.textContent = 'Source request: ';
        const link = document.createElement('a');
        link.href =
            `initiative-workflow.php?view=detail&id=${
                encodeURIComponent(data.source_request_id)
            }`;
        link.textContent = value(
            data.source_request_code
            || `Request #${data.source_request_id}`
        );
        line.append(strong, link);
        source.append(line);

        const back = document.querySelector(
            '[data-initiative-back]'
        );
        back.href = link.href;
        back.textContent = '← Back to Initiative request';
    }

    function renderAgreements(agreements) {
        const container = document.querySelector(
            '[data-initiative-agreements]'
        );
        container.replaceChildren();

        if (!Array.isArray(agreements) || agreements.length === 0) {
            return;
        }

        agreements.forEach((agreement) => {
            const block = document.createElement('div');
            block.className = 'mb-3';

            const line = document.createElement('p');
            line.className = 'mb-1';
            const strong = document.createElement('strong');
            strong.textContent = 'Related Agreement: ';
            const title = document.createElement('span');
            title.textContent = value(
                agreement.title
            );
            line.append(strong, title);
            block.append(line);

            if (String(agreement.relation_notes || '').trim() !== '') {
                const notes = document.createElement('p');
                notes.className = 'small text-secondary mb-0';
                notes.textContent = agreement.relation_notes;
                block.append(notes);
            }

            container.append(block);
        });
    }

    function renderVersions(versions) {
        const container = document.querySelector(
            '[data-initiative-versions]'
        );
        container.replaceChildren();

        if (!Array.isArray(versions) || versions.length === 0) {
            const empty = document.createElement('p');
            empty.className = 'text-secondary mb-0';
            empty.textContent = 'No saved versions.';
            container.append(empty);
            return;
        }

        versions.forEach((version) => {
            const row = document.createElement('div');
            row.className = 'initiative-record-person';

            const copy = document.createElement('div');
            const title = document.createElement('strong');
            title.textContent =
                `Version ${version.version_number}`;
            const summary = document.createElement('div');
            summary.className = 'small text-secondary';
            summary.textContent = value(
                version.change_summary
            );
            copy.append(title, summary);

            const date = document.createElement('span');
            date.className = 'small text-secondary';
            date.textContent = AgreementApi.formatDate(
                version.created_at
            );

            row.append(copy, date);
            container.append(row);
        });
    }

    function render(data) {
        setText(
            '[data-initiative-code]',
            data.initiative_code
            || `Initiative #${data.initiative_id}`
        );
        setText('[data-initiative-title]', data.title);
        setText(
            '[data-initiative-owner]',
            data.creator_name || data.creator_email
        );
        setText(
            '[data-initiative-type]',
            label(data.initiative_type)
        );
        setText(
            '[data-initiative-description]',
            data.description
        );
        setText(
            '[data-initiative-objectives]',
            data.objectives
        );
        setText(
            '[data-initiative-impact]',
            data.expected_impact
        );
        setText(
            '[data-initiative-beneficiaries]',
            data.beneficiaries
        );
        setText(
            '[data-initiative-start]',
            data.planned_start_date
        );
        setText(
            '[data-initiative-end]',
            data.planned_end_date
        );
        setText(
            '[data-initiative-approved]',
            AgreementApi.formatDate(data.final_decision_at)
        );
        setText(
            '[data-initiative-updated]',
            AgreementApi.formatDate(data.updated_at)
        );

        const budget = data.expected_budget;
        setText(
            '[data-initiative-budget]',
            budget === null || budget === ''
                ? '—'
                : Number(budget).toLocaleString(
                    undefined,
                    {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    }
                )
        );

        const statusContainer = document.querySelector(
            '[data-initiative-status]'
        );
        statusContainer.replaceChildren(
            AgreementApi.createStatusBadge(data.status)
        );

        renderParticipants(data.participants);
        renderSource(data);
        renderAgreements(data.agreements);
        renderVersions(data.versions);
    }

    async function initialize() {
        if (!Number.isInteger(initiativeId) || initiativeId < 1) {
            showError({
                message: 'The Initiative ID is missing.'
            });
            loading.classList.add('d-none');
            return;
        }

        try {
            await AgreementApi.requireSession();
            const data = await AgreementApi.request(
                `/initiative-requests/converted-initiatives/${
                    encodeURIComponent(initiativeId)
                }`
            );
            render(data);
            content.classList.remove('d-none');
        } catch (error) {
            showError(error);
        } finally {
            loading.classList.add('d-none');
        }
    }

    initialize();
}());
