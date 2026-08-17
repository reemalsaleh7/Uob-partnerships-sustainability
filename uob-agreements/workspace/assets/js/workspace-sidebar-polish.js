(function () {
    'use strict';

    function installConditionalGroupLabels() {
        const groups = [
            {
                label: document.querySelector(
                    '[data-sidebar-review-label]'
                ),
                links: [
                    document.querySelector('[data-workflow-nav]'),
                    document.querySelector('[data-lifecycle-nav]')
                ]
            },
            {
                label: document.querySelector(
                    '[data-sidebar-performance-label]'
                ),
                links: [
                    document.querySelector('[data-performance-nav]'),
                    document.querySelector(
                        '[data-performance-dashboard-nav]'
                    )
                ]
            }
        ];

        function sync() {
            groups.forEach(({ label, links }) => {
                if (!label) {
                    return;
                }

                const hasVisibleLink = links
                    .filter(Boolean)
                    .some(
                        (link) =>
                            !link.classList.contains('d-none')
                    );

                label.classList.toggle(
                    'd-none',
                    !hasVisibleLink
                );
            });
        }

        sync();

        if (typeof MutationObserver !== 'undefined') {
            const observer = new MutationObserver(sync);

            groups.forEach(({ links }) => {
                links.filter(Boolean).forEach((link) => {
                    observer.observe(
                        link,
                        {
                            attributes: true,
                            attributeFilter: ['class']
                        }
                    );
                });
            });
        }
    }

    installConditionalGroupLabels();

    const sidebar = document.querySelector('.workspace-sidebar');

    if (!sidebar) {
        return;
    }

    const sidebarNav = sidebar.querySelector('.workspace-side-nav');

    if (!sidebarNav) {
        return;
    }

    function installAdministrationNavigation() {
        const navigation = sidebar.querySelector('.workspace-side-nav');
        const profileLink = navigation?.querySelector('a[href="profile.php"]');
        const accountLabel = profileLink?.previousElementSibling;

        if (!navigation || !profileLink || !accountLabel) {
            return;
        }

        const isArabic = document.documentElement.lang === 'ar';
        const label = document.createElement('p');
        label.className = 'workspace-nav-label mt-4 d-none';
        label.dataset.adminUsersGroup = '';
        label.textContent = isArabic ? 'الإدارة' : 'Administration';

        const userLink = document.createElement('a');
        const usersActive = window.location.pathname.endsWith('/admin-users.php');
        userLink.className = `workspace-nav-link${usersActive ? ' active' : ' d-none'}`;
        userLink.href = 'admin-users.php';
        userLink.dataset.adminUsersNav = '';

        const userTitle = document.createElement('span');
        userTitle.textContent = isArabic ? 'إدارة المستخدمين' : 'User management';
        const userDescription = document.createElement('small');
        userDescription.textContent = isArabic
            ? 'الهوية والأدوار والجهة التنظيمية'
            : 'Identity, roles, and organization';
        userLink.append(userTitle, userDescription);

        const workflowLink = document.createElement('a');
        const workflowsActive = window.location.pathname.endsWith(
            '/admin-workflows.php'
        );
        workflowLink.className = `workspace-nav-link${workflowsActive ? ' active' : ' d-none'}`;
        workflowLink.href = 'admin-workflows.php';
        workflowLink.dataset.adminWorkflowsNav = '';

        const workflowTitle = document.createElement('span');
        workflowTitle.textContent = isArabic
            ? 'قوالب سير العمل'
            : 'Workflow templates';
        const workflowDescription = document.createElement('small');
        workflowDescription.textContent = isArabic
            ? 'المراحل والترتيب والمسؤوليات'
            : 'Stages, ordering, and responsibility';
        workflowLink.append(workflowTitle, workflowDescription);

        if (usersActive || workflowsActive) {
            label.classList.remove('d-none');
        }
        const administrationAnchor =
            navigation.querySelector('[data-sidebar-review-label]')
            || accountLabel;

        navigation.insertBefore(
            label,
            administrationAnchor
        );

        navigation.insertBefore(
            userLink,
            administrationAnchor
        );

        navigation.insertBefore(
            workflowLink,
            administrationAnchor
        );

        AgreementApi.request('/me')
            .then((user) => {
                const canManageUsers = AgreementApi.hasPermission(
                    user,
                    'MANAGE_USERS'
                );
                const canManageWorkflows = AgreementApi.hasPermission(
                    user,
                    'MANAGE_WORKFLOW_TEMPLATES'
                );

                userLink.classList.toggle('d-none', !canManageUsers);
                workflowLink.classList.toggle('d-none', !canManageWorkflows);
                label.classList.toggle(
                    'd-none',
                    !canManageUsers && !canManageWorkflows
                );
            })
            .catch(() => {
                // The normal page-level session guard handles authentication.
            });
    }

    installAdministrationNavigation();

    /*
     * Keep the sidebar visually stable between full page navigations.
     * Permissions are still enforced by the backend; this cache only
     * remembers UI visibility to avoid links jumping after page load.
     */
    const sidebarVisualStateKey = 'uob-sidebar-visual-state-v4';
    const sidebarScrollStateKey = 'uob-sidebar-scroll-v4';

    const conditionalSidebarSelectors = [
        '[data-agreement-nav]',
        '[data-workflow-nav]',
        '[data-lifecycle-nav]',
        '[data-performance-nav]',
        '[data-performance-dashboard-nav]',
        '[data-initiative-monitoring-nav]',
        '[data-admin-users-nav]',
        '[data-admin-workflows-nav]'
    ];

    function sidebarConditionalElements() {
        return conditionalSidebarSelectors
            .map((selector) => ({
                selector,
                element: document.querySelector(selector)
            }))
            .filter(({ element }) => Boolean(element));
    }

    function syncSidebarGroupLabels() {
        const groups = [
            {
                label: document.querySelector(
                    '[data-admin-users-group]'
                ),
                links: [
                    document.querySelector('[data-admin-users-nav]'),
                    document.querySelector('[data-admin-workflows-nav]')
                ]
            },
            {
                label: document.querySelector(
                    '[data-sidebar-review-label]'
                ),
                links: [
                    document.querySelector('[data-workflow-nav]'),
                    document.querySelector('[data-lifecycle-nav]')
                ]
            },
            {
                label: document.querySelector(
                    '[data-sidebar-performance-label]'
                ),
                links: [
                    document.querySelector('[data-performance-nav]'),
                    document.querySelector(
                        '[data-performance-dashboard-nav]'
                    )
                ]
            }
        ];

        groups.forEach(({ label, links }) => {
            if (!label) {
                return;
            }

            const hasVisibleLink = links
                .filter(Boolean)
                .some(
                    (link) =>
                        !link.classList.contains('d-none')
                );

            label.classList.toggle(
                'd-none',
                !hasVisibleLink
            );
        });
    }

    function restoreSidebarVisibility() {
        let savedState = null;

        try {
            savedState = JSON.parse(
                sessionStorage.getItem(
                    sidebarVisualStateKey
                ) || 'null'
            );
        } catch (error) {
            savedState = null;
        }

        if (!savedState || typeof savedState !== 'object') {
            syncSidebarGroupLabels();
            return;
        }

        sidebarConditionalElements().forEach(
            ({ selector, element }) => {
                /*
                 * Never hide the current page's active link,
                 * even if an old cache says otherwise.
                 */
                const shouldShow =
                    element.classList.contains('active')
                    || savedState[selector] === true;

                element.classList.toggle(
                    'd-none',
                    !shouldShow
                );
            }
        );

        syncSidebarGroupLabels();
    }

    function saveSidebarVisibility() {
        const state = {};

        sidebarConditionalElements().forEach(
            ({ selector, element }) => {
                state[selector] =
                    !element.classList.contains('d-none');
            }
        );

        try {
            sessionStorage.setItem(
                sidebarVisualStateKey,
                JSON.stringify(state)
            );
        } catch (error) {
            // Storage may be unavailable in some privacy modes.
        }
    }

    function restoreSidebarScroll() {
        let scrollTop = 0;

        try {
            scrollTop = Number(
                sessionStorage.getItem(
                    sidebarScrollStateKey
                ) || 0
            );
        } catch (error) {
            scrollTop = 0;
        }

        if (
            Number.isFinite(scrollTop)
            && scrollTop > 0
        ) {
            requestAnimationFrame(() => {
                sidebarNav.scrollTop = scrollTop;
            });
        }
    }

    function saveSidebarScroll() {
        try {
            sessionStorage.setItem(
                sidebarScrollStateKey,
                String(sidebarNav.scrollTop)
            );
        } catch (error) {
            // Storage may be unavailable in some privacy modes.
        }
    }

    /*
     * ACTIVE SIDEBAR VISIBILITY V5
     * Keep the current page and its section heading visible.
     */
    /*
     * ACTIVE SIDEBAR GEOMETRY FIX V6
     *
     * Compare real viewport rectangles instead of offsetTop.
     * This works regardless of the sidebar brand/account area
     * above the scrolling navigation container.
     */
    function ensureActiveSidebarLinkVisible() {
        const activeLink = sidebarNav.querySelector(
            '.workspace-nav-link.active, ' +
            '.workspace-nav-link.is-active, ' +
            '.workspace-nav-link[aria-current="page"]'
        );

        if (
            !activeLink
            || activeLink.classList.contains('d-none')
        ) {
            return;
        }

        activeLink.setAttribute(
            'aria-current',
            'page'
        );

        let sectionLabel = null;
        let sibling = activeLink.previousElementSibling;

        while (sibling) {
            if (
                sibling.classList.contains(
                    'workspace-nav-label'
                )
            ) {
                sectionLabel = sibling;
                break;
            }

            sibling = sibling.previousElementSibling;
        }

        const target =
            sectionLabel || activeLink;

        const padding = 10;

        const navRect =
            sidebarNav.getBoundingClientRect();

        const targetRect =
            target.getBoundingClientRect();

        const activeRect =
            activeLink.getBoundingClientRect();

        /*
         * Section heading is clipped above the navigation viewport.
         */
        if (
            targetRect.top <
            navRect.top + padding
        ) {
            sidebarNav.scrollTop +=
                targetRect.top
                - navRect.top
                - padding;

            return;
        }

        /*
         * Active page is clipped below the navigation viewport.
         */
        if (
            activeRect.bottom >
            navRect.bottom - padding
        ) {
            sidebarNav.scrollTop +=
                activeRect.bottom
                - navRect.bottom
                + padding;
        }
    }

    restoreSidebarVisibility();
    restoreSidebarScroll();
    ensureActiveSidebarLinkVisible();

    let sidebarStateFrame = null;

    function scheduleSidebarStateSave() {
        if (sidebarStateFrame !== null) {
            return;
        }

        sidebarStateFrame = requestAnimationFrame(() => {
            sidebarStateFrame = null;
            saveSidebarVisibility();
            syncSidebarGroupLabels();
            ensureActiveSidebarLinkVisible();
        });
    }

    if (typeof MutationObserver !== 'undefined') {
        const sidebarStateObserver = new MutationObserver(
            (mutations) => {
                const relevantChange = mutations.some(
                    (mutation) =>
                        mutation.type === 'attributes'
                        && mutation.attributeName === 'class'
                );

                if (relevantChange) {
                    scheduleSidebarStateSave();
                }
            }
        );

        sidebarStateObserver.observe(
            sidebar,
            {
                subtree: true,
                attributes: true,
                attributeFilter: ['class']
            }
        );
    }

    let sidebarScrollFrame = null;

    sidebarNav.addEventListener(
        'scroll',
        () => {
            if (sidebarScrollFrame !== null) {
                return;
            }

            sidebarScrollFrame = requestAnimationFrame(() => {
                sidebarScrollFrame = null;
                saveSidebarScroll();
            });
        },
        { passive: true }
    );

    window.addEventListener('pagehide', () => {
        saveSidebarVisibility();
        saveSidebarScroll();
    });

    document.addEventListener('click', (event) => {
        if (!event.target.closest('[data-logout]')) {
            return;
        }

        try {
            sessionStorage.removeItem(
                sidebarVisualStateKey
            );
            sessionStorage.removeItem(
                sidebarScrollStateKey
            );
        } catch (error) {
            // Nothing else is required.
        }
    });

    const tooltip = document.createElement('div');
    tooltip.className = 'workspace-sidebar-tooltip';
    tooltip.setAttribute('role', 'tooltip');
    tooltip.setAttribute('aria-hidden', 'true');
    document.body.append(tooltip);

    const targets = Array.from(
        sidebar.querySelectorAll([
            '.workspace-sidebar-brand strong',
            '.workspace-sidebar-brand small',
            '.workspace-sidebar-context strong',
            '.workspace-sidebar-context small',
            '.workspace-nav-link span',
            '.workspace-nav-link small'
        ].join(','))
    );

    let activeTarget = null;

    function normalizedText(element) {
        return String(element?.textContent || '')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function isTruncated(element) {
        return (
            element.scrollWidth > element.clientWidth + 1
            || element.scrollHeight > element.clientHeight + 1
        );
    }

    function shouldShow(element) {
        const text = normalizedText(element);

        if (!text) {
            return false;
        }

        return (
            isTruncated(element)
            || element.matches(
                '.workspace-sidebar-context strong,'
                + '.workspace-sidebar-context small'
            )
        );
    }

    function positionTooltip() {
        if (!activeTarget) {
            return;
        }

        const targetRect = activeTarget.getBoundingClientRect();
        const tooltipRect = tooltip.getBoundingClientRect();
        const gap = 12;
        const viewportPadding = 10;
        const isRtl = document.documentElement.dir === 'rtl';
        const availableRight =
            window.innerWidth - targetRect.right;
        const availableLeft = targetRect.left;
        const placeRight = isRtl
            ? !(
                availableLeft >= tooltipRect.width + gap
                || availableRight < tooltipRect.width + gap
            )
            : (
                availableRight >= tooltipRect.width + gap
                || targetRect.left < tooltipRect.width + gap
            );

        let left = placeRight
            ? targetRect.right + gap
            : targetRect.left - tooltipRect.width - gap;

        left = Math.min(
            Math.max(viewportPadding, left),
            window.innerWidth
                - tooltipRect.width
                - viewportPadding
        );

        let top =
            targetRect.top
            + (targetRect.height - tooltipRect.height) / 2;

        top = Math.min(
            Math.max(viewportPadding, top),
            window.innerHeight
                - tooltipRect.height
                - viewportPadding
        );

        tooltip.style.left = `${Math.round(left)}px`;
        tooltip.style.top = `${Math.round(top)}px`;
        tooltip.classList.toggle('is-right', placeRight);
        tooltip.classList.toggle('is-left', !placeRight);
    }

    function showTooltip(element) {
        if (!shouldShow(element)) {
            hideTooltip();
            return;
        }

        activeTarget = element;
        tooltip.textContent = normalizedText(element);
        tooltip.setAttribute('aria-hidden', 'false');
        tooltip.classList.add('is-visible');

        window.requestAnimationFrame(positionTooltip);
    }

    function hideTooltip() {
        activeTarget = null;
        tooltip.classList.remove(
            'is-visible',
            'is-right',
            'is-left'
        );
        tooltip.setAttribute('aria-hidden', 'true');
    }

    targets.forEach((target) => {
        target.addEventListener(
            'mouseenter',
            () => showTooltip(target)
        );
        target.addEventListener(
            'mouseleave',
            hideTooltip
        );
        target.addEventListener(
            'focus',
            () => showTooltip(target)
        );
        target.addEventListener(
            'blur',
            hideTooltip
        );
    });

    sidebar.addEventListener('scroll', () => {
        if (activeTarget) {
            positionTooltip();
        }
    }, true);

    window.addEventListener('resize', () => {
        if (activeTarget) {
            positionTooltip();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            hideTooltip();
        }
    });
}());
