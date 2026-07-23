<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

workspaceHeader('My profile', 'profile');
?>

<section class="page-heading">
    <p class="eyebrow mb-2">Account and access</p>
    <h1 class="display-6 mb-2">My profile</h1>
    <p class="text-secondary mb-0">
        Your University identity, organizational position, and system authority.
    </p>
</section>

<div class="alert alert-danger mt-4 d-none" role="alert" tabindex="-1" data-profile-alert></div>
<div class="loading-state mt-4" data-profile-loading>
    <div class="spinner-border text-primary" aria-hidden="true"></div>
    <span>Loading profile…</span>
</div>

<div class="row g-4 mt-1 d-none" data-profile-content>
    <div class="col-xl-5">
        <section class="workspace-card">
            <div class="profile-identity">
                <div class="profile-avatar" data-profile-initials>U</div>
                <div>
                    <h2 data-profile-name></h2>
                    <p data-profile-email></p>
                    <span class="status-badge status-active mt-2">Active account</span>
                </div>
            </div>
            <dl class="record-list border-top">
                <div><dt>University ID</dt><dd data-profile-field="university_id"></dd></div>
                <div><dt>Phone</dt><dd data-profile-field="phone"></dd></div>
                <div><dt>Last sign-in</dt><dd data-profile-field="last_login"></dd></div>
                <div><dt>Account created</dt><dd data-profile-field="account_created_at"></dd></div>
            </dl>
        </section>

        <section class="workspace-card mt-4">
            <div class="workspace-card-header">
                <div>
                    <h2 class="h5 mb-1">Organizational position</h2>
                    <p class="small text-secondary mb-0">Used to route work and determine scope.</p>
                </div>
            </div>
            <div data-profile-positions></div>
        </section>
    </div>

    <div class="col-xl-7">
        <section class="workspace-card">
            <div class="workspace-card-header">
                <div>
                    <h2 class="h5 mb-1">Assigned roles</h2>
                    <p class="small text-secondary mb-0">Business roles granted to your account.</p>
                </div>
            </div>
            <ul class="profile-tag-list" data-profile-roles></ul>
        </section>

        <section class="workspace-card mt-4">
            <div class="workspace-card-header">
                <div>
                    <h2 class="h5 mb-1">System permissions</h2>
                    <p class="small text-secondary mb-0">The exact actions your account may perform.</p>
                </div>
            </div>
            <ul class="profile-tag-list" data-profile-permissions></ul>
        </section>

        <section class="workspace-card mt-4">
            <div class="workspace-card-header">
                <div>
                    <h2 class="h5 mb-1">Account security</h2>
                    <p class="small text-secondary mb-0">Security details for this workspace session.</p>
                </div>
            </div>
            <dl class="record-list">
                <div><dt>Password last changed</dt><dd data-profile-field="password_changed_at"></dd></div>
                <div><dt>Session protection</dt><dd>30-minute inactivity timeout · 12-hour maximum session</dd></div>
                <div><dt>Sign-in protection</dt><dd>Temporary lock after five failed attempts</dd></div>
            </dl>
        </section>
    </div>
</div>

<?php workspaceFooter(['assets/js/profile.js']); ?>
