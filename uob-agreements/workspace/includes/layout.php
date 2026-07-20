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
    <link href="assets/css/workspace.css" rel="stylesheet">
</head>
<body>
    <a class="skip-link" href="#main-content">Skip to content</a>

    <nav class="navbar navbar-expand-lg workspace-navbar">
        <div class="container-xl">
            <a class="navbar-brand d-flex align-items-center gap-3" href="agreements.php">
                <img
                    src="../assets/image/THEM/uob_logo.png"
                    alt="University of Bahrain"
                    class="workspace-logo"
                >
                <span>
                    <span class="d-block workspace-brand-title">Agreement Workspace</span>
                    <span class="d-block workspace-brand-subtitle">University of Bahrain</span>
                </span>
            </a>

            <button
                class="navbar-toggler"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#workspaceNavigation"
                aria-controls="workspaceNavigation"
                aria-expanded="false"
                aria-label="Toggle navigation"
            >
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="workspaceNavigation">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link{$agreementsActive}" href="agreements.php">
                            Agreements
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link disabled" href="#" aria-disabled="true">
                            Workflow inbox
                        </a>
                    </li>
                </ul>

                <div class="workspace-user d-none" data-session-panel>
                    <span class="workspace-user-name" data-user-name></span>
                    <button class="btn btn-sm btn-outline-light" type="button" data-logout>
                        Sign out
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <main id="main-content" class="container-xl py-4 py-lg-5">
HTML;
}

function workspaceFooter(array $scripts = []): void
{
    echo <<<HTML
    </main>

    <footer class="workspace-footer">
        <div class="container-xl py-4">
            <span>University of Bahrain Partnerships and Sustainability System</span>
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

