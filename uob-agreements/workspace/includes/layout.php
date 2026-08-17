<?php

declare(strict_types=1);

require_once __DIR__ . '/i18n.php';

function workspaceVersionedAsset(string $asset): string
{
    $parts = explode('?', $asset, 2);
    $relativePath = ltrim($parts[0], '/');
    $absolutePath = dirname(__DIR__) . '/' . $relativePath;
    $version = is_file($absolutePath) ? filemtime($absolutePath) : false;
    $separator = isset($parts[1]) ? '&' : '?';

    return $asset . $separator . 'v=' . ($version === false ? 'workspace' : $version);
}

function workspaceHeader(
    string $title,
    string $activePage = '',
    array $styles = []
): void {
    $language = workspaceResolveLanguage();
    $direction = workspaceLanguageDirection($language);
    workspaceStartTranslationBuffer();

    $GLOBALS['workspace_is_login_page'] = $activePage === '';
    $translatedTitle = workspaceT($title);
    $safeTitle = htmlspecialchars($translatedTitle, ENT_QUOTES, 'UTF-8');
    $safeWorkspaceTitle = htmlspecialchars(
        workspaceT('UOB Partnerships Workspace'),
        ENT_QUOTES,
        'UTF-8'
    );
    $active = static fn (string $page): string =>
        $activePage === $page ? ' active' : '';
    $isLoginPage = $activePage === '';
    $bodyClass = $isLoginPage
        ? 'workspace-body workspace-login-body'
        : 'workspace-body';
    $extraStyles = '';
    foreach ($styles as $style) {
        $safeStyle = htmlspecialchars((string) $style, ENT_QUOTES, 'UTF-8');
        $extraStyles .= "    <link href=\"{$safeStyle}\" rel=\"stylesheet\">\n";
    }
    $workspaceStyle = htmlspecialchars(
        workspaceVersionedAsset('assets/css/workspace.css'),
        ENT_QUOTES,
        'UTF-8'
    );
    if ($language === 'ar') {
        $bodyClass .= ' workspace-rtl';
    }
    $switchLanguage = $language === 'ar' ? 'en' : 'ar';
    $languageUrl = htmlspecialchars(
        workspaceLanguageUrl($switchLanguage),
        ENT_QUOTES,
        'UTF-8'
    );
    $languageLabel = $language === 'ar' ? 'English' : 'Arabic';
    $languageMark = $language === 'ar' ? 'EN' : 'ع';
    $languageAria = $language === 'ar'
        ? 'Switch to English'
        : 'التبديل إلى العربية';

    echo <<<HTML
<!doctype html>
<html lang="{$language}" dir="{$direction}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{$safeTitle} | {$safeWorkspaceTitle}</title>
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&display=swap"
        rel="stylesheet"
    >
{$extraStyles}
    <link href="{$workspaceStyle}" rel="stylesheet">
    <link href="assets/css/workspace-sidebar-polish.css?v=20260802-phase14g" rel="stylesheet">
    <link href="assets/css/workspace-rtl.css?v=20260802-phase17b" rel="stylesheet">
</head>
<body class="{$bodyClass}">
    <a class="skip-link" href="#main-content">Skip to content</a>
HTML;

    if ($isLoginPage) {
        echo <<<HTML
    <header class="login-brandbar">
        <a class="workspace-brand" href="../index.php">
            <img src="../assets/image/THEM/uob_logo.png" alt="University of Bahrain">
            <span>
                <strong>University of Bahrain</strong>
                <small>Partnerships &amp; Sustainable Impact</small>
            </span>
        </a>
        <div class="login-brandbar-actions">
            <a
                class="workspace-language-toggle"
                href="{$languageUrl}"
                aria-label="{$languageAria}"
            >
                <span class="workspace-language-toggle-mark">{$languageMark}</span>
                <span class="workspace-language-toggle-label">{$languageLabel}</span>
            </a>
            <a class="login-public-link" href="../index.php">Public portal</a>
        </div>
    </header>
    <main id="main-content" class="workspace-login-main">
HTML;
        return;
    }

    $dashboardActive = $active('dashboard');
    $agreementsActive = $active('agreements');
    $workflowActive = $active('workflow');
    $lifecycleActive = $active('lifecycle');
    $performanceActive = $active('performance');
    $performanceDashboardActive = $active('performance-dashboard');
    $profileActive = $active('profile');

    $currentScript = basename(
        (string) ($_SERVER['SCRIPT_NAME'] ?? '')
    );
    $initiativeView = (string) ($_GET['view'] ?? '');

    $initiativeHubActive = $currentScript === 'initiative-hub.php'
        ? ' active'
        : '';

    $initiativeRequestsActive = $currentScript === 'initiative-workflow.php'
        && !in_array($initiativeView, ['notifications', 'monitoring'], true)
        ? ' active'
        : '';

    $initiativeNotificationsActive =
        $currentScript === 'initiative-workflow.php'
        && $initiativeView === 'notifications'
            ? ' active'
            : '';

    $initiativeMonitoringActive =
        $currentScript === 'initiative-workflow.php'
        && $initiativeView === 'monitoring'
            ? ' active'
            : '';

    $finalInitiativesActive = in_array(
        $currentScript,
        [
            'initiative-portfolio.php',
            'initiative-view.php',
            'add-initiative-approved.php',
            'register-existing-initiative.php',
            'add-legacy-initiative.php',
        ],
        true
    )
        ? ' active'
        : '';

    echo <<<HTML
    <div class="workspace-app">
        <aside class="workspace-sidebar" id="workspaceSidebar" aria-label="Workspace navigation">
            <a class="workspace-brand workspace-sidebar-brand" href="../index.php" aria-label="Open UOB Partnerships public portal">
                <img src="../assets/image/THEM/uob_logo.png" alt="University of Bahrain">
                <span>
                    <strong>UOB Partnerships</strong>
                    <small>Operations workspace</small>
                </span>
            </a>

            <div class="workspace-sidebar-context">
                <span class="workspace-user-avatar" data-user-initials aria-hidden="true">U</span>
                <div>
                    <strong data-user-name>Loading account…</strong>
                    <small data-user-context>Secure workspace</small>
                </div>
            </div>
            <script>
            /* SESSION IDENTITY INSTANT HYDRATION */
            (() => {
                try {
                    const cached = JSON.parse(
                        sessionStorage.getItem(
                            'uob-workspace-identity-v1'
                        ) || 'null'
                    );

                    if (!cached || typeof cached !== 'object') {
                        return;
                    }

                    const sidebar =
                        document.querySelector(
                            '.workspace-sidebar-context'
                        );

                    if (!sidebar) {
                        return;
                    }

                    const name =
                        sidebar.querySelector(
                            '[data-user-name]'
                        );

                    const initials =
                        sidebar.querySelector(
                            '[data-user-initials]'
                        );

                    const context =
                        sidebar.querySelector(
                            '[data-user-context]'
                        );

                    if (name && cached.name) {
                        name.textContent = cached.name;
                    }

                    if (initials && cached.initials) {
                        initials.textContent =
                            cached.initials;
                    }

                    if (context && cached.context) {
                        context.textContent =
                            cached.context;
                    }
                } catch (error) {
                    // /me will populate the account normally.
                }
            })();
            </script>

            <nav class="workspace-side-nav">
                <p class="workspace-nav-label">Workspace</p>
                <a class="workspace-nav-link{$dashboardActive}" href="index.php">
                    <span>Overview</span><small>Your work today</small>
                </a>
                <a class="workspace-nav-link{$agreementsActive}" href="agreements.php" data-agreement-nav>
                    <span>Agreements</span><small>Portfolio and records</small>
                </a>
                <p class="workspace-nav-label mt-4 d-none" data-sidebar-review-label>Reviews &amp; workflows</p>
                <a class="workspace-nav-link{$workflowActive} d-none" href="workflow-inbox.php" data-workflow-nav>
                    <span>Review inbox</span><small>Assigned decisions</small>
                    <b class="workspace-nav-count d-none" data-workflow-nav-count></b>
                </a>
                <a class="workspace-nav-link{$lifecycleActive} d-none" href="lifecycle-requests.php" data-lifecycle-nav>
                    <span>Lifecycle requests</span><small>Renew, amend, terminate</small>
                </a>

                <p class="workspace-nav-label mt-4 d-none" data-sidebar-performance-label>Performance</p>
                <a class="workspace-nav-link{$performanceActive} d-none" href="performance-reports.php" data-performance-nav>
                    <span>Annual reports</span><small>Evidence and outcomes</small>
                </a>
                <a class="workspace-nav-link{$performanceDashboardActive} d-none" href="performance-dashboard.php" data-performance-dashboard-nav>
                    <span>Performance dashboard</span><small>Progress and compliance</small>
                </a>

                <p class="workspace-nav-label mt-4">Initiatives</p>
                <a class="workspace-nav-link{$initiativeHubActive}" href="initiative-hub.php">
                    <span>Initiative hub</span><small>Guidance and starting points</small>
                </a>
                <a class="workspace-nav-link{$initiativeRequestsActive}" href="initiative-workflow.php">
                    <span>Initiative requests</span><small>Create, review, and follow</small>
                </a>
                <a
                    class="workspace-nav-link{$initiativeMonitoringActive} d-none"
                    href="initiative-workflow.php?view=monitoring"
                    data-initiative-monitoring-nav
                >
                    <span>Initiative Monitoring</span>
                    <small>System-wide workflow oversight</small>
                </a>
                <a class="workspace-nav-link{$finalInitiativesActive}" href="initiative-portfolio.php">
                    <span>Final Initiatives</span><small>Approved and existing records</small>
                </a>

                <p class="workspace-nav-label mt-4">Account</p>
                <a class="workspace-nav-link{$profileActive}" href="profile.php">
                    <span>My profile</span><small>Role and access</small>
                </a>
            </nav>

            <div class="workspace-sidebar-footer">
                <a href="../index.php">Public portal</a>
                <button type="button" data-logout>Sign out</button>
            </div>
        </aside>

        <div class="workspace-stage">
            <header class="workspace-topbar">
                <button
                    class="workspace-menu-button"
                    type="button"
                    aria-label="Open navigation"
                    aria-controls="workspaceSidebar"
                    aria-expanded="false"
                    data-sidebar-toggle
                >
                    <span></span><span></span><span></span>
                </button>
                <div class="workspace-topbar-title">
                    <small>Partnerships &amp; Sustainable Impact</small>
                    <strong>{$safeTitle}</strong>
                </div>
                <div class="workspace-topbar-actions" data-session-panel>
                    <a
                        class="workspace-notification-button{$initiativeNotificationsActive}"
                        href="initiative-workflow.php?view=notifications"
                        aria-label="Notifications"
                        title="Notifications"
                        data-workspace-notifications-topbar
                    >
                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"></path>
                            <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                        </svg>
                        <b
                            class="workspace-notification-count d-none"
                            data-initiative-notification-count
                            aria-label="Unread Initiative notifications"
                        ></b>
                    </a>
                    <a
                        class="workspace-language-toggle"
                        href="{$languageUrl}"
                        aria-label="{$languageAria}"
                    >
                        <span class="workspace-language-toggle-mark">{$languageMark}</span>
                        <span class="workspace-language-toggle-label">{$languageLabel}</span>
                    </a>
                    <a class="workspace-profile-link" href="profile.php">
                        <span class="workspace-user-avatar" data-user-initials aria-hidden="true">U</span>
                        <span><strong data-user-name></strong><small>View profile</small></span>
                    </a>
                </div>
            </header>

            <main id="main-content" class="workspace-main">
HTML;
}

