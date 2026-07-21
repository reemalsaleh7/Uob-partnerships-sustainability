<?php

declare(strict_types=1);

function workspaceHeader(
    string $title,
    string $activePage = ''
): void {
    $safeTitle = htmlspecialchars(
        $title,
        ENT_QUOTES,
        'UTF-8'
    );

    $agreementsActive =
        $activePage === 'agreements'
            ? ' active'
            : '';

    $workflowActive =
        $activePage === 'workflow'
            ? ' active'
            : '';

    $lifecycleActive =
        $activePage === 'lifecycle'
            ? ' active'
            : '';

    $performanceActive =
        $activePage === 'performance'
            ? ' active'
            : '';

    $dashboardActive =
        $activePage === 'performance-dashboard'
            ? ' active'
            : '';

    $isLoginPage = $activePage === '';
    $navigationClass = $isLoginPage
        ? ' workspace-navigation-guest'
        : '';

    echo <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{$safeTitle} | UOB Agreement Workspace</title>
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap"
        rel="stylesheet"
    >
    <link href="assets/css/workspace.css?v=20260721" rel="stylesheet">
</head>
<body class="workspace-body">
    <a class="skip-link" href="#main-content">Skip to content</a>

    <div class="workspace-utility-bar">
        <div class="container-xl workspace-utility-content">
            <span class="workspace-context-label">
                Partnerships &amp; Sustainable Impact
            </span>
            <div class="workspace-portal-links" aria-label="Portal links">
                <a href="../index.php">Public portal</a>
                <a href="../initiatives.php">Initiatives</a>
                <a href="../agreements.php">Public Agreements</a>
            </div>
        </div>
    </div>

    <nav class="navbar navbar-expand-lg workspace-navbar">
        <div class="container-xl">
            <a class="navbar-brand d-flex align-items-center gap-3" href="agreements.php">
                <img
                    src="../assets/image/THEM/uob_logo.png"
                    alt="University of Bahrain"
                    class="workspace-logo"
                >
                <span>
                    <span class="d-block workspace-brand-title">University of Bahrain</span>
                    <span class="d-block workspace-brand-subtitle">Agreement Workspace</span>
                </span>
            </a>

            <button
                class="navbar-toggler{$navigationClass}"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#workspaceNavigation"
                aria-controls="workspaceNavigation"
                aria-expanded="false"
                aria-label="Toggle navigation"
            >
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse{$navigationClass}" id="workspaceNavigation">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link{$agreementsActive}" href="agreements.php">
                            Agreements
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link{$lifecycleActive}" href="lifecycle-requests.php">
                            Lifecycle requests
                        </a>
                    </li>
                    <li class="nav-item d-none" data-workflow-nav>
                        <a class="nav-link{$workflowActive}" href="workflow-inbox.php">
                            Workflow inbox
                        </a>
                    </li>
                    <li class="nav-item d-none" data-performance-nav>
                        <a class="nav-link{$performanceActive}" href="performance-reports.php">
                            Performance reports
                        </a>
                    </li>
                    <li class="nav-item d-none" data-performance-dashboard-nav>
                        <a class="nav-link{$dashboardActive}" href="performance-dashboard.php">
                            Performance dashboard
                        </a>
                    </li>
                </ul>

                <div class="workspace-user d-none" data-session-panel>
                    <span class="workspace-user-avatar" aria-hidden="true">U</span>
                    <span class="workspace-user-name" data-user-name></span>
                    <button class="btn btn-sm btn-outline-primary" type="button" data-logout>
                        Sign out
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <main id="main-content" class="container-xl workspace-main py-4 py-lg-5">
HTML;
}

function workspaceFooter(array $scripts = []): void
{
    $year = date('Y');

    echo <<<HTML
    </main>

    <footer class="workspace-footer">
        <div class="container-xl workspace-footer-content py-4">
            <div class="workspace-footer-brand">
                <img
                    src="../assets/image/THEM/uob_logo.png"
                    alt=""
                    aria-hidden="true"
                >
                <span>
                    <strong>University of Bahrain</strong>
                    <small>Partnerships &amp; Sustainable Impact</small>
                </span>
            </div>
            <nav class="workspace-footer-links" aria-label="Footer links">
                <a href="../index.php">Public portal</a>
                <a href="../agreements.php">Published Agreements</a>
                <a href="../initiatives.php">Initiatives</a>
            </nav>
            <span class="workspace-footer-copy">&copy; {$year} University of Bahrain</span>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/api-client.js"></script>
HTML;

    foreach ($scripts as $script) {
        $safeScript = htmlspecialchars(
            (string) $script,
            ENT_QUOTES,
            'UTF-8'
        );

        echo "    <script src=\"{$safeScript}\"></script>\n";
    }

    echo "</body>\n</html>\n";
}
