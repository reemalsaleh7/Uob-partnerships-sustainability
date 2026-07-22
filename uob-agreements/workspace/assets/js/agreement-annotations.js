(function () {
    'use strict';

    const elements = {
        alert: document.getElementById('detail-alert'),
        composer: document.querySelector('[data-annotation-composer]'),
        fieldLabel: document.querySelector('[data-annotation-field-label]'),
        selection: document.querySelector('[data-annotation-selection]'),
        comment: document.querySelector('[data-annotation-comment]'),
        visibility: document.querySelector('[data-annotation-visibility]'),
        save: document.querySelector('[data-save-annotation]'),
        close: document.querySelector('[data-close-annotation]'),
        loading: document.querySelector('[data-annotation-loading]'),
        empty: document.querySelector('[data-annotation-empty]'),
        list: document.querySelector('[data-annotation-list]'),
        count: document.querySelector('[data-annotation-count]'),
        changeReview: document.querySelector('[data-change-review]'),
        changeRange: document.querySelector('[data-change-range]'),
        changeReason: document.querySelector('[data-change-reason]'),
        changeList: document.querySelector('[data-change-list]'),
        dismissChanges: document.querySelector('[data-dismiss-changes]')
    };

    const state = {
        agreementId: null,
        latestVersion: null,
        anchor: null,
        annotations: []
    };

    function agreementId() {
        const value = new URLSearchParams(window.location.search).get('id');
        if (!value || !/^\d+$/.test(value)) {
            throw new AgreementApi.ApiError('A valid Agreement ID is required.', 422, null);
        }
        return value;
    }

    function fieldTargets() {
        const targets = new Map();
        document.querySelectorAll('[data-field], [data-annotation-field]').forEach((target) => {
            const key = target.dataset.annotationField || target.dataset.field;
            if (key && !targets.has(key)) targets.set(key, target);
        });
        return targets;
    }

    function fieldLabel(target) {
        const container = target.closest('div');
        const label = container?.querySelector('dt')?.textContent
            || target.dataset.annotationField
            || target.dataset.field
            || 'Agreement field';
        return label.trim();
    }

    function selectedRange(target) {
        const selection = window.getSelection();
        if (!selection || selection.rangeCount === 0 || selection.isCollapsed) return null;
        const range = selection.getRangeAt(0);
        if (!target.contains(range.commonAncestorContainer)) return null;
        const selectedText = range.toString().trim();
        if (!selectedText) return null;

        const before = range.cloneRange();
        before.selectNodeContents(target);
        before.setEnd(range.startContainer, range.startOffset);
        const start = before.toString().length;
        return {
            selected_text: selectedText,
            selection_start: start,
            selection_end: start + range.toString().length
        };
    }

    function decorateFields() {
        fieldTargets().forEach((target, key) => {
            if (target.dataset.annotationReady === 'true') return;
            target.dataset.annotationReady = 'true';
            const container = target.closest('div') || target.parentElement;
            if (!container) return;
            container.classList.add('annotation-target');

            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'field-comment-button';
            button.dataset.commentField = key;
            button.setAttribute('aria-label', `Comment on ${fieldLabel(target)}`);
            button.textContent = 'Comment on this field';
            container.append(button);
        });
    }

    function openComposer(fieldKey, target) {
        const range = selectedRange(target);
        state.anchor = {
            field_key: fieldKey,
            selected_text: range?.selected_text || null,
            selection_start: range?.selection_start ?? null,
            selection_end: range?.selection_end ?? null
        };
        elements.fieldLabel.textContent = fieldLabel(target);
        elements.selection.textContent = range?.selected_text ? `“${range.selected_text}”` : '';
        elements.selection.classList.toggle('d-none', !range?.selected_text);
        elements.comment.value = '';
        elements.visibility.value = 'SHARED';
        elements.composer.classList.remove('d-none');
        elements.composer.scrollIntoView({ behavior: 'smooth', block: 'center' });
        elements.comment.focus({ preventScroll: true });
    }

    function closeComposer() {
        state.anchor = null;
        elements.composer.classList.add('d-none');
        window.getSelection()?.removeAllRanges();
    }

    function showError(error) {
        elements.alert.textContent = error.message || 'The comment action could not be completed.';
        elements.alert.classList.remove('d-none');
        elements.alert.focus();
    }

    function annotationCard(annotation) {
        const card = document.createElement('article');
        card.className = `annotation-card ${annotation.status === 'RESOLVED' ? 'is-resolved' : ''}`;

        const header = document.createElement('div');
        header.className = 'annotation-card-header';
        const identity = document.createElement('div');
        const title = document.createElement('strong');
        title.textContent = annotation.field_label || annotation.field_key;
        const meta = document.createElement('small');
        meta.textContent = [
            annotation.author_name || annotation.author_email,
            `Version ${annotation.version_number}`,
            AgreementApi.formatDate(annotation.created_at)
        ].filter(Boolean).join(' · ');
        identity.append(title, meta);
        const badges = document.createElement('div');
        badges.className = 'annotation-badges';
        const visibility = document.createElement('span');
        visibility.className = `annotation-visibility ${annotation.is_private ? 'is-private' : ''}`;
        visibility.textContent = annotation.is_private ? 'Private · only you' : 'Shared';
        badges.append(visibility);
        if (annotation.status === 'RESOLVED') {
            const resolved = document.createElement('span');
            resolved.className = 'annotation-visibility is-resolved';
            resolved.textContent = 'Resolved';
            badges.append(resolved);
        }
        header.append(identity, badges);

        const body = document.createElement('p');
        body.className = 'annotation-comment';
        body.textContent = annotation.comment_text;
        card.append(header);
        if (annotation.selected_text) {
            const quote = document.createElement('blockquote');
            quote.className = 'annotation-selection';
            quote.textContent = `“${annotation.selected_text}”`;
            card.append(quote);
        }
        card.append(body);

        const actions = document.createElement('div');
        actions.className = 'annotation-actions';
        const locate = document.createElement('button');
        locate.type = 'button';
        locate.className = 'btn btn-sm btn-outline-primary';
        locate.dataset.locateField = annotation.field_key;
        locate.textContent = 'Show field';
        actions.append(locate);
        if (annotation.can_resolve) {
            const resolve = document.createElement('button');
            resolve.type = 'button';
            resolve.className = 'btn btn-sm btn-outline-success';
            resolve.dataset.resolveAnnotation = annotation.annotation_id;
            resolve.textContent = 'Resolve';
            actions.append(resolve);
        }
        if (annotation.can_delete) {
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'btn btn-sm btn-outline-danger';
            remove.dataset.deleteAnnotation = annotation.annotation_id;
            remove.textContent = 'Delete';
            actions.append(remove);
        }
        card.append(actions);
        return card;
    }

    function applyAnnotationHighlights(annotations) {
        const targets = fieldTargets();
        document.querySelectorAll('.annotation-count-badge').forEach((badge) => badge.remove());
        document.querySelectorAll('.has-field-comments').forEach((element) => element.classList.remove('has-field-comments'));
        const counts = new Map();
        annotations.filter((item) => item.status === 'OPEN').forEach((item) => {
            counts.set(item.field_key, (counts.get(item.field_key) || 0) + 1);
        });
        counts.forEach((count, key) => {
            const target = targets.get(key);
            const container = target?.closest('div');
            if (!target || !container) return;
            container.classList.add('has-field-comments');
            const badge = document.createElement('span');
            badge.className = 'annotation-count-badge';
            badge.textContent = `${count} comment${count === 1 ? '' : 's'}`;
            container.append(badge);
        });
    }

    function renderAnnotations(annotations) {
        state.annotations = Array.isArray(annotations) ? annotations : [];
        elements.loading.classList.add('d-none');
        elements.empty.classList.toggle('d-none', state.annotations.length !== 0);
        elements.list.classList.toggle('d-none', state.annotations.length === 0);
        elements.list.replaceChildren(...state.annotations.map(annotationCard));
        const open = state.annotations.filter((item) => item.status === 'OPEN').length;
        elements.count.textContent = `${open} open`;
        applyAnnotationHighlights(state.annotations);
    }

    function clearChangeHighlights() {
        document.querySelectorAll('.has-unseen-change').forEach((element) => element.classList.remove('has-unseen-change'));
        document.querySelectorAll('.field-change-badge').forEach((badge) => badge.remove());
    }

    function renderChanges(context, forceOpen = false) {
        clearChangeHighlights();
        const changes = Array.isArray(context?.changes) ? context.changes : [];
        if (!changes.length) {
            if (forceOpen) {
                elements.changeRange.textContent = 'No field values changed between these versions.';
                elements.changeReason.textContent = context?.revision_reason || 'No content changes recorded.';
                elements.changeList.replaceChildren();
                elements.changeReview.classList.remove('d-none');
            } else {
                elements.changeReview.classList.add('d-none');
            }
            return;
        }

        elements.changeRange.textContent = `Comparing version ${context.from_version} with version ${context.to_version}`;
        elements.changeReason.textContent = `Reason: ${context.revision_reason || 'No reason recorded'}`;
        elements.changeList.replaceChildren();
        const targets = fieldTargets();

        changes.forEach((change) => {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'change-review-item';
            item.dataset.locateField = change.field_key;
            const title = document.createElement('strong');
            title.textContent = change.field_label;
            const values = document.createElement('span');
            values.className = 'change-values';
            const before = document.createElement('del');
            before.textContent = change.before ?? 'Not set';
            const after = document.createElement('ins');
            after.textContent = change.after ?? 'Removed';
            values.append(before, after);
            item.append(title, values);
            elements.changeList.append(item);

            const target = targets.get(change.field_key);
            const container = target?.closest('div');
            if (target && container) {
                container.classList.add('has-unseen-change');
                const badge = document.createElement('span');
                badge.className = 'field-change-badge';
                badge.textContent = 'Changed';
                container.append(badge);
            }
        });
        elements.changeReview.classList.remove('d-none');
    }

    async function reloadAnnotations() {
        renderAnnotations(await AgreementApi.agreementAnnotations(state.agreementId));
    }

    async function initialize() {
        try {
            state.agreementId = agreementId();
            await AgreementApi.requireSession('VIEW_AGREEMENT');
            decorateFields();
            const [annotations, context] = await Promise.all([
                AgreementApi.agreementAnnotations(state.agreementId),
                AgreementApi.agreementReviewContext(state.agreementId)
            ]);
            state.latestVersion = context.latest_version;
            renderAnnotations(annotations);
            renderChanges(context);
            if (state.latestVersion) {
                window.setTimeout(() => {
                    AgreementApi.markAgreementViewed(state.agreementId, state.latestVersion).catch(() => {});
                }, 1500);
            }
        } catch (error) {
            elements.loading.classList.add('d-none');
            showError(error);
        }
    }

    document.addEventListener('click', async (event) => {
        const commentButton = event.target.closest('[data-comment-field]');
        if (commentButton) {
            const target = fieldTargets().get(commentButton.dataset.commentField);
            if (target) openComposer(commentButton.dataset.commentField, target);
            return;
        }

        const locate = event.target.closest('[data-locate-field]');
        if (locate) {
            const target = fieldTargets().get(locate.dataset.locateField);
            if (target) {
                target.closest('div')?.classList.add('field-locate-pulse');
                target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                window.setTimeout(() => target.closest('div')?.classList.remove('field-locate-pulse'), 1600);
            }
            return;
        }

        const compare = event.target.closest('[data-compare-version]');
        if (compare) {
            try {
                const to = Number(compare.dataset.compareVersion);
                renderChanges(await AgreementApi.agreementReviewContext(state.agreementId, to - 1, to), true);
                elements.changeReview.scrollIntoView({ behavior: 'smooth', block: 'start' });
            } catch (error) { showError(error); }
            return;
        }

        const resolve = event.target.closest('[data-resolve-annotation]');
        if (resolve) {
            try {
                resolve.disabled = true;
                await AgreementApi.resolveAgreementAnnotation(state.agreementId, resolve.dataset.resolveAnnotation);
                await reloadAnnotations();
            } catch (error) { showError(error); }
            return;
        }

        const remove = event.target.closest('[data-delete-annotation]');
        if (remove) {
            if (!window.confirm('Delete this comment permanently?')) return;
            try {
                remove.disabled = true;
                await AgreementApi.deleteAgreementAnnotation(state.agreementId, remove.dataset.deleteAnnotation);
                await reloadAnnotations();
            } catch (error) { showError(error); }
        }
    });

    elements.save.addEventListener('click', async () => {
        const comment = elements.comment.value.trim();
        if (!state.anchor || !comment) {
            elements.comment.focus();
            return;
        }
        elements.save.disabled = true;
        try {
            await AgreementApi.createAgreementAnnotation(state.agreementId, {
                ...state.anchor,
                comment_text: comment,
                visibility: elements.visibility.value
            });
            closeComposer();
            await reloadAnnotations();
        } catch (error) {
            showError(error);
        } finally {
            elements.save.disabled = false;
        }
    });

    elements.close.addEventListener('click', closeComposer);
    elements.dismissChanges.addEventListener('click', () => elements.changeReview.classList.add('d-none'));
    initialize();
}());