function workspaceFooter(array $scripts = []): void
{
    echo "    </main>\n";

    if (!empty($GLOBALS['workspace_is_login_page'])) {
        echo <<<HTML
    <footer class="login-footer">
        University of Bahrain · Partnerships &amp; Sustainable Impact
    </footer>
HTML;
    } else {
        echo <<<HTML
            <footer class="workspace-footer">
                <span>University of Bahrain · Partnerships &amp; Sustainable Impact</span>
                <a href="../index.php">Open public portal</a>
            </footer>
        </div>
    </div>
HTML;
    }

    $i18nConfig = json_encode(
        workspaceClientI18nConfig(),
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_HEX_TAG
        | JSON_HEX_AMP
        | JSON_HEX_APOS
        | JSON_HEX_QUOT
    );

    $versionedCoreScripts = [];
    foreach (
        [
            'apiClientScript' => 'assets/js/api-client.js',
            'exportUtilsScript' => 'assets/js/export-utils.js',
            'uiDialogScript' => 'assets/js/ui-dialog.js',
            'workspaceI18nScript' => 'assets/js/workspace-i18n.js',
            'sidebarInitiativeScript' => 'assets/js/sidebar-initiative.js',
            'sidebarPolishScript' => 'assets/js/workspace-sidebar-polish.js',
        ] as $key => $asset
    ) {
        $versionedCoreScripts[$key] = htmlspecialchars(
            workspaceVersionedAsset($asset),
            ENT_QUOTES,
            'UTF-8'
        );
    }
    extract($versionedCoreScripts, EXTR_SKIP);

    echo <<<HTML
    <div class="modal fade workspace-confirm-modal" id="workspace-confirm-modal" tabindex="-1" aria-labelledby="workspace-confirm-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <p class="eyebrow mb-1">UOB Partnerships</p>
                        <h2 class="modal-title h5 mb-0" id="workspace-confirm-title" data-dialog-title>Confirm action</h2>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" data-dialog-message></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" data-dialog-confirm>Confirm</button>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="{$apiClientScript}"></script>
    <script src="{$exportUtilsScript}"></script>
    <script src="{$uiDialogScript}"></script>
    <script>window.WorkspaceI18nConfig = {$i18nConfig};</script>
    <script src="{$workspaceI18nScript}"></script>
    <script src="{$sidebarInitiativeScript}"></script>
    <script src="{$sidebarPolishScript}"></script>
HTML;

    foreach ($scripts as $script) {
        $versionedScript = workspaceVersionedAsset((string) $script);
        $safeScript = htmlspecialchars($versionedScript, ENT_QUOTES, 'UTF-8');
        echo "    <script src=\"{$safeScript}\"></script>\n";
    }

    $initiativeLinkScript = htmlspecialchars(
        workspaceVersionedAsset('assets/js/agreement-initiative-link.js'),
        ENT_QUOTES,
        'UTF-8'
    );
    echo "    <script src=\"{$initiativeLinkScript}\"></script>\n";
    echo "</body>\n</html>\n";
    workspaceEndTranslationBuffer();
}
