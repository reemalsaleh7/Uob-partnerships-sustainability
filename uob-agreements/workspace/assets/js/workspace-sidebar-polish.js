(function () {
    'use strict';

    const performanceLabel = document.querySelector(
        '[data-sidebar-performance-label]'
    );

    if (performanceLabel) {
        const performanceLinks = [
            document.querySelector('[data-performance-nav]'),
            document.querySelector(
                '[data-performance-dashboard-nav]'
            )
        ].filter(Boolean);

        const hasVisibleLink = performanceLinks.some(
            (link) => !link.classList.contains('d-none')
        );

        performanceLabel.classList.toggle(
            'd-none',
            !hasVisibleLink
        );
    }

    const sidebar = document.querySelector('.workspace-sidebar');

    if (!sidebar) {
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
        userLink.className = `workspace-nav-link d-none${usersActive ? ' active' : ''}`;
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
        workflowLink.className = `workspace-nav-link d-none${workflowsActive ? ' active' : ''}`;
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

        navigation.insertBefore(label, accountLabel);
        navigation.insertBefore(userLink, accountLabel);
        navigation.insertBefore(workflowLink, accountLabel);

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
