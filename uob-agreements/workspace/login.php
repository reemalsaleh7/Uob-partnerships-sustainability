<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

workspaceHeader('Sign in');
?>

<section class="login-shell" aria-labelledby="login-title">
    <div class="login-card">
        <div class="login-card-header">
            <p class="eyebrow mb-2">Internal workspace</p>
            <h1 id="login-title" class="h3 mb-2">Sign in to your partnerships workspace</h1>
            <p class="text-secondary mb-0">
                Your dashboard adapts to your University role, assignments, and reporting responsibilities.
            </p>
        </div>

        <form id="login-form" class="login-form" novalidate>
            <div
                id="login-alert"
                class="alert alert-danger d-none"
                role="alert"
                aria-live="polite"
            ></div>

            <div class="mb-3">
                <label for="email" class="form-label">Email address</label>
                <input
                    id="email"
                    name="email"
                    type="email"
                    class="form-control form-control-lg"
                    autocomplete="username"
                    required
                    autofocus
                >
                <div class="invalid-feedback">Enter a valid email address.</div>
            </div>

            <div class="mb-4">
                <label for="password" class="form-label">Password</label>
                <input
                    id="password"
                    name="password"
                    type="password"
                    class="form-control form-control-lg"
                    autocomplete="current-password"
                    required
                >
                <div class="invalid-feedback">Enter your password.</div>
            </div>

            <button id="login-button" class="btn btn-primary btn-lg w-100" type="submit">
                <span data-button-label>Sign in</span>
                <span
                    class="spinner-border spinner-border-sm ms-2 d-none"
                    data-button-spinner
                    aria-hidden="true"
                ></span>
            </button>
        </form>
    </div>
</section>

<?php workspaceFooter(['assets/js/login.js']); ?>
