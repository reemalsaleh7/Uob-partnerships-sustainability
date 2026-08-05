(() => {
    'use strict';

    const MARKER = 'initiative-revision-audience-v2';
    // INITIATIVE_REVISION_AUDIENCE_RUNTIME_FIX_V2
    const isArabic = () => {
        const lang = String(document.documentElement.lang || '').toLowerCase();
        return lang.startsWith('ar') || document.documentElement.dir === 'rtl';
    };

    const labels = () => isArabic() ? {
        privateOption: 'ملاحظة خاصة لأشخاص محددين',
        peopleTitle: 'من يستطيع مشاهدة الملاحظة؟',
        searchPlaceholder: 'ابحث بالاسم أو البريد الإلكتروني أو الرقم الجامعي...',
        searchHint: 'اكتب حرفين على الأقل. تظهر فقط الجهات المرتبطة بهذا الطلب.',
        noResults: 'لا توجد نتائج مطابقة.',
        searching: 'جاري البحث...',
        required: 'اختر شخصًا واحدًا على الأقل قبل نشر الملاحظة الخاصة.',
        selected: 'الأشخاص المختارون',
        authorAccess: 'كاتب الملاحظة يحتفظ بالوصول تلقائيًا.',
        privateLabel: 'خاصة',
        privateTooltip: 'هذه الملاحظة مرئية لأشخاص محددين فقط.',
        viewers: 'عرض المشاهدين',
        remove: 'إزالة',
    } : {
        privateOption: 'Private note for selected people',
        peopleTitle: 'Who can view this note?',
        searchPlaceholder: 'Search by name, email, or University ID...',
        searchHint: 'Type at least two characters. Only people related to this request are shown.',
        noResults: 'No matching people found.',
        searching: 'Searching...',
        required: 'Choose at least one person before posting the private note.',
        selected: 'Selected people',
        authorAccess: 'The note author keeps access automatically.',
        privateLabel: 'Private',
        privateTooltip: 'This note is visible only to selected people.',
        viewers: 'View audience',
        remove: 'Remove',
    };

    const stateByForm = new WeakMap();
    let pendingAudience = null;
    let originalRequest = null;

    const requestIdFromPage = () => {
        const params = new URLSearchParams(window.location.search);
        const id = Number(params.get('id') || params.get('request_id') || 0);
        if (id > 0) return id;
        const holder = document.querySelector('[data-request-id]');
        return Number(holder?.dataset.requestId || holder?.value || 0);
    };

    const isPrivateSelect = (select) => {
        if (!(select instanceof HTMLSelectElement)) return false;
        return [...select.options].some((option) => {
            const value = String(option.value || '').toLowerCase();
            const text = String(option.textContent || '').trim().toLowerCase();
            return value === 'private'
                || text === 'private note'
                || text.includes('ملاحظة خاصة')
                || text === 'خاص'
                || text === 'خاصة';
        });
    };

    const selectedIsPrivate = (select) => {
        const option = select.options[select.selectedIndex];
        const value = String(select.value || '').toLowerCase();
        const text = String(option?.textContent || '').trim().toLowerCase();
        return value === 'private'
            || text === 'private note'
            || text.includes('ملاحظة خاصة')
            || text === 'خاص'
            || text === 'خاصة';
    };

    const findForm = (select) => select.closest('form')
        || select.closest('[data-revision-thread-id]')
        || select.closest('.initiative-revision-thread')
        || select.parentElement;

    const threadIdFor = (form) => {
        const direct = Number(form?.dataset?.revisionThreadId || 0);
        if (direct > 0) return direct;
        const field = form?.querySelector?.(
            '[name="revision_thread_id"], [data-thread-id]'
        );
        return Number(field?.value || field?.dataset?.threadId || 0);
    };

    const lockSvg = () => `
        <svg class="initiative-note-lock-icon" viewBox="0 0 24 24"
             aria-hidden="true" focusable="false">
            <path d="M7 10V8a5 5 0 0 1 10 0v2h1.2A1.8 1.8 0 0 1 20 11.8v8.4A1.8 1.8 0 0 1 18.2 22H5.8A1.8 1.8 0 0 1 4 20.2v-8.4A1.8 1.8 0 0 1 5.8 10H7Zm2 0h6V8a3 3 0 0 0-6 0v2Zm3 4a2 2 0 0 0-1 3.73V19h2v-1.27A2 2 0 0 0 12 14Z"/>
        </svg>`;

    const renderSelected = (state) => {
        const t = labels();
        state.chips.innerHTML = '';
        state.selected.forEach((person, userId) => {
            const chip = document.createElement('span');
            chip.className = 'initiative-audience-chip';
            chip.innerHTML = `
                <span class="initiative-audience-chip-name"></span>
                <button type="button" class="initiative-audience-remove"
                        aria-label="${t.remove}">×</button>`;
            chip.querySelector('.initiative-audience-chip-name').textContent =
                person.full_name || person.email || String(userId);
            chip.querySelector('button').addEventListener('click', () => {
                state.selected.delete(userId);
                renderSelected(state);
            });
            state.chips.appendChild(chip);
        });
        state.selectedWrap.classList.toggle(
            'd-none',
            state.selected.size === 0
        );
        state.error.classList.add('d-none');
    };

    const renderResults = (state, people) => {
        const t = labels();
        state.results.innerHTML = '';
        if (!people.length) {
            const empty = document.createElement('div');
            empty.className = 'initiative-audience-empty';
            empty.textContent = t.noResults;
            state.results.appendChild(empty);
            state.results.classList.remove('d-none');
            return;
        }

        people.forEach((person) => {
            const id = Number(person.user_id);
            if (!id || state.selected.has(id)) return;
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'initiative-audience-result';
            button.innerHTML = `
                <span class="initiative-audience-result-name"></span>
                <span class="initiative-audience-result-meta"></span>`;
            button.querySelector('.initiative-audience-result-name').textContent =
                person.full_name || person.email || String(id);
            button.querySelector('.initiative-audience-result-meta').textContent =
                [person.email, person.university_id, person.relation_label]
                    .filter(Boolean)
                    .join(' · ');
            button.addEventListener('click', () => {
                state.selected.set(id, person);
                state.search.value = '';
                state.results.classList.add('d-none');
                renderSelected(state);
            });
            state.results.appendChild(button);
        });
        state.results.classList.remove('d-none');
    };

    const searchPeople = async (state) => {
        const query = state.search.value.trim();
        const t = labels();
        if (query.length < 2) {
            state.results.classList.add('d-none');
            state.results.innerHTML = '';
            return;
        }

        const requestId = requestIdFromPage();
        if (!requestId || !originalRequest) return;
        const token = ++state.searchToken;
        state.results.innerHTML = `<div class="initiative-audience-empty">${t.searching}</div>`;
        state.results.classList.remove('d-none');

        try {
            const response = await originalRequest(
                `/initiative-requests/${requestId}/revision-note-viewers?q=${encodeURIComponent(query)}`,
                { method: 'GET' }
            );
            if (token !== state.searchToken) return;
            const data = response?.data ?? response ?? {};
            const people = Array.isArray(data.items)
                ? data.items
                : (Array.isArray(data) ? data : []);
            renderResults(state, people);
        } catch (error) {
            if (token !== state.searchToken) return;
            state.results.innerHTML = `<div class="initiative-audience-empty">${String(error?.message || t.noResults)}</div>`;
            state.results.classList.remove('d-none');
        }
    };

    const togglePicker = (state) => {
        const show = selectedIsPrivate(state.select);
        state.panel.classList.toggle('d-none', !show);
        if (!show) {
            state.error.classList.add('d-none');
            state.results.classList.add('d-none');
        }
    };

    const enhanceSelect = (select) => {
        if (select.dataset.revisionAudienceEnhanced === '1') return;
        if (!isPrivateSelect(select)) return;
        select.dataset.revisionAudienceEnhanced = '1';
        const form = findForm(select);
        if (!form) return;
        const t = labels();

        const privateOption = [...select.options].find((option) => {
            const value = String(option.value || '').toLowerCase();
            const text = String(option.textContent || '').trim().toLowerCase();
            return value === 'private'
                || text === 'private note'
                || text.includes('ملاحظة خاصة')
                || text === 'خاص'
                || text === 'خاصة';
        });
        if (privateOption) privateOption.textContent = t.privateOption;

        const panel = document.createElement('div');
        panel.className = 'initiative-audience-picker d-none';
        panel.dataset.revisionAudiencePicker = '1';
        panel.innerHTML = `
            <div class="initiative-audience-title">
                ${lockSvg()}<span>${t.peopleTitle}</span>
            </div>
            <div class="initiative-audience-search-wrap">
                <input type="search" class="form-control initiative-audience-search"
                       autocomplete="off" placeholder="${t.searchPlaceholder}">
                <div class="initiative-audience-results d-none" role="listbox"></div>
            </div>
            <p class="initiative-audience-hint">${t.searchHint}</p>
            <div class="initiative-audience-selected d-none">
                <div class="initiative-audience-selected-label">${t.selected}</div>
                <div class="initiative-audience-chips"></div>
            </div>
            <p class="initiative-audience-author-note">${t.authorAccess}</p>
            <div class="initiative-audience-error d-none" role="alert">${t.required}</div>`;
        select.insertAdjacentElement('afterend', panel);

        const state = {
            form,
            select,
            panel,
            search: panel.querySelector('.initiative-audience-search'),
            results: panel.querySelector('.initiative-audience-results'),
            selectedWrap: panel.querySelector('.initiative-audience-selected'),
            chips: panel.querySelector('.initiative-audience-chips'),
            error: panel.querySelector('.initiative-audience-error'),
            selected: new Map(),
            searchToken: 0,
            timer: null,
        };
        stateByForm.set(form, state);

        state.search.addEventListener('input', () => {
            clearTimeout(state.timer);
            state.timer = setTimeout(() => searchPeople(state), 250);
        });
        state.search.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                state.results.classList.add('d-none');
            }
        });
        select.addEventListener('change', () => togglePicker(state));
        togglePicker(state);
    };

    const prepareAudience = (form) => {
        const state = stateByForm.get(form);
        if (!state || !selectedIsPrivate(state.select)) {
            pendingAudience = null;
            if (form instanceof Element) {
                delete form.dataset.revisionAudienceViewerIds;
            }
            return true;
        }
        if (state.selected.size === 0) {
            state.error.classList.remove('d-none');
            state.search.focus();
            return false;
        }

        const viewerIds = [...state.selected.keys()];
        form.dataset.revisionAudienceViewerIds = JSON.stringify(viewerIds);
        pendingAudience = {
            requestId: requestIdFromPage(),
            threadId: threadIdFor(form),
            viewerIds,
            expiresAt: Date.now() + 8000,
        };
        return true;
    };

    const patchApi = () => {
        const api = window.AgreementApi;
        if (!api || typeof api.request !== 'function') return false;
        originalRequest = api.request.bind(api);
        return true;
    };

    const protectSubmissions = () => {
        document.addEventListener('submit', (event) => {
            const form = event.target;
            if (!(form instanceof Element)) return;
            if (!stateByForm.has(form)) return;
            if (!prepareAudience(form)) {
                event.preventDefault();
                event.stopImmediatePropagation();
            }
        }, true);

        document.addEventListener('click', (event) => {
            const button = event.target.closest(
                'button[type="submit"], input[type="submit"], [data-post-note], [data-add-comment]'
            );
            if (!button) return;
            const form = button.closest('form')
                || button.closest('[data-revision-thread-id]')
                || button.closest('.initiative-revision-thread')
                || button.parentElement;
            if (!form || !stateByForm.has(form)) return;
            if (!prepareAudience(form)) {
                event.preventDefault();
                event.stopImmediatePropagation();
            }
        }, true);
    };

    const enhancePrivateBadges = (root = document) => {
        const t = labels();
        root.querySelectorAll(
            '.badge, .status-badge, [data-visibility], [data-comment-visibility], .initiative-revision-comment small, .initiative-revision-comment span'
        ).forEach((element) => {
            if (element.dataset.privateLockEnhanced === '1') return;
            if (element.closest('.initiative-audience-picker')) return;
            const raw = String(
                element.dataset.visibility
                || element.dataset.commentVisibility
                || element.textContent
                || ''
            ).trim().toLowerCase();
            const isPrivate = raw === 'private'
                || raw === 'private note'
                || raw === 'خاص'
                || raw === 'خاصة'
                || raw === 'ملاحظة خاصة';
            if (!isPrivate) return;

            element.dataset.privateLockEnhanced = '1';
            element.classList.add('initiative-private-note-badge');
            element.title = t.privateTooltip;
            element.setAttribute('aria-label', t.privateTooltip);
            element.innerHTML = `${lockSvg()}<span>${t.privateLabel}</span>`;
        });
    };

    const scan = (root = document) => {
        root.querySelectorAll('select').forEach(enhanceSelect);
        enhancePrivateBadges(root);
    };

    const start = () => {
        document.documentElement.classList.add(MARKER);
        patchApi();
        protectSubmissions();
        scan(document);

        const observer = new MutationObserver((mutations) => {
            patchApi();
            for (const mutation of mutations) {
                mutation.addedNodes.forEach((node) => {
                    if (node instanceof Element) {
                        scan(node);
                    }
                });
            }
            enhancePrivateBadges(document);
        });
        observer.observe(document.body, { childList: true, subtree: true });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start, { once: true });
    } else {
        start();
    }
})();
