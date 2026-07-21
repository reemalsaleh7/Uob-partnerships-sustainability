<?php

declare(strict_types=1);

function workspaceHeader(
    string $title,
    string $activePage = ''
): void {
    $GLOBALS['workspace_is_login_page'] = $activePage === '';
    $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $active = static fn (string $page): string =>
        $activePage === $page ? ' active' : '';
    $isLoginPage = $activePage === '';
    $bodyClass = $isLoginPage
        ? 'workspace-body workspace-login-body'
        : 'workspace-body';

    echo <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{$safeTitle} | UOB Partnerships Workspace</title>
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
    <link href="assets/css/workspace.css?v=20260721-functional-redesign" rel="stylesheet">
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
        <a class="login-public-link" href="../index.php">Public portal</a>
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
    $initiativesActive = $active('initiatives');
    $profileActive = $active('profile');

    echo <<<HTML
    <div class="workspace-app">
        <aside class="workspace-sidebar" id="workspaceSidebar" aria-label="Workspace navigation">
            <a class="workspace-brand workspace-sidebar-brand" href="index.php">
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

            <nav class="workspace-side-nav">
                <p class="workspace-nav-label">Workspace</p>
                <a class="workspace-nav-link{$dashboardActive}" href="index.php">
                    <span>Overview</span><small>Your work today</small>
                </a>
                <a class="workspace-nav-link{$agreementsActive}" href="agreements.php" data-agreement-nav>
                    <span>Agreements</span><small>Portfolio and records</small>
                </a>
                <a class="workspace-nav-link{$workflowActive} d-none" href="workflow-inbox.php" data-workflow-nav>
                    <span>Review inbox</span><small>Assigned decisions</small>
                    <b class="workspace-nav-count d-none" data-workflow-nav-count></b>
                </a>
                <a class="workspace-nav-link{$lifecycleActive} d-none" href="lifecycle-requests.php" data-lifecycle-nav>
                    <span>Lifecycle requests</span><small>Renew, amend, terminate</small>
                </a>

                <p class="workspace-nav-label mt-4">Performance</p>
                <a class="workspace-nav-link{$performanceActive} d-none" href="performance-reports.php" data-performance-nav>
                    <span>Annual reports</span><small>Evidence and outcomes</small>
                </a>
                <a class="workspace-nav-link{$performanceDashboardActive} d-none" href="performance-dashboard.php" data-performance-dashboard-nav>
                    <span>Performance dashboard</span><small>Progress and compliance</small>
                </a>

                <p class="workspace-nav-label mt-4">Initiatives</p>
                <a class="workspace-nav-link{$initiativesActive}" href="initiative-hub.php">
                    <span>Initiative hub</span><small>Start and follow initiatives</small>
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

    echo <<<HTML
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/api-client.js?v=20260721-functional-redesign"></script>
HTML;

    foreach ($scripts as $script) {
        $safeScript = htmlspecialchars((string) $script, ENT_QUOTES, 'UTF-8');
        echo "    <script src=\"{$safeScript}\"></script>\n";
    }

    echo "</body>\n</html>\n";
}
