<?php

declare(strict_types=1);

if ($view === 'list'): ?>
<section class="page-heading d-flex flex-wrap justify-content-between align-items-start gap-3">
    <div>
        <p class="eyebrow mb-2">Initiatives</p>
        <h1 class="display-6 mb-2">Initiative requests</h1>
        <p class="text-secondary mb-0">
            Create, follow, review, and convert University Initiative requests.
        </p>
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2 initiative-page-actions">
        <a
            class="btn btn-outline-secondary d-none initiative-existing-action"
            href="register-existing-initiative.php"
            data-add-existing-initiative
            title="Register an Initiative approved before this workflow"
        >
            <span class="initiative-action-plus" aria-hidden="true">+</span>
            <span>Register Existing Initiative</span>
        </a>
        <div
            class="dropdown d-none"
            data-add-approved-initiative-menu
        >
            <button
                class="btn btn-success dropdown-toggle"
                type="button"
                data-bs-toggle="dropdown"
                aria-expanded="false"
            >
                Create Final Initiative
            </button>
            <ul
                class="dropdown-menu dropdown-menu-end initiative-approved-menu"
                data-approved-initiative-options
            ></ul>
        </div>
        <a
            class="btn btn-outline-primary initiative-notification-link"
            href="initiative-workflow.php?view=notifications"
        >
            Notifications
            <span
                class="badge text-bg-danger d-none"
                data-notification-count
            ></span>
        </a>
        <a class="btn btn-primary d-none" href="initiative-workflow.php?view=form" data-create-request>
            New Initiative Request
        </a>
    </div>
</section>

<div class="alert alert-danger mt-4 d-none" role="alert" data-module-alert></div>

<section class="workspace-card mt-4">
    <div class="workspace-card-header">
        <div>
            <h2 class="h5 mb-1">My visible requests</h2>
            <p class="small text-secondary mb-0">
                Requests you created, joined, review, or administer.
            </p>
        </div>
        <div class="initiative-filter-row">
            <input class="form-control" type="search" placeholder="Search title or request code" data-search>
            <select class="form-select" data-status-filter>
                <option value="">All statuses</option>
                <option value="DRAFT">Draft</option>
                <option value="UNDER_REVIEW">Under review</option>
                <option value="REVISION_REQUIRED">Revision required</option>
                <option value="APPROVED">Approved</option>
                <option value="REJECTED">Rejected</option>
                <option value="CONVERTED">Converted</option>
            </select>
        </div>
    </div>

    <div class="loading-state" data-loading>
        <div class="spinner-border text-primary" aria-hidden="true"></div>
        <span>Loading Initiative requests…</span>
    </div>

    <div class="empty-state d-none" data-empty>
        <h3>No Initiative requests found</h3>
        <p>Start a new request or change the filters.</p>
    </div>

    <div class="table-responsive d-none" data-table-wrap>
        <table class="table workspace-table align-middle mb-0">
            <thead>
            <tr>
                <th>Request</th>
                <th>Requester</th>
                <th>Current stage</th>
                <th>Status</th>
                <th>Waiting</th>
                <th></th>
            </tr>
            </thead>
            <tbody data-list-body></tbody>
        </table>
    </div>
</section>

<?php elseif ($view === 'notifications'): ?>
<div class="mb-4">
    <a href="initiative-workflow.php" class="back-link">← Back to Initiative requests</a>
</div>

<section class="page-heading d-flex flex-wrap justify-content-between align-items-start gap-3">
    <div>
        <p class="eyebrow mb-2">Initiatives</p>
        <h1 class="display-6 mb-2">Notifications</h1>
        <p class="text-secondary mb-0">
            Approval assignments, progress updates, revision requests, final decisions, and reminders.
        </p>
    </div>
    <button
        class="btn btn-outline-primary"
        type="button"
        data-mark-all-notifications-read
    >
        Mark all as read
    </button>
</section>

<div class="alert alert-danger mt-4 d-none" role="alert" data-module-alert></div>

<section class="workspace-card mt-4">
    <div class="workspace-card-header">
        <div>
            <h2 class="h5 mb-1">Initiative activity</h2>
            <p class="small text-secondary mb-0">
                Unread items are highlighted. Open an item to view its Initiative request.
            </p>
        </div>
        <label class="form-check form-switch mb-0">
            <input
                class="form-check-input"
                type="checkbox"
                role="switch"
                data-unread-notifications-only
            >
            <span class="form-check-label">Unread only</span>
        </label>
    </div>

    <div class="loading-state" data-notifications-loading>
        <div class="spinner-border text-primary" aria-hidden="true"></div>
        <span>Loading Initiative notifications…</span>
    </div>

    <div class="empty-state d-none" data-notifications-empty>
        <h3>No notifications found</h3>
        <p>Your Initiative notifications will appear here.</p>
    </div>

    <div
        class="initiative-notification-list d-none"
        data-notification-list
    ></div>
</section>

<?php elseif ($view === 'form'): ?>
<script>
document.body.classList.add('initiative-form-focus');
document.body.classList.remove('initiative-form-sidebar-open');
document.getElementById('workspaceSidebar')?.classList.remove('is-open');
</script>

<section class="initiative-request-hero">
    <div>
        <div class="initiative-request-hero-meta">
            <a
                href="initiative-workflow.php"
                class="initiative-request-hero-back"
                aria-label="Back to all Initiative requests"
            >
                <span aria-hidden="true">←</span>
                <span>All requests</span>
            </a>
            <span
                class="initiative-request-hero-meta-divider"
                aria-hidden="true"
            ></span>
            <p class="eyebrow mb-0">Pre-execution approval</p>
        </div>
        <h1 class="display-6 mb-2" data-form-heading>
            Create Initiative request
        </h1>
        <p class="mb-0" data-form-description>
            Complete the request in six guided sections, save it as a draft, and submit it through the University approval route.
        </p>
    </div>
    <div class="initiative-request-hero-badge">
        <span>6</span>
        <small>guided sections</small>
    </div>
</section>

<div
    class="alert alert-danger mt-4 d-none"
    role="alert"
    data-module-alert
></div>

<form
    class="initiative-request-form workspace-standard-form mt-4"
    novalidate
    data-request-form
>
    <section class="initiative-form-shell">
        <aside
            class="initiative-form-step-panel"
            aria-label="Initiative request progress"
        >
            <h2 class="initiative-form-step-panel-title">
                Form Steps
            </h2>

            <nav
                class="initiative-form-steps"
                aria-label="Initiative request sections"
                data-form-step-navigation
            >
                <button
                    class="initiative-form-step is-active"
                    type="button"
                    data-form-step-button="0"
                >
                    <span class="initiative-form-step-marker">1</span>
                    <span class="initiative-form-step-copy">
                        <small class="initiative-form-step-kicker">Step 1</small>
                        <strong>Requester Information</strong>
                        <small class="initiative-form-step-description">
                            Account and requester category
                        </small>
                    </span>
                </button>
                <button
                    class="initiative-form-step"
                    type="button"
                    data-form-step-button="1"
                >
                    <span class="initiative-form-step-marker">2</span>
                    <span class="initiative-form-step-copy">
                        <small class="initiative-form-step-kicker">Step 2</small>
                        <strong>Initiative Details</strong>
                        <small class="initiative-form-step-description">
                            Idea, type, and audience
                        </small>
                    </span>
                </button>
                <button
                    class="initiative-form-step"
                    type="button"
                    data-form-step-button="2"
                >
                    <span class="initiative-form-step-marker">3</span>
                    <span class="initiative-form-step-copy">
                        <small class="initiative-form-step-kicker">Step 3</small>
                        <strong>Implementation</strong>
                        <small class="initiative-form-step-description">
                            Dates, scope, and venue
                        </small>
                    </span>
                </button>
                <button
                    class="initiative-form-step"
                    type="button"
                    data-form-step-button="3"
                >
                    <span class="initiative-form-step-marker">4</span>
                    <span class="initiative-form-step-copy">
                        <small class="initiative-form-step-kicker">Step 4</small>
                        <strong>Agreements &amp; Partners</strong>
                        <small class="initiative-form-step-description">
                            Agreement, partner, and team
                        </small>
                    </span>
                </button>
                <button
                    class="initiative-form-step"
                    type="button"
                    data-form-step-button="4"
                >
                    <span class="initiative-form-step-marker">5</span>
                    <span class="initiative-form-step-copy">
                        <small class="initiative-form-step-kicker">Step 5</small>
                        <strong>Requirements</strong>
                        <small class="initiative-form-step-description">
                            Resources, budget, and files
                        </small>
                    </span>
                </button>
                <button
                    class="initiative-form-step"
                    type="button"
                    data-form-step-button="5"
                >
                    <span class="initiative-form-step-marker">6</span>
                    <span class="initiative-form-step-copy">
                        <small class="initiative-form-step-kicker">Step 6</small>
                        <strong>Review &amp; Submit</strong>
                        <small class="initiative-form-step-description">
                            SDGs and declaration
                        </small>
                    </span>
                </button>
            </nav>
        </aside>

        <div class="initiative-form-content">
            <div class="initiative-form-progress">
            <div
                class="initiative-form-progress-track"
                role="progressbar"
                aria-valuemin="0"
                aria-valuemax="100"
                aria-valuenow="0"
                data-form-progress-track
            >
                <div
                    class="initiative-form-progress-fill"
                    data-form-progress-fill
                ></div>
            </div>
            <strong data-form-progress-text>0%</strong>
        </div>

        <div class="initiative-form-section is-active" data-form-step="0">
            <div class="initiative-form-section-heading">
                <div>
                    <p class="eyebrow mb-2">Section 1 of 6</p>
                    <h2>Requester details</h2>
                    <p>
                        Name, email, position, College or Organizational Unit, and Department, Directorate or Unit are resolved from the signed-in University account.
                    </p>
                </div>
                <span class="initiative-auto-chip">Auto verified</span>
            </div>

            <div class="initiative-profile-grid">
                <div class="initiative-profile-item">
                    <small>Requester name</small>
                    <strong data-requester-name>Loading…</strong>
                </div>
                <div class="initiative-profile-item">
                    <small>Email address</small>
                    <strong data-requester-email>Loading…</strong>
                </div>
                <div class="initiative-profile-item">
                    <small>Position</small>
                    <strong data-requester-position>Loading…</strong>
                </div>
                <div class="initiative-profile-item">
                    <small>College or Organizational Unit</small>
                    <strong data-requester-entity>Loading…</strong>
                </div>
                <div class="initiative-profile-item">
                    <small>Department, Directorate or Unit</small>
                    <strong data-requester-department>Loading…</strong>
                </div>
                <div class="initiative-profile-item initiative-profile-mobile-item">
                    <small>Mobile number</small>
                    <input
                        id="requester_mobile"
                        name="requester_mobile"
                        class="initiative-profile-mobile-input"
                        maxlength="40"
                        autocomplete="tel"
                        placeholder="Not provided"
                        aria-label="Mobile number"
                    >
                </div>
            </div>

            <div class="row g-4 mt-1">
                <div class="col-12">
                    <label class="form-label">
                        Requester Category *
                    </label>
                    <div class="form-text mb-2">
                        Select the category that applies to you.
                    </div>
                    <div class="initiative-choice-grid initiative-requester-category-grid">
                        <label class="initiative-choice-card">
                            <input
                                type="radio"
                                name="requester_type"
                                value="FACULTY"
                            >
                            <span>Academic Staff</span>
                        </label>
                        <label class="initiative-choice-card">
                            <input
                                type="radio"
                                name="requester_type"
                                value="STAFF"
                            >
                            <span>Administrative Staff</span>
                        </label>
                        <label class="initiative-choice-card">
                            <input
                                type="radio"
                                name="requester_type"
                                value="STUDENT"
                            >
                            <span>Student</span>
                        </label>
                        <label class="initiative-choice-card">
                            <input
                                type="radio"
                                name="requester_type"
                                value="STUDENT_GROUP"
                            >
                            <span>Student Group</span>
                        </label>
                    </div>
                    <input
                        type="hidden"
                        name="requester_type_other"
                        value=""
                    >
                </div>

            </div>
        </div>

        <div class="initiative-form-section" data-form-step="1">
            <div class="initiative-form-section-heading">
                <div>
                    <p class="eyebrow mb-2">Section 2 of 6</p>
                    <h2>Initiative information</h2>
                    <p>
                        Describe the idea, objectives, expected impact, beneficiaries, and initiative classifications.
                    </p>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-12">
                    <label for="title" class="form-label">
                        Initiative or Activity title *
                    </label>
                    <input
                        id="title"
                        name="title"
                        class="form-control"
                        maxlength="255"
                        required
                        placeholder="Enter the activity name, such as the name of the initiative, event, workshop, research project, or media participation."
                    >
                </div>

                <input
                    type="hidden"
                    id="initiative_type"
                    name="initiative_type"
                    value="ACADEMIC"
                >
                <input
                    type="hidden"
                    name="primary_type_other"
                    value=""
                >

                <div class="col-12">
                    <label class="form-label">
                        Initiative or Activity types *
                    </label>
                    <div class="form-text mb-2">
                        Select all types that apply to the proposed activity.
                    </div>
                    <div class="initiative-choice-grid initiative-choice-grid-compact">
                        <label class="initiative-choice-card">
                            <input type="checkbox" name="secondary_types" value="WORKSHOP_TRAINING">
                            <span>Workshop / Training</span>
                        </label>
                        <label class="initiative-choice-card">
                            <input type="checkbox" name="secondary_types" value="LECTURE_SEMINAR">
                            <span>Lecture / Seminar</span>
                        </label>
                        <label class="initiative-choice-card">
                            <input type="checkbox" name="secondary_types" value="STUDENT_INITIATIVE">
                            <span>Student Initiative</span>
                        </label>
                        <label class="initiative-choice-card">
                            <input type="checkbox" name="secondary_types" value="COMMUNITY_ENGAGEMENT">
                            <span>Community Engagement</span>
                        </label>
                        <label class="initiative-choice-card">
                            <input type="checkbox" name="secondary_types" value="VOLUNTEERING">
                            <span>Volunteering</span>
                        </label>
                        <label class="initiative-choice-card">
                            <input type="checkbox" name="secondary_types" value="AWARENESS_CAMPAIGN">
                            <span>Awareness Campaign</span>
                        </label>
                        <label class="initiative-choice-card">
                            <input type="checkbox" name="secondary_types" value="RESEARCH">
                            <span>Research</span>
                        </label>
                        <label class="initiative-choice-card">
                            <input type="checkbox" name="secondary_types" value="CONSULTATION">
                            <span>Consultation</span>
                        </label>
                        <label class="initiative-choice-card">
                            <input type="checkbox" name="secondary_types" value="PARTNERSHIP">
                            <span>Partnership</span>
                        </label>
                        <label class="initiative-choice-card">
                            <input type="checkbox" name="secondary_types" value="SUSTAINABILITY">
                            <span>Sustainability</span>
                        </label>
                        <label class="initiative-choice-card">
                            <input type="checkbox" name="secondary_types" value="ACADEMIC">
                            <span>Academic</span>
                        </label>
                        <label class="initiative-choice-card">
                            <input type="checkbox" name="secondary_types" value="INNOVATION">
                            <span>Innovation</span>
                        </label>
                    </div>
                </div>

                <div class="col-12">
                    <label for="description" class="form-label">
                        Brief Initiative Description *
                    </label>
                    <textarea
                        id="description"
                        name="description"
                        class="form-control"
                        rows="5"
                        maxlength="1600"
                    ></textarea>
                    <div class="form-text">
                        Briefly describe what will be delivered and the proposed approach.
                    </div>
                </div>

                <div class="col-12">
                    <label for="objective" class="form-label">
                        Main Initiative Objective *
                    </label>
                    <textarea
                        id="objective"
                        name="objective"
                        class="form-control"
                        rows="4"
                        maxlength="1200"
                    ></textarea>
                    <div class="form-text">
                        State the main objective the Initiative is expected to achieve.
                    </div>
                </div>

                <div class="col-md-6">
                    <label
                        for="expected_impact"
                        class="form-label"
                    >
                        Expected Results or Impact
                    </label>
                    <textarea
                        id="expected_impact"
                        name="expected_impact"
                        class="form-control"
                        rows="4"
                    ></textarea>
                    <div class="form-text">
                        Describe the most important result or impact expected from the Initiative.
                    </div>
                </div>

                <div class="col-md-6">
                    <label
                        for="beneficiaries"
                        class="form-label"
                    >
                        Beneficiaries Summary
                    </label>
                    <textarea
                        id="beneficiaries"
                        name="beneficiaries"
                        class="form-control"
                        rows="4"
                    ></textarea>
                    <div class="form-text">
                        Add any useful details about the expected beneficiaries.
                    </div>
                </div>

                <div class="col-12">
                    <label class="form-label">
                        Expected Beneficiary or Target Groups *
                    </label>
                    <div class="form-text mb-2">
                        Select all groups expected to benefit from or participate in the Initiative. You may select more than one.
                    </div>
                    <div class="initiative-choice-grid">
                        <label class="initiative-choice-card">
                            <input type="checkbox" name="target_groups" value="UNIVERSITY_STUDENTS">
                            <span>University Students</span>
                        </label>
                        <label class="initiative-choice-card">
                            <input type="checkbox" name="target_groups" value="SCHOOL_STUDENTS">
                            <span>School Students</span>
                        </label>
                        <label class="initiative-choice-card">
                            <input type="checkbox" name="target_groups" value="FACULTY_STAFF">
                            <span>Faculty and Staff</span>
                        </label>
                        <label class="initiative-choice-card">
                            <input type="checkbox" name="target_groups" value="PROFESSIONALS">
                            <span>Professionals</span>
                        </label>
                        <label class="initiative-choice-card">
                            <input type="checkbox" name="target_groups" value="ALUMNI">
                            <span>Alumni</span>
                        </label>
                        <label class="initiative-choice-card">
                            <input type="checkbox" name="target_groups" value="LOCAL_COMMUNITY">
                            <span>Local Community</span>
                        </label>
                        <label class="initiative-choice-card">
                            <input type="checkbox" name="target_groups" value="VULNERABLE_GROUPS">
                            <span>Vulnerable Groups</span>
                        </label>
                        <label class="initiative-choice-card">
                            <input type="checkbox" name="target_groups" value="OPEN_PUBLIC">
                            <span>Open to the Public</span>
                        </label>
                        <label class="initiative-choice-card">
                            <input type="checkbox" name="target_groups" value="OTHER">
                            <span>Other</span>
                        </label>
                    </div>
                    <input
                        class="form-control mt-3 d-none"
                        name="target_group_other"
                        maxlength="200"
                        placeholder="Specify the other target audience"
                        data-conditional-field="target-group-other"
                    >
                </div>

                <div class="col-md-6">
                    <label
                        for="expected_participants"
                        class="form-label"
                    >
                        Expected Number of Beneficiaries or Attendees *
                    </label>
                    <input
                        id="expected_participants"
                        name="expected_participants"
                        type="number"
                        min="0"
                        step="1"
                        class="form-control"
                    >
                    <div class="form-text">
                        Enter the approximate expected number.
                    </div>
                </div>
            </div>
        </div>

        <div class="initiative-form-section" data-form-step="2">
            <div class="initiative-form-section-heading">
                <div>
                    <p class="eyebrow mb-2">Section 3 of 6</p>
                    <h2>Implementation</h2>
                    <p>
                        Define the proposed schedule, implementation scope, and venue.
                    </p>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-md-6">
                    <label
                        for="proposed_start_date"
                        class="form-label"
                    >
                        Expected start date *
                    </label>
                    <input
                        id="proposed_start_date"
                        name="proposed_start_date"
                        type="date"
                        class="form-control"
                    >
                </div>
                <div class="col-md-6">
                    <label
                        for="proposed_end_date"
                        class="form-label"
                    >
                        Expected end date *
                    </label>
                    <input
                        id="proposed_end_date"
                        name="proposed_end_date"
                        type="date"
                        class="form-control"
                    >
                </div>

                <fieldset class="col-12">
                    <legend class="form-label">
                        Location and Delivery Mode *
                    </legend>
                    <div class="form-text mb-2">
                        Select how and where the activity will be delivered.
                    </div>
                    <div class="initiative-choice-grid initiative-location-choice-grid">
                        <label class="initiative-choice-card">
                            <input type="radio" name="implementation_scope" value="WITHIN_UOB">
                            <span>Within the University of Bahrain</span>
                        </label>
                        <label class="initiative-choice-card">
                            <input type="radio" name="implementation_scope" value="OUTSIDE_UOB">
                            <span>Outside the University Of Bahrain</span>
                        </label>
                        <label class="initiative-choice-card">
                            <input type="radio" name="implementation_scope" value="VIRTUAL">
                            <span>Online / Virtual</span>
                        </label>
                        <label class="initiative-choice-card">
                            <input type="radio" name="implementation_scope" value="HYBRID">
                            <span>Hybrid: In-Person and Online</span>
                        </label>
                    </div>
                    <input
                        type="hidden"
                        name="implementation_scope_other"
                        value=""
                    >
                </fieldset>

                <div
                    class="col-md-6 d-none"
                    data-location-detail="venue"
                >
                    <label
                        class="form-label"
                        for="proposed_venue"
                    >
                        Proposed Venue or Location *
                    </label>

                    <div
                        class="initiative-google-place-control"
                        data-google-place-control
                    >
                        <div
                            class="initiative-google-place-host"
                            data-google-place-host
                            aria-label="Search Google Maps places"
                        ></div>

                        <div
                            class="initiative-selected-place-wrap"
                            data-selected-place-wrap
                        >
                            <div
                                class="initiative-selected-place-name d-none"
                                data-selected-place-name-wrap
                            >
                                <small>Selected place</small>
                                <strong data-selected-place-name-display></strong>
                            </div>

                            <input
                                id="proposed_venue"
                                name="proposed_venue"
                                class="form-control initiative-selected-place-input"
                                maxlength="300"
                                placeholder="Search Google Maps and select a place."
                                autocomplete="off"
                                readonly
                                data-selected-place-input
                            >

                            <button
                                type="button"
                                class="btn btn-outline-danger initiative-clear-place-button d-none"
                                aria-label="Clear selected location"
                                title="Clear selected location"
                                data-clear-selected-place
                            >
                                Clear selected location
                            </button>
                        </div>

                        <div class="initiative-map-picker-actions">
                            <button
                                type="button"
                                class="btn btn-outline-primary initiative-open-map-button"
                                data-open-map-picker
                                disabled
                            >
                                Choose location on map
                            </button>
                            <small>
                                Open the map to select a labelled place or drop the pin on an exact point.
                            </small>
                        </div>

                        <input
                            type="hidden"
                            name="proposed_venue_place_id"
                            data-place-id
                        >
                        <input
                            type="hidden"
                            name="proposed_venue_name"
                            data-place-name
                        >
                        <input
                            type="hidden"
                            name="proposed_venue_latitude"
                            data-place-latitude
                        >
                        <input
                            type="hidden"
                            name="proposed_venue_longitude"
                            data-place-longitude
                        >
                        <input
                            type="hidden"
                            name="proposed_venue_country_code"
                            data-place-country-code
                        >

                        <div
                            class="form-text"
                            data-google-place-status
                        >
                            Search Google Maps and select one of the suggested places.
                        </div>
                    </div>
                </div>

                <div
                    class="col-md-6 d-none"
                    data-location-detail="country"
                >
                    <label
                        for="implementation_country"
                        class="form-label"
                    >
                        Country *
                    </label>
                    <input
                        id="implementation_country"
                        name="implementation_country"
                        class="form-control"
                        type="search"
                        maxlength="120"
                        placeholder="Search all countries."
                        autocomplete="off"
                        list="initiative-country-options"
                        data-country-search
                    >
                    <datalist id="initiative-country-options"></datalist>
                    <div class="form-text">
                        Search and select a country from the list.
                    </div>
                </div>

                <div
                    class="col-12 d-none"
                    data-location-detail="platform"
                >
                    <label
                        for="online_platform_name"
                        class="form-label"
                    >
                        Online Platform Name *
                    </label>
                    <input
                        id="online_platform_name"
                        name="online_platform_name"
                        class="form-control"
                        maxlength="200"
                        placeholder="Enter the online platform name."
                    >
                </div>
            </div>
        </div>

        <div class="initiative-form-section" data-form-step="3">
            <div class="initiative-form-section-heading">
                <div>
                    <p class="eyebrow mb-2">Section 4 of 6</p>
                    <h2>Agreements, partners, and collaborators</h2>
                    <p>
                        Link an active Agreement when relevant, identify external partners, and add internal collaborators.
                    </p>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-12">
                    <label class="form-label">
                        Is this Initiative linked to an existing Agreement or memorandum of understanding? *
                    </label>
                    <div class="initiative-choice-grid initiative-choice-grid-two">
                        <label class="initiative-choice-card">
                            <input type="radio" name="has_related_agreement" value="true">
                            <span>Yes</span>
                        </label>
                        <label class="initiative-choice-card">
                            <input type="radio" name="has_related_agreement" value="false">
                            <span>No</span>
                        </label>
                    </div>
                </div>

                <div class="col-12 d-none" data-related-agreement-wrap>
                    <label
                        for="related_agreement_id"
                        class="form-label"
                    >
                        Agreement Related to the Initiative *
                    </label>
                    <select
                        id="related_agreement_id"
                        name="related_agreement_id"
                        class="form-select"
                    >
                        <option value="">Select an Agreement</option>
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label">
                        Is an external partner involved? *
                    </label>
                    <div class="initiative-choice-grid initiative-choice-grid-two">
                        <label class="initiative-choice-card">
                            <input type="radio" name="has_external_partner" value="true">
                            <span>Yes</span>
                        </label>
                        <label class="initiative-choice-card">
                            <input type="radio" name="has_external_partner" value="false">
                            <span>No</span>
                        </label>
                    </div>
                </div>

                <div class="col-12 d-none" data-external-partner-wrap>
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label
                                for="external_partner_name"
                                class="form-label"
                            >
                                External Partner Entity Name *
                            </label>
                            <input
                                id="external_partner_name"
                                name="external_partner_name"
                                class="form-control"
                                maxlength="255"
                            >
                        </div>
                        <div class="col-md-6">
                            <label
                                for="external_partner_role"
                                class="form-label"
                            >
                                Partner Role in the Initiative
                            </label>
                            <input
                                id="external_partner_role"
                                name="external_partner_role"
                                class="form-control"
                                maxlength="500"
                            >
                        </div>
                    </div>
                </div>
            </div>

            <div class="initiative-form-subcard mt-4">
                <div class="initiative-form-subcard-heading">
                    <div>
                        <h3>Internal collaborators</h3>
                        <p>
                            Collaborators follow the full route, receive notifications, and may convert the approved request.
                        </p>
                    </div>
                    <button
                        class="btn btn-outline-primary btn-sm"
                        type="button"
                        data-add-collaborator
                    >
                        Add collaborator
                    </button>
                </div>
                <div
                    class="initiative-collaborator-list"
                    data-collaborator-list
                ></div>
            </div>
        </div>

        <div class="initiative-form-section" data-form-step="4">
            <div class="initiative-form-section-heading">
                <div>
                    <p class="eyebrow mb-2">Section 5 of 6</p>
                    <h2>Requirements and supporting files</h2>
                    <p>
                        Identify the resources needed to implement the Initiative and attach supporting material.
                    </p>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-12">
                    <label class="form-label">
                        Required Support and Resources *
                    </label>
                    <div class="initiative-choice-grid">
                        <label class="initiative-choice-card">
                            <input type="checkbox" name="required_resources" value="VENUE">
                            <span>Venue</span>
                        </label>
                        <label class="initiative-choice-card">
                            <input type="checkbox" name="required_resources" value="BUDGET">
                            <span>Budget or Funding</span>
                        </label>
                        <label class="initiative-choice-card">
                            <input type="checkbox" name="required_resources" value="MEDIA">
                            <span>Media Support</span>
                        </label>
                        <label class="initiative-choice-card">
                            <input type="checkbox" name="required_resources" value="TRANSPORT">
                            <span>Transportation</span>
                        </label>
                        <label class="initiative-choice-card">
                            <input type="checkbox" name="required_resources" value="EQUIPMENT">
                            <span>Equipment or Technical Support</span>
                        </label>
                        <label class="initiative-choice-card">
                            <input type="checkbox" name="required_resources" value="NONE">
                            <span>No Additional Requirements</span>
                        </label>
                        <label class="initiative-choice-card">
                            <input type="checkbox" name="required_resources" value="OTHER">
                            <span>Other</span>
                        </label>
                    </div>
                    <input
                        class="form-control mt-3 d-none"
                        name="resource_other"
                        maxlength="200"
                        placeholder="Specify the other requirement"
                        data-conditional-field="resource-other"
                    >
                </div>

                <div class="col-md-6">
                    <label
                        for="estimated_budget"
                        class="form-label"
                    >
                        Estimated budget
                    </label>
                    <input
                        id="estimated_budget"
                        name="estimated_budget"
                        type="number"
                        min="0"
                        step="0.001"
                        class="form-control"
                    >
                </div>

                <div class="col-md-6">
                    <label
                        for="needs_media_support"
                        class="form-label"
                    >
                        Does the Initiative need media support?
                    </label>
                    <select
                        id="needs_media_support"
                        name="needs_media_support"
                        class="form-select"
                    >
                        <option value="">Select</option>
                        <option value="true">Yes</option>
                        <option value="false">No</option>
                    </select>
                </div>

                <div class="col-12">
                    <div class="initiative-upload-box">
                        <div>
                            <h3>Supporting attachments</h3>
                            <p>
                                Optional. Add up to 5 PDF, Word, Excel, PowerPoint, JPG, or PNG files. Maximum 10 MB per file.
                            </p>
                        </div>
                        <input
                            id="initiative_attachments"
                            type="file"
                            class="form-control"
                            multiple
                            accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png"
                            data-attachment-input
                        >
                    </div>

                    <div
                        class="initiative-attachment-queue mt-3"
                        data-attachment-queue
                    ></div>

                    <div
                        class="initiative-existing-attachments mt-3"
                        data-existing-attachments
                    ></div>
                </div>
            </div>
        </div>

        <div class="initiative-form-section" data-form-step="5">
            <div class="initiative-form-section-heading">
                <div>
                    <p class="eyebrow mb-2">Section 6 of 6</p>
                    <h2>Sustainability and approval</h2>
                    <p>
                        Select relevant Sustainable Development Goals, confirm the declaration, and review the approval route.
                    </p>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-12">
                    <label class="form-label">
                        Does this Initiative support the Sustainable Development Goals? *
                    </label>
                    <div class="initiative-choice-grid initiative-choice-grid-two">
                        <label class="initiative-choice-card">
                            <input type="radio" name="supports_sdg" value="true">
                            <span>Yes</span>
                        </label>
                        <label class="initiative-choice-card">
                            <input type="radio" name="supports_sdg" value="false">
                            <span>No</span>
                        </label>
                    </div>
                </div>

                <div class="col-12 d-none" data-sdg-wrap>
                    <label class="form-label">
                        Relevant SDGs *
                    </label>
                    <div class="initiative-sdg-grid">
                        <label><input type="checkbox" name="sdg_goals" value="SDG_1"><span><b>1</b>No Poverty</span></label>
                        <label><input type="checkbox" name="sdg_goals" value="SDG_2"><span><b>2</b>Zero Hunger</span></label>
                        <label><input type="checkbox" name="sdg_goals" value="SDG_3"><span><b>3</b>Good Health and Well-being</span></label>
                        <label><input type="checkbox" name="sdg_goals" value="SDG_4"><span><b>4</b>Quality Education</span></label>
                        <label><input type="checkbox" name="sdg_goals" value="SDG_5"><span><b>5</b>Gender Equality</span></label>
                        <label><input type="checkbox" name="sdg_goals" value="SDG_6"><span><b>6</b>Clean Water and Sanitation</span></label>
                        <label><input type="checkbox" name="sdg_goals" value="SDG_7"><span><b>7</b>Affordable and Clean Energy</span></label>
                        <label><input type="checkbox" name="sdg_goals" value="SDG_8"><span><b>8</b>Decent Work and Economic Growth</span></label>
                        <label><input type="checkbox" name="sdg_goals" value="SDG_9"><span><b>9</b>Industry, Innovation and Infrastructure</span></label>
                        <label><input type="checkbox" name="sdg_goals" value="SDG_10"><span><b>10</b>Reduced Inequalities</span></label>
                        <label><input type="checkbox" name="sdg_goals" value="SDG_11"><span><b>11</b>Sustainable Cities and Communities</span></label>
                        <label><input type="checkbox" name="sdg_goals" value="SDG_12"><span><b>12</b>Responsible Consumption and Production</span></label>
                        <label><input type="checkbox" name="sdg_goals" value="SDG_13"><span><b>13</b>Climate Action</span></label>
                        <label><input type="checkbox" name="sdg_goals" value="SDG_14"><span><b>14</b>Life Below Water</span></label>
                        <label><input type="checkbox" name="sdg_goals" value="SDG_15"><span><b>15</b>Life on Land</span></label>
                        <label><input type="checkbox" name="sdg_goals" value="SDG_16"><span><b>16</b>Peace, Justice and Strong Institutions</span></label>
                        <label><input type="checkbox" name="sdg_goals" value="SDG_17"><span><b>17</b>Partnerships for the Goals</span></label>
                    </div>
                </div>

                <div class="col-12">
                    <label class="initiative-declaration">
                        <input
                            type="checkbox"
                            name="declaration_confirmed"
                            value="true"
                        >
                        <span>
                            I confirm that the information provided is accurate and that implementation will not begin before formal approval is granted.
                        </span>
                    </label>
                </div>
            </div>

            <div class="initiative-form-subcard mt-4">
                <div class="initiative-form-subcard-heading">
                    <div>
                        <h3>Approval route preview</h3>
                        <p>
                            The final route is generated from the requester’s official position, department, and college.
                        </p>
                    </div>
                </div>
                <div
                    class="workflow-timeline"
                    data-route-preview
                ></div>
            </div>
        </div>
        </div>
    </section>

    <div class="initiative-form-actions mt-4">
        <div class="initiative-form-actions-left">
            <button
                class="btn btn-outline-secondary"
                type="button"
                data-previous-step
            >
                Previous
            </button>
            <button
                class="btn btn-outline-primary"
                type="button"
                data-next-step
            >
                Next section
            </button>
        </div>
        <div class="initiative-form-actions-right">
            <button
                class="btn btn-outline-secondary"
                type="button"
                data-save-draft
            >
                Save draft
            </button>
            <button
                class="btn btn-primary d-none"
                type="submit"
                data-submit-request
            >
                Submit for approval
            </button>
        </div>
    </div>
</form>

<div
    class="modal fade initiative-map-picker-modal"
    id="initiativeLocationMapModal"
    tabindex="-1"
    aria-labelledby="initiativeLocationMapModalLabel"
    aria-hidden="true"
    data-map-picker-modal
>
    <div
        class="modal-dialog modal-xl modal-dialog-centered"
    >
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h2
                        class="modal-title fs-5"
                        id="initiativeLocationMapModalLabel"
                    >
                        Choose location on map
                    </h2>
                    <p class="mb-0 mt-1 text-body-secondary">
                        Search for a place, click a labelled place, or drop the pin on any point.
                    </p>
                </div>
                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                    aria-label="Close"
                ></button>
            </div>

            <div class="modal-body">
                <div class="initiative-map-picker-search">
                    <label class="form-label">Search inside the map</label>
                    <div
                        class="initiative-map-place-host"
                        data-map-place-host
                    ></div>
                    <div
                        class="form-text"
                        data-map-picker-status
                        aria-live="polite"
                    >
                        You can also click directly on the map.
                    </div>
                </div>

                <div
                    class="initiative-map-canvas"
                    data-map-canvas
                    role="application"
                    aria-label="Interactive Google Map location picker"
                ></div>

                <div
                    class="initiative-map-candidate d-none"
                    data-map-candidate
                    aria-live="polite"
                >
                    <div class="initiative-map-candidate-heading">
                        <div>
                            <small>Selected map point</small>
                            <strong data-map-candidate-title></strong>
                        </div>
                        <span data-map-candidate-coordinates></span>
                    </div>

                    <div class="row g-3 mt-1">
                        <div class="col-md-5">
                            <label
                                for="initiativeMapLocationName"
                                class="form-label"
                            >
                                Location name
                            </label>
                            <input
                                id="initiativeMapLocationName"
                                type="text"
                                class="form-control"
                                maxlength="255"
                                placeholder="Example: Main Hall or restaurant name"
                                data-map-candidate-name
                            >
                        </div>
                        <div class="col-md-7">
                            <label class="form-label">Address</label>
                            <div
                                class="initiative-map-candidate-address"
                                data-map-candidate-address
                            ></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button
                    type="button"
                    class="btn btn-outline-secondary"
                    data-bs-dismiss="modal"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    class="btn btn-primary"
                    data-confirm-map-location
                    disabled
                >
                    Confirm location
                </button>
            </div>
        </div>
    </div>
</div>

<template data-collaborator-template>
    <div
        class="initiative-collaborator-row"
        data-collaborator-row
    >
        <div class="flex-grow-1">
            <label class="form-label">Collaborator</label>
            <select
                class="form-select"
                data-collaborator-user
                required
            >
                <option value="">Select a person</option>
            </select>
        </div>
        <div>
            <label class="form-label">Access</label>
            <select
                class="form-select"
                data-collaborator-role
            >
                <option value="COLLABORATOR">
                    Collaborator
                </option>
                <option value="CO_OWNER">
                    Co-owner
                </option>
            </select>
        </div>
        <button
            class="btn btn-outline-danger"
            type="button"
            data-remove-collaborator
        >
            Remove
        </button>
    </div>
</template>

<?php elseif (in_array($view, ['convert', 'existing'], true)): ?>
<?php $isExistingFinalForm = $view === 'existing'; ?>
<div class="alert alert-danger d-none" role="alert" data-module-alert></div>

<div class="loading-state" data-loading>
    <div class="spinner-border text-primary" aria-hidden="true"></div>
    <span><?= $isExistingFinalForm ? 'Preparing the existing Initiative form…' : 'Preparing the approved Initiative form…' ?></span>
</div>

<div class="d-none" data-final-content data-final-mode="<?= $isExistingFinalForm ? 'existing' : 'approved' ?>">
    <section class="initiative-final-hero">
        <div>
            <div class="initiative-final-hero-meta">
                <a
                    href="<?= $isExistingFinalForm
                        ? 'initiative-portfolio.php'
                        : 'initiative-workflow.php?view=detail&amp;id=' . (int) ($_GET['id'] ?? 0) ?>"
                    class="initiative-final-back"
                >
                    <span aria-hidden="true">←</span>
                    <span><?= $isExistingFinalForm ? 'Final Initiatives' : 'Approved request' ?></span>
                </a>
                <span class="initiative-final-meta-divider" aria-hidden="true"></span>
                <p class="eyebrow mb-0"><?= $isExistingFinalForm ? 'Existing University record' : 'Approved Initiative record' ?></p>
            </div>
            <h1><?= $isExistingFinalForm ? 'Register Existing Initiative' : 'Create Final Initiative' ?></h1>
            <p>
                <?= $isExistingFinalForm
                    ? 'Register an Initiative approved before the current workflow. The record is saved directly in PostgreSQL and identified as an existing University record.'
                    : 'Every value from the approved request is prefilled. Review it, change anything that needs updating, and save the final record directly in PostgreSQL.' ?>
            </p>
            <div class="initiative-final-source">
                <strong data-final-source-title></strong>
                <span data-final-request-code></span>
            </div>
        </div>
        <div class="initiative-final-hero-badge">
            <span>5</span>
            <small>FORM SECTIONS</small>
            <b data-final-state>Ready</b>
        </div>
    </section>

    <form class="initiative-final-form workspace-standard-form" novalidate data-final-form data-final-mode="<?= $isExistingFinalForm ? 'existing' : 'approved' ?>">
        <div class="initiative-final-shell">
            <aside class="initiative-final-step-panel">
                <h2>Form Steps</h2>
                <nav
                    class="initiative-final-steps"
                    aria-label="Final Initiative form sections"
                >
                    <button class="initiative-final-step is-active" type="button" data-final-step-button="0">
                        <span class="initiative-final-step-marker">1</span>
                        <span class="initiative-final-step-copy">
                            <small>Step 1</small>
                            <strong>General Information</strong>
                        </span>
                    </button>
                    <button class="initiative-final-step" type="button" data-final-step-button="1">
                        <span class="initiative-final-step-marker">2</span>
                        <span class="initiative-final-step-copy">
                            <small>Step 2</small>
                            <strong>Timing &amp; Location</strong>
                        </span>
                    </button>
                    <button class="initiative-final-step" type="button" data-final-step-button="2">
                        <span class="initiative-final-step-marker">3</span>
                        <span class="initiative-final-step-copy">
                            <small>Step 3</small>
                            <strong>Beneficiaries &amp; Impact</strong>
                        </span>
                    </button>
                    <button class="initiative-final-step" type="button" data-final-step-button="3">
                        <span class="initiative-final-step-marker">4</span>
                        <span class="initiative-final-step-copy">
                            <small>Step 4</small>
                            <strong>Rankings &amp; SDGs</strong>
                        </span>
                    </button>
                    <button class="initiative-final-step" type="button" data-final-step-button="4">
                        <span class="initiative-final-step-marker">5</span>
                        <span class="initiative-final-step-copy">
                            <small>Step 5</small>
                            <strong>Documentation &amp; Notes</strong>
                        </span>
                    </button>
                </nav>
            </aside>

            <div class="initiative-final-content">
                <section class="initiative-final-section is-active" data-final-step="0">
                    <header class="initiative-final-section-heading">
                        <div>
                            <p class="eyebrow mb-2">Section 1 of 5</p>
                            <h2>General Information</h2>
                            <p>
                                Approval reference, Agreement, Initiative identity,
                                implementing entity, and responsible people.
                            </p>
                        </div>
                        <span class="initiative-prefill-badge"><?= $isExistingFinalForm ? 'Manual existing record' : 'Prefilled from request' ?></span>
                    </header>

                    <div class="row g-4">
                        <div class="<?= $isExistingFinalForm ? 'col-md-4' : 'col-md-6' ?>">
                            <label class="form-label">
                                <?= $isExistingFinalForm ? 'Previous Reference' : 'Approval Request ID' ?>
                            </label>
                            <input
                                class="form-control"
                                name="approval_request_id"
                                <?= $isExistingFinalForm ? 'placeholder="Previous reference, memo, or archive number"' : 'readonly' ?>
                            >
                        </div>
                        <?php if ($isExistingFinalForm): ?>
                            <div class="col-md-4">
                                <label class="form-label">Original Approval Date</label>
                                <input type="date" class="form-control" name="legacy_approval_date">
                            </div>
                        <?php endif; ?>
                        <div class="<?= $isExistingFinalForm ? 'col-md-4' : 'col-md-6' ?>">
                            <label class="form-label">Initiative Number</label>
                            <input class="form-control" name="initiative_number" placeholder="Generated automatically when finalized" readonly>
                        </div>

                        <div class="col-12">
                            <div class="initiative-final-subheading">
                                <div>
                                    <h3>Requester Information</h3>
                                    <p>
                                        Copied from the approved request. These values may be
                                        updated for the final Initiative record.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Requester Name</label>
                            <input class="form-control" name="requester_name">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Requester Email</label>
                            <input type="email" class="form-control" name="requester_email">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Requester Mobile</label>
                            <input class="form-control" name="requester_mobile">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Requester Category</label>
                            <select class="form-select" name="requester_type">
                                <option value="">Select</option>
                                <option value="FACULTY">Academic Staff</option>
                                <option value="STAFF">Administrative Staff</option>
                                <option value="STUDENT">Student</option>
                                <option value="STUDENT_GROUP">Student group</option>
                                <option value="OTHER">Other</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Other Requester Category</label>
                            <input class="form-control" name="requester_type_other">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Requester Position</label>
                            <input class="form-control" name="requester_position">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Requester Entity</label>
                            <input class="form-control" name="requester_entity">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Requester Department / Unit</label>
                            <input class="form-control" name="requester_department">
                        </div>

                        <div class="col-12">
                            <label class="form-label">
                                Is the Initiative linked to an existing Agreement?
                            </label>
                            <div class="initiative-final-choice-row">
                                <label><input type="radio" name="related_agreement" value="true"> Yes</label>
                                <label><input type="radio" name="related_agreement" value="false"> No</label>
                            </div>
                        </div>
                        <div class="col-md-8" data-final-related-agreement>
                            <label class="form-label">Related Agreement</label>
                            <select class="form-select" name="related_agreement_id">
                                <option value="">Select an Agreement</option>
                            </select>
                        </div>
                        <div class="col-md-4" data-final-related-agreement>
                            <label class="form-label">Relationship Notes</label>
                            <input class="form-control" name="relation_notes" maxlength="500">
                        </div>

                        <div class="col-md-8">
                            <label class="form-label">Initiative or Activity title *</label>
                            <input class="form-control" name="title" maxlength="255" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Initiative Type *</label>
                            <select class="form-select" name="initiative_type" required>
                                <option value="">Select a type</option>
                                <option value="WORKSHOP_TRAINING">Workshop / Training</option>
<option value="LECTURE_SEMINAR">Lecture / Seminar</option>
<option value="CONFERENCE_FORUM">Conference / Forum</option>
<option value="EXHIBITION_FAIR">Exhibition / Fair</option>
<option value="COMPETITION_HACKATHON">Competition / Hackathon</option>
<option value="CULTURAL_SPORTS_EVENT">Cultural / Arts / Sports Event</option>
<option value="FIELD_VISIT">Field Visit</option>
<option value="EXCHANGE_PROGRAM">Exchange Program</option>
<option value="JOINT_RESEARCH">Joint Research</option>
<option value="COORDINATION_PROFESSIONAL_MEETING">Coordination / Professional Meeting</option>
<option value="STUDENT_INITIATIVE">Student Initiative</option>
<option value="COMMUNITY_ENGAGEMENT">Community Engagement</option>
<option value="VOLUNTEERING">Volunteering Program</option>
<option value="CONSULTATION">Consultation / Advisory Role</option>
<option value="AWARENESS_CAMPAIGN">Awareness Campaign / Media Engagement</option>
<option value="CAPACITY_BUILDING_TRAINING">Capacity Building & Community Training</option>
<option value="PARTNERSHIP">Community Partnership / Joint Project</option>
<option value="KNOWLEDGE_TRANSFER">Knowledge Transfer</option>
<option value="TUTORING_COACHING_MENTORSHIP">Tutoring / Coaching / Mentorship</option>
<option value="PROFESSIONAL_MEMBERSHIP">Professional Membership / Committee / Jury</option>
<option value="MEDIA_ARTICLE">Media / Newspaper Article</option>
<option value="SCHOOL_OUTREACH">School Outreach Activities</option>
<option value="VULNERABLE_GROUPS">Support for Vulnerable Groups</option>
<option value="SUSTAINABILITY">Sustainability Activities</option>
<option value="RESEARCH">Research</option>
<option value="ACADEMIC">Academic</option>
<option value="INNOVATION">Innovation</option>
<option value="OTHER">Other</option>
                            </select>
                        </div>
                        <div class="col-12 d-none" data-final-other-type>
                            <label class="form-label">Other Initiative Type</label>
                            <input class="form-control" name="initiative_type_other">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Secondary Initiative Types</label>
                            <select class="form-select" name="secondary_initiative_types" multiple size="6">
                                <option value="WORKSHOP_TRAINING">Workshop / Training</option>
<option value="LECTURE_SEMINAR">Lecture / Seminar</option>
<option value="CONFERENCE_FORUM">Conference / Forum</option>
<option value="EXHIBITION_FAIR">Exhibition / Fair</option>
<option value="COMPETITION_HACKATHON">Competition / Hackathon</option>
<option value="CULTURAL_SPORTS_EVENT">Cultural / Arts / Sports Event</option>
<option value="FIELD_VISIT">Field Visit</option>
<option value="EXCHANGE_PROGRAM">Exchange Program</option>
<option value="JOINT_RESEARCH">Joint Research</option>
<option value="COORDINATION_PROFESSIONAL_MEETING">Coordination / Professional Meeting</option>
<option value="STUDENT_INITIATIVE">Student Initiative</option>
<option value="COMMUNITY_ENGAGEMENT">Community Engagement</option>
<option value="VOLUNTEERING">Volunteering Program</option>
<option value="CONSULTATION">Consultation / Advisory Role</option>
<option value="AWARENESS_CAMPAIGN">Awareness Campaign / Media Engagement</option>
<option value="CAPACITY_BUILDING_TRAINING">Capacity Building & Community Training</option>
<option value="PARTNERSHIP">Community Partnership / Joint Project</option>
<option value="KNOWLEDGE_TRANSFER">Knowledge Transfer</option>
<option value="TUTORING_COACHING_MENTORSHIP">Tutoring / Coaching / Mentorship</option>
<option value="PROFESSIONAL_MEMBERSHIP">Professional Membership / Committee / Jury</option>
<option value="MEDIA_ARTICLE">Media / Newspaper Article</option>
<option value="SCHOOL_OUTREACH">School Outreach Activities</option>
<option value="VULNERABLE_GROUPS">Support for Vulnerable Groups</option>
<option value="SUSTAINABILITY">Sustainability Activities</option>
<option value="RESEARCH">Research</option>
<option value="ACADEMIC">Academic</option>
<option value="INNOVATION">Innovation</option>

                            </select>
                            <div class="form-text">Hold Ctrl to select more than one type.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Implementing Entity *</label>
                            <input class="form-control" name="entity" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">External Entities</label>
                            <input class="form-control" name="external_entities">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Is an External Partner Involved?</label>
                            <div class="initiative-final-choice-row">
                                <label><input type="radio" name="has_external_partner" value="true"> Yes</label>
                                <label><input type="radio" name="has_external_partner" value="false"> No</label>
                            </div>
                        </div>
                        <div class="col-md-6" data-final-external-partner>
                            <label class="form-label">External Partner Name</label>
                            <input class="form-control" name="external_partner_name">
                        </div>
                        <div class="col-md-6" data-final-external-partner>
                            <label class="form-label">External Partner Role</label>
                            <input class="form-control" name="external_partner_role">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Provider Categories</label>
                            <select class="form-select" name="provider_categories" multiple size="5">
                                <option value="ACADEMIC">Academic staff</option>
                                <option value="ADMINISTRATIVE">Administrative staff</option>
                                <option value="STUDENTS">Students</option>
                                <option value="STUDENT_GROUP">Student group</option>
                                <option value="JOINT">Joint University team</option>
                                <option value="EXTERNAL_PARTNER">External partner</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Department / Center / Unit</label>
                            <input class="form-control" name="department_unit">
                        </div>

                        <div class="col-12">
                            <div class="initiative-final-subheading">
                                <div>
                                    <h3>Responsible People</h3>
                                    <p>
                                        Request owner and collaborators are copied here.
                                        Their final Initiative responsibilities can be edited.
                                    </p>
                                </div>
                                <button class="btn btn-outline-primary btn-sm" type="button" data-add-final-contributor>
                                    Add person
                                </button>
                            </div>
                            <div class="initiative-final-people" data-final-contributors></div>
                        </div>
                    </div>
                </section>

                <section class="initiative-final-section d-none" data-final-step="1">
                    <header class="initiative-final-section-heading">
                        <div>
                            <p class="eyebrow mb-2">Section 2 of 5</p>
                            <h2>Timing &amp; Location</h2>
                            <p>
                                Dates, operational status, recurrence, venue,
                                international participation, description, and objectives.
                            </p>
                        </div>
                    </header>

                    <div class="row g-4">
                        <div class="col-md-4">
                            <label class="form-label">Start Date *</label>
                            <input type="date" class="form-control" name="start_date" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">End Date</label>
                            <input type="date" class="form-control" name="end_date">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Duration Hours</label>
                            <input type="number" min="0" step="0.5" class="form-control" name="duration_hours">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Activity Status *</label>
                            <select class="form-select" name="activity_status" required>
                                <option value="">Select status</option>
                                <option value="PLANNED">Planned</option>
                                <option value="IN_PROGRESS">In progress</option>
                                <option value="COMPLETED">Completed</option>
                                <option value="POSTPONED">Postponed</option>
                                <option value="CANCELLED">Cancelled</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Activity Recurrence</label>
                            <select class="form-select" name="activity_recurrence">
                                <option value="">Select recurrence</option>
                                <option value="ONE_TIME">One time</option>
                                <option value="ANNUAL">Annual</option>
                                <option value="SEMESTER">Every semester</option>
                                <option value="MONTHLY">Monthly</option>
                                <option value="ONGOING">Ongoing</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Academic Year / Reporting Period</label>
                            <input class="form-control" name="academic_year" placeholder="2026/2027">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Delivery Location *</label>
                            <select class="form-select" name="location_mode" required>
                                <option value="">Select a delivery location</option>
                                <option value="WITHIN_UOB">Within UOB</option>
                                <option value="OUTSIDE_UOB">Outside UOB</option>
                                <option value="VIRTUAL">Virtual</option>
                                <option value="HYBRID">Hybrid</option>
                                <option value="OTHER">Other</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Other Implementation Scope</label>
                            <input class="form-control" name="implementation_scope_other">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Venue or Entity Name</label>
                            <input class="form-control" name="proposed_venue">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Additional Location Details</label>
                            <input class="form-control" name="outside_location">
                        </div>

                        <div class="col-12">
                            <label class="form-label">International Participation</label>
                            <div class="initiative-final-choice-row">
                                <label><input type="radio" name="international_participation" value="true"> Yes</label>
                                <label><input type="radio" name="international_participation" value="false"> No</label>
                            </div>
                        </div>
                        <div class="col-12 d-none" data-final-international>
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label class="form-label">International Countries</label>
                                    <input class="form-control" name="international_countries_text" placeholder="Bahrain, Saudi Arabia, United Kingdom">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">International Participants</label>
                                    <input type="number" min="0" class="form-control" name="international_participants">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">International Partner</label>
                                    <input class="form-control" name="international_partner">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">International Partner Type</label>
                                    <input class="form-control" name="international_partner_type">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">International Collaboration Nature</label>
                                    <select class="form-select" name="international_collaboration_nature" multiple size="5">
                                        <option value="RESEARCH">Research</option>
                                        <option value="TEACHING">Teaching</option>
                                        <option value="TRAINING">Training</option>
                                        <option value="EXCHANGE">Exchange</option>
                                        <option value="FUNDING">Funding</option>
                                        <option value="JOINT_ORGANIZATION">Joint organization</option>
                                        <option value="KNOWLEDGE_TRANSFER">Knowledge transfer</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Initiative Contribution Areas</label>
                            <select class="form-select" name="initiative_descriptors" multiple size="6">
                                <option value="RESEARCH">Research</option>
                                <option value="STUDENT_SUPPORT">Student support</option>
                                <option value="STAFF_PRACTICES">Staff practices</option>
                                <option value="COMMUNITY_OUTREACH">Community outreach</option>
                                <option value="CAMPUS_OPERATIONS">Campus operations</option>
                                <option value="GOVERNANCE">Governance, policies, or reports</option>
                                <option value="OTHER">Other</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Description *</label>
                            <textarea class="form-control" name="description" rows="5" required></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Objectives *</label>
                            <textarea class="form-control" name="objectives" rows="4" required></textarea>
                        </div>
                    </div>
                </section>

                <section class="initiative-final-section d-none" data-final-step="2">
                    <header class="initiative-final-section-heading">
                        <div>
                            <p class="eyebrow mb-2">Section 3 of 5</p>
                            <h2>Beneficiaries &amp; Impact</h2>
                            <p>
                                Audience, participant counts, resources, funding,
                                training, volunteering, outputs, and impact.
                            </p>
                        </div>
                    </header>

                    <div class="row g-4">
                        <div class="col-12">
                            <label class="form-label">Beneficiary or Target Groups *</label>
                            <select class="form-select" name="target_groups" multiple size="8" required>
                                <option value="UNIVERSITY_STUDENTS">University students</option>
<option value="PROFESSIONALS">Professionals</option>
<option value="OPEN_PUBLIC">Open to public</option>
<option value="FACULTY_STAFF">Faculty and staff members</option>
<option value="SCHOOL_STUDENTS">School students</option>
<option value="ALUMNI">Alumni</option>
<option value="LOCAL_COMMUNITY">Local community</option>
<option value="VULNERABLE_GROUPS">Disadvantaged groups</option>
<option value="NGOS">NGOs / Civil Society</option>
<option value="GOVERNMENT_ENTITIES">Government Entities</option>
<option value="PRIVATE_SECTOR">Private Sector</option>
<option value="PERSONS_WITH_DISABILITIES">Persons with Disabilities</option>
<option value="CHILDREN_ELDERLY">Children / Older People</option>
<option value="OTHER">Other</option>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Other Target Group</label>
                            <input class="form-control" name="target_group_other">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Expected Beneficiaries or Attendees from Request</label>
                            <input type="number" min="0" class="form-control" name="expected_participants">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Male Beneficiaries</label>
                            <input type="number" min="0" class="form-control" name="male_count">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Female Beneficiaries</label>
                            <input type="number" min="0" class="form-control" name="female_count">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Unspecified Beneficiaries</label>
                            <input type="number" min="0" class="form-control" name="unspecified_count">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Count Basis</label>
                            <select class="form-select" name="beneficiary_count_basis">
                                <option value="">Select basis</option>
                                <option value="ACTUAL">Actual</option>
                                <option value="ESTIMATED">Estimated</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Youth 18–35</label>
                            <select class="form-select" name="youth_18_35">
                                <option value="">Not specified</option>
                                <option value="YES">Yes</option>
                                <option value="NO">No</option>
                                <option value="PARTIAL">Partially</option>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Beneficiaries Summary</label>
                            <input class="form-control" name="beneficiaries">
                        </div>

                        <div class="col-12">
                            <label class="form-label">Resources Mobilized</label>
                            <select class="form-select" name="resources_mobilized_options" multiple size="6">
                                <option value="BUDGET">Internal budget</option>
                                <option value="EXTERNAL_FUNDING">External funding</option>
                                <option value="VOLUNTEERS">Volunteers</option>
                                <option value="STAFF_HOURS">Staff hours</option>
                                <option value="FACILITIES">Facilities / Venue</option>
                                <option value="EQUIPMENT">Equipment / Technical support</option>
                                <option value="MEDIA_SUPPORT">Media support</option>
                                <option value="TRANSPORT">Transportation</option>
                                <option value="PARTNERSHIPS">Partnerships</option>
                                <option value="OTHER">Other</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Expected Budget (BHD)</label>
                            <input type="number" min="0" step="0.001" class="form-control" name="expected_budget">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Internal Funding (BHD)</label>
                            <input type="number" min="0" step="0.001" class="form-control" name="internal_funding_bhd">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">In-kind Support (BHD)</label>
                            <input type="number" min="0" step="0.001" class="form-control" name="in_kind_support_bhd">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">External Funding Amount</label>
                            <input type="number" min="0" step="0.001" class="form-control" name="external_funding_amount">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Funding Currency</label>
                            <input class="form-control" name="external_funding_currency">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Funding Entity</label>
                            <input class="form-control" name="funding_entity">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Training Hours</label>
                            <input type="number" min="0" step="0.5" class="form-control" name="training_hours">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Number of Trainees</label>
                            <input type="number" min="0" class="form-control" name="trainees_count">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Number of Volunteers</label>
                            <input type="number" min="0" class="form-control" name="volunteers_count">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Volunteer Hours per Person</label>
                            <input type="number" min="0" step="0.5" class="form-control" name="volunteer_hours_per_person">
                        </div>

                        <div class="col-12">
                            <label class="form-label">Direct Outputs Achieved</label>
                            <textarea class="form-control" name="direct_outputs" rows="4"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Expected Impact</label>
                            <textarea class="form-control" name="expected_impact" rows="4"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Societal / Environmental / Health Impact</label>
                            <textarea class="form-control" name="societal_impact" rows="4"></textarea>
                        </div>
                    </div>
                </section>

                <section class="initiative-final-section d-none" data-final-step="3">
                    <header class="initiative-final-section-heading">
                        <div>
                            <p class="eyebrow mb-2">Section 4 of 5</p>
                            <h2>Rankings &amp; SDGs</h2>
                            <p>
                                Global ranking relevance, environmental measurement,
                                and Sustainable Development Goals.
                            </p>
                        </div>
                    </header>

                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label">Activity Relevance to Global Rankings</label>
                            <select class="form-select" name="ranking_framework">
                                <option value="NONE">Not linked to a ranking framework</option>
                                <option value="THE">Times Higher Education</option>
                                <option value="QS">QS Sustainability</option>
                                <option value="BOTH">THE and QS</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">THE Areas</label>
                            <select class="form-select" name="the_areas" multiple size="4">
                                <option value="TEACHING">Teaching</option>
                                <option value="RESEARCH">Research</option>
                                <option value="OUTREACH">Outreach</option>
                                <option value="STEWARDSHIP">Stewardship</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">QS Categories</label>
                            <select class="form-select" name="qs_categories" multiple size="3">
                                <option value="ENVIRONMENTAL">Environmental Impact</option>
                                <option value="SOCIAL">Social Impact</option>
                                <option value="GOVERNANCE">Governance</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <div class="initiative-final-subheading">
                                <div>
                                    <h3>Environmental Impact Measurement</h3>
                                    <p>Complete these fields when environmental outcomes apply.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Environmental Impact Types</label>
                            <select class="form-select" name="environmental_impact_types" multiple size="5">
                                <option value="ENERGY">Energy</option>
                                <option value="WATER">Water</option>
                                <option value="WASTE">Waste</option>
                                <option value="EMISSIONS">Emissions</option>
                                <option value="TREES">Tree Planting</option>
                                <option value="TRANSPORT">Sustainable Transport</option>
                                <option value="BIODIVERSITY">Biodiversity</option>
                                <option value="PROCUREMENT">Sustainable Procurement or Consumption</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Value Before the Initiative</label>
                            <input type="number" step="any" class="form-control" name="environmental_before_value">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Value After the Initiative</label>
                            <input type="number" step="any" class="form-control" name="environmental_after_value">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Improvement Value</label>
                            <input type="number" step="any" class="form-control" name="environmental_improvement_value">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Unit of Measurement</label>
                            <input class="form-control" name="environmental_unit">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Measurement Basis</label>
                            <select class="form-select" name="environmental_measurement_basis">
                                <option value="">Select basis</option>
                                <option value="ACTUAL">Actual</option>
                                <option value="ESTIMATED">Estimated</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Data or Measurement Source</label>
                            <input class="form-control" name="environmental_data_source">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Environmental Impact Description</label>
                            <textarea class="form-control" name="environmental_impact" rows="4"></textarea>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Does this Initiative support the Sustainable Development Goals?</label>
                            <div class="initiative-final-choice-row">
                                <label><input type="radio" name="supports_sdg" value="true"> Yes</label>
                                <label><input type="radio" name="supports_sdg" value="false"> No</label>
                            </div>
                        </div>
                        <div class="col-md-6" data-final-sdg>
                            <label class="form-label">Primary Sustainable Development Goal</label>
                            <select class="form-select" name="primary_sdg">
                                <option value="">Select the primary goal</option>
                                <option value="SDG_1">SDG 1</option>
<option value="SDG_2">SDG 2</option>
<option value="SDG_3">SDG 3</option>
<option value="SDG_4">SDG 4</option>
<option value="SDG_5">SDG 5</option>
<option value="SDG_6">SDG 6</option>
<option value="SDG_7">SDG 7</option>
<option value="SDG_8">SDG 8</option>
<option value="SDG_9">SDG 9</option>
<option value="SDG_10">SDG 10</option>
<option value="SDG_11">SDG 11</option>
<option value="SDG_12">SDG 12</option>
<option value="SDG_13">SDG 13</option>
<option value="SDG_14">SDG 14</option>
<option value="SDG_15">SDG 15</option>
<option value="SDG_16">SDG 16</option>
<option value="SDG_17">SDG 17</option>
                            </select>
                        </div>
                        <div class="col-md-6" data-final-sdg>
                            <label class="form-label">Secondary Sustainable Development Goals</label>
                            <select class="form-select" name="secondary_sdgs" multiple size="6">
                                <option value="SDG_1">SDG 1</option>
<option value="SDG_2">SDG 2</option>
<option value="SDG_3">SDG 3</option>
<option value="SDG_4">SDG 4</option>
<option value="SDG_5">SDG 5</option>
<option value="SDG_6">SDG 6</option>
<option value="SDG_7">SDG 7</option>
<option value="SDG_8">SDG 8</option>
<option value="SDG_9">SDG 9</option>
<option value="SDG_10">SDG 10</option>
<option value="SDG_11">SDG 11</option>
<option value="SDG_12">SDG 12</option>
<option value="SDG_13">SDG 13</option>
<option value="SDG_14">SDG 14</option>
<option value="SDG_15">SDG 15</option>
<option value="SDG_16">SDG 16</option>
<option value="SDG_17">SDG 17</option>
                            </select>
                        </div>
                    </div>
                </section>

                <section class="initiative-final-section d-none" data-final-step="4">
                    <header class="initiative-final-section-heading">
                        <div>
                            <p class="eyebrow mb-2">Section 5 of 5</p>
                            <h2>Documentation &amp; Notes</h2>
                            <p>
                                Publication, media coverage, evidence, supporting files,
                                sharing permission, notes, and final confirmation.
                            </p>
                        </div>
                    </header>

                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label">Media Support Requested</label>
                            <select class="form-select" name="needs_media_support">
                                <option value="">Not specified</option>
                                <option value="true">Yes</option>
                                <option value="false">No</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Publication Status</label>
                            <select class="form-select" name="publication_status">
                                <option value="NOT_PLANNED">Not published / not planned</option>
                                <option value="IN_PROGRESS">Publication in progress</option>
                                <option value="UOB">Published by UOB</option>
                                <option value="PARTNER">Published by a partner</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Media Coverage Type</label>
                            <select class="form-select" name="media_coverage_type">
                                <option value="">No media coverage</option>
                                <option value="NEWS">News article</option>
                                <option value="TV_INTERVIEW">TV interview</option>
                            </select>
                        </div>

                        <div class="col-12 d-none" data-final-news>
                            <div class="row g-4">
                                <div class="col-md-4">
                                    <label class="form-label">Publishing Platform / Outlet</label>
                                    <input class="form-control" name="media_outlet_name">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">News Headline</label>
                                    <input class="form-control" name="media_headline">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Publication Date</label>
                                    <input type="date" class="form-control" name="media_publication_date">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">News Link</label>
                                    <input type="url" class="form-control" name="news_link" placeholder="https://">
                                </div>
                            </div>
                        </div>

                        <div class="col-12 d-none" data-final-tv>
                            <div class="row g-4">
                                <div class="col-md-4"><label class="form-label">TV Channel</label><input class="form-control" name="tv_channel"></div>
                                <div class="col-md-4"><label class="form-label">TV Program</label><input class="form-control" name="tv_program"></div>
                                <div class="col-md-4"><label class="form-label">Interview Topic</label><input class="form-control" name="tv_interview_topic"></div>
                                <div class="col-md-4"><label class="form-label">Interviewer</label><input class="form-control" name="tv_interviewer"></div>
                                <div class="col-md-4"><label class="form-label">UOB Representatives</label><input class="form-control" name="tv_uob_representatives"></div>
                                <div class="col-md-4"><label class="form-label">Interview Date</label><input type="date" class="form-control" name="tv_interview_date"></div>
                                <div class="col-md-3"><label class="form-label">Duration Minutes</label><input type="number" min="0" class="form-control" name="tv_duration_minutes"></div>
                                <div class="col-md-3"><label class="form-label">Broadcast Status</label><select class="form-select" name="tv_broadcast_status"><option value="">Select</option><option value="SCHEDULED">Scheduled</option><option value="LIVE">Live</option><option value="AIRED">Aired</option></select></div>
                                <div class="col-md-3"><label class="form-label">Broadcast Scope</label><select class="form-select" name="tv_broadcast_scope"><option value="">Select</option><option value="LOCAL">Local</option><option value="REGIONAL">Regional</option><option value="INTERNATIONAL">International</option></select></div>
                                <div class="col-md-3"><label class="form-label">Interview Language</label><select class="form-select" name="tv_interview_language"><option value="">Select</option><option value="AR">Arabic</option><option value="EN">English</option><option value="BOTH">Arabic and English</option><option value="OTHER">Other</option></select></div>
                                <div class="col-12"><label class="form-label">Interview Viewing Link</label><input type="url" class="form-control" name="tv_interview_link"></div>
                                <div class="col-12"><label class="form-label">Main Topics and Messages</label><textarea class="form-control" name="tv_interview_highlights" rows="3"></textarea></div>
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Evidence Method</label>
                            <div class="initiative-final-check-grid">
                                <label><input type="checkbox" name="evidence_types" value="UPLOAD"> Upload file</label>
                                <label><input type="checkbox" name="evidence_types" value="URL"> Public URL</label>
                                <label><input type="checkbox" name="evidence_types" value="EXPLANATION"> Written explanation</label>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="initiative-final-subheading">
                                <div>
                                    <h3>Supporting Files</h3>
                                    <p>
                                        Request attachments are included automatically.
                                        Uncheck any file that should not be copied.
                                    </p>
                                </div>
                            </div>
                            <div class="initiative-final-attachments" data-final-request-attachments></div>
                            <div class="initiative-final-attachments mt-3" data-final-conversion-attachments></div>
                            <label class="initiative-final-upload mt-3">
                                <input
                                    type="file"
                                    multiple
                                    data-final-file-input
                                    accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.mp4,.mov"
                                >
                                <strong>Upload Supporting Files</strong>
                                <span>Up to 10 files in total, 20 MB each.</span>
                            </label>
                            <div class="initiative-final-queued-files" data-final-queued-files></div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Evidence Document Type</label>
                            <input class="form-control" name="evidence_document_type">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Evidence Date</label>
                            <input type="date" class="form-control" name="evidence_date">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Evidence Owner</label>
                            <input class="form-control" name="evidence_owner">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Evidence Public Access</label>
                            <select class="form-select" name="evidence_public_access">
                                <option value="">Select</option>
                                <option value="YES">Public</option>
                                <option value="NO">Restricted</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">UOB May Use and Publish the Evidence</label>
                            <select class="form-select" name="public_sharing">
                                <option value="">Select</option>
                                <option value="YES">Yes</option>
                                <option value="NO">No</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">News or Public Evidence Links</label>
                            <textarea class="form-control" name="evidence_urls_text" rows="3" placeholder="One URL per line"></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Evidence Explanation</label>
                            <textarea class="form-control" name="evidence_explanation" rows="3"></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Additional Notes</label>
                            <textarea class="form-control" name="notes_entity" rows="4"></textarea>
                        </div>

                        <div class="col-12">
                            <label class="initiative-final-declaration">
                                <input type="checkbox" name="declaration_confirmed" required>
                                <span>
                                    I confirm that the final Initiative information is accurate
                                    and may be saved as the official University record.
                                </span>
                            </label>
                        </div>

                        <div class="col-12">
                            <section class="initiative-final-review" data-final-review>
                                <p class="eyebrow mb-2">Final review</p>
                                <h3 data-final-review-title>Initiative</h3>
                                <dl>
                                    <div><dt>Type</dt><dd data-final-review-type>—</dd></div>
                                    <div><dt>Dates</dt><dd data-final-review-dates>—</dd></div>
                                    <div><dt>Target groups</dt><dd data-final-review-targets>—</dd></div>
                                    <div><dt>SDGs</dt><dd data-final-review-sdgs>—</dd></div>
                                </dl>
                            </section>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <div class="initiative-final-actions">
            <button class="btn btn-outline-secondary" type="button" data-final-previous>
                Previous
            </button>
            <button class="btn btn-outline-primary" type="button" data-final-next>
                Next section
            </button>
            <div class="ms-auto d-flex gap-2">
                <button class="btn btn-outline-secondary" type="button" data-final-save>
                    <?= $isExistingFinalForm ? 'Save draft' : 'Save conversion draft' ?>
                </button>
                <button class="btn btn-primary d-none" type="submit" data-finalize>
                    <?= $isExistingFinalForm ? 'Register Initiative' : 'Complete conversion' ?>
                </button>
            </div>
        </div>
    </form>
</div>

<template data-final-person-template>
    <article class="initiative-final-person" data-final-person>
        <div class="initiative-final-person-head">
            <strong data-final-person-number>Person</strong>
            <button class="btn btn-sm btn-outline-danger" type="button" data-remove-final-person>Remove</button>
        </div>
        <input type="hidden" data-person-field="user_id">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Name</label>
                <input class="form-control" data-person-field="name">
            </div>
            <div class="col-md-4">
                <label class="form-label">Email</label>
                <input type="email" class="form-control" data-person-field="email">
            </div>
            <div class="col-md-4">
                <label class="form-label">Mobile</label>
                <input class="form-control" data-person-field="mobile">
            </div>
            <div class="col-md-5">
                <label class="form-label">Role</label>
                <input class="form-control" data-person-field="role">
            </div>
            <div class="col-md-3">
                <label class="initiative-final-inline-check">
                    <input type="checkbox" data-person-field="is_primary">
                    Primary responsible
                </label>
            </div>
            <div class="col-md-4">
                <label class="initiative-final-inline-check">
                    <input type="checkbox" data-person-field="is_coordinator">
                    Initiative coordinator
                </label>
            </div>
        </div>
    </article>
</template>

<?php else: ?>
<div class="mb-4">
    <a href="initiative-workflow.php" class="back-link">← Back to Initiative requests</a>
</div>

<div class="alert alert-danger d-none" role="alert" data-module-alert></div>
<div class="loading-state" data-loading>
    <div class="spinner-border text-primary" aria-hidden="true"></div>
    <span>Loading Initiative request…</span>
</div>

<div class="d-none" data-detail-content>
    <section class="page-heading d-flex flex-wrap justify-content-between gap-3">
        <div>
            <p class="eyebrow mb-2" data-request-code>Initiative request</p>
            <h1 class="display-6 mb-2" data-request-title></h1>
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <span data-request-status></span>
                <span class="text-secondary small" data-request-owner></span>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-start" data-request-actions></div>
    </section>

    <section class="workspace-card mt-4 d-none initiative-decision-card" data-decision-panel>
        <div class="workspace-card-header">
            <div>
                <p class="eyebrow mb-2">Action required</p>
                <h2 class="h5 mb-1">Review this Initiative request</h2>
                <p class="small text-secondary mb-0" data-decision-context>
                    You are the assigned reviewer for <strong data-decision-stage>the current stage</strong>.
                </p>
            </div>
            <span class="initiative-decision-badge">Your decision</span>
        </div>
        <form class="form-section initiative-decision-form" data-decision-form novalidate>
            <label class="form-label" for="initiative-decision-comment">Decision note</label>
            <textarea
                id="initiative-decision-comment"
                class="form-control"
                rows="3"
                maxlength="2000"
                placeholder="Optional for approval. Required for revision or rejection."
                data-decision-comment
            ></textarea>
            <div class="form-text" data-decision-help>
                Revision and rejection require a clear reason of at least 10 characters.
            </div>
            <div class="initiative-decision-actions mt-3">
                <button class="btn btn-success" type="button" data-decision-action="APPROVE">
                    Approve
                </button>
                <button class="btn btn-outline-warning" type="button" data-decision-action="REQUEST_REVISION">
                    Request revision
                </button>
                <button class="btn btn-outline-danger" type="button" data-decision-action="REJECT">
                    Reject
                </button>
            </div>
        </form>
    </section>

    <section
        class="workspace-card mt-4 d-none initiative-admin-skip-card"
        data-admin-skip-panel
    >
        <div class="workspace-card-header">
            <div>
                <p class="eyebrow mb-2">Administrative action</p>
                <h2 class="h5 mb-1">Skip current approval stage</h2>
                <p class="small text-secondary mb-0">
                    The current reviewer and request participants will be notified, and the next stage will start immediately.
                </p>
            </div>
            <span class="initiative-admin-skip-badge">System Administrator</span>
        </div>
        <form class="form-section" data-admin-skip-form novalidate>
            <div class="initiative-admin-skip-warning mb-3">
                Current stage:
                <strong data-admin-skip-stage>Approval stage</strong>
            </div>
            <label class="form-label" for="initiative-admin-skip-reason">
                Mandatory skip reason
            </label>
            <textarea
                id="initiative-admin-skip-reason"
                class="form-control"
                rows="3"
                maxlength="2000"
                placeholder="Explain why this approval stage must be bypassed."
                data-admin-skip-reason
                required
            ></textarea>
            <div class="form-text">
                Enter a clear reason of at least 10 characters. It will be stored in the timeline and sent to the skipped approver.
            </div>
            <div class="mt-3">
                <button
                    class="btn btn-outline-danger"
                    type="submit"
                    data-admin-skip-submit
                >
                    Skip stage and continue
                </button>
            </div>
        </form>
    </section>

    <section class="workspace-card mt-4">
        <div class="workspace-card-header">
            <div>
                <h2 class="h5 mb-1">Approval progress</h2>
                <p class="small text-secondary mb-0">
                    Each approval cycle remains separate while decisions, revisions, skips, and reminders stay visible.
                </p>
            </div>
            <span class="initiative-waiting-chip" data-current-waiting></span>
        </div>
        <div class="form-section">
            <div class="workflow-timeline initiative-long-timeline" data-timeline></div>
        </div>
    </section>

    <section
        id="revision-discussions"
        class="workspace-card mt-4 d-none initiative-revision-discussions-card"
        data-revision-discussions
    >
        <div class="workspace-card-header">
            <div>
                <p class="eyebrow mb-2">Revisions and discussion</p>
                <h2 class="h5 mb-1">Revision discussion</h2>
                <p class="small text-secondary mb-0">
                    Public notes are visible to everyone who can view the request. Private notes are limited to the requester, the involved approval stages, and System Administrators.
                </p>
            </div>
            <span class="initiative-discussion-count" data-revision-thread-count></span>
        </div>
        <div class="form-section">
            <div class="initiative-revision-thread-list" data-revision-thread-list></div>
        </div>
    </section>

    <div class="row g-4 mt-1">
        <div class="col-lg-8">
            <section class="workspace-card mb-4">
                <div class="workspace-card-header">
                    <div>
                        <h2 class="h5 mb-1">Requester and affiliation</h2>
                        <p class="small text-secondary mb-0">
                            Snapshot captured from the requester’s University account when the request was created.
                        </p>
                    </div>
                </div>
                <div class="form-section">
                    <dl class="initiative-detail-grid">
                        <div><dt>Requester Category</dt><dd data-requester-type></dd></div>
                        <div><dt>Mobile number</dt><dd data-requester-mobile></dd></div>
                        <div><dt>Position</dt><dd data-requester-position></dd></div>
                        <div><dt>College or Organizational Unit</dt><dd data-requester-entity></dd></div>
                        <div><dt>Department, Directorate or Unit</dt><dd data-requester-department></dd></div>
                        <div><dt>Email</dt><dd data-requester-email></dd></div>
                    </dl>
                </div>
            </section>

            <section class="workspace-card mb-4">
                <div class="workspace-card-header">
                    <div>
                        <h2 class="h5 mb-1">Initiative information</h2>
                        <p class="small text-secondary mb-0">
                            Idea, classifications, objectives, impact, and audience.
                        </p>
                    </div>
                </div>
                <div class="form-section">
                    <dl class="initiative-detail-grid">
                        <div class="initiative-detail-wide"><dt>Initiative or Activity types</dt><dd data-secondary-types></dd></div>
                        <div class="initiative-detail-wide"><dt>Description</dt><dd data-description></dd></div>
                        <div class="initiative-detail-wide"><dt>Objectives</dt><dd data-objective></dd></div>
                        <div><dt>Expected Results or Impact</dt><dd data-impact></dd></div>
                        <div><dt>Beneficiaries Summary</dt><dd data-beneficiaries></dd></div>
                        <div><dt>Target audience</dt><dd data-target-groups></dd></div>
                        <div><dt>Expected participants</dt><dd data-expected-participants></dd></div>
                    </dl>
                </div>
            </section>

            <section class="workspace-card mb-4">
                <div class="workspace-card-header">
                    <div>
                        <h2 class="h5 mb-1">Implementation and partners</h2>
                        <p class="small text-secondary mb-0">
                            Schedule, venue, Agreement context, and external partner participation.
                        </p>
                    </div>
                </div>
                <div class="form-section">
                    <dl class="initiative-detail-grid">
                        <div><dt>Proposed dates</dt><dd data-proposed-dates></dd></div>
                        <div><dt>Location and Delivery Mode</dt><dd data-implementation-scope></dd></div>
                        <div>
                            <dt>Proposed Venue or Location</dt>
                            <dd class="initiative-venue-detail">
                                <strong
                                    class="initiative-venue-name d-none"
                                    data-proposed-venue-name
                                ></strong>
                                <span data-proposed-venue></span>
                                <a
                                    class="initiative-map-link d-none"
                                    href="#"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    data-proposed-venue-map-link
                                >
                                    Open location in Google Maps
                                </a>
                            </dd>
                        </div>
                        <div><dt>Country</dt><dd data-implementation-country></dd></div>
                        <div><dt>Online Platform Name</dt><dd data-online-platform-name></dd></div>
                        <div><dt>Related Agreement</dt><dd data-related-agreement></dd></div>
                        <div><dt>External partner involved</dt><dd data-has-external-partner></dd></div>
                        <div><dt>External partner name</dt><dd data-external-partner-name></dd></div>
                        <div><dt>External partner role</dt><dd data-external-partner-role></dd></div>
                    </dl>
                </div>
            </section>

            <section class="workspace-card mb-4">
                <div class="workspace-card-header">
                    <div>
                        <h2 class="h5 mb-1">Requirements and sustainability</h2>
                        <p class="small text-secondary mb-0">
                            Resources, estimated budget, media support, SDGs, and declaration.
                        </p>
                    </div>
                </div>
                <div class="form-section">
                    <dl class="initiative-detail-grid">
                        <div><dt>Required resources</dt><dd data-required-resources></dd></div>
                        <div><dt>Estimated budget</dt><dd data-estimated-budget></dd></div>
                        <div><dt>Media support</dt><dd data-needs-media></dd></div>
                        <div><dt>Supports SDGs</dt><dd data-supports-sdg></dd></div>
                        <div class="initiative-detail-wide"><dt>Relevant SDGs</dt><dd data-sdg-goals></dd></div>
                        <div class="initiative-detail-wide"><dt>Declaration</dt><dd data-declaration></dd></div>
                    </dl>
                </div>
            </section>

            <section class="workspace-card">
                <div class="workspace-card-header">
                    <div>
                        <h2 class="h5 mb-1">Supporting attachments</h2>
                        <p class="small text-secondary mb-0">
                            Files attached while the request was a draft or under revision.
                        </p>
                    </div>
                </div>
                <div class="form-section">
                    <div
                        class="initiative-detail-attachments"
                        data-detail-attachments
                    ></div>
                </div>
            </section>
        </div>

        <div class="col-lg-4">
            <section class="workspace-card mb-4">
                <div class="workspace-card-header">
                    <h2 class="h5 mb-0">Collaborators</h2>
                </div>
                <div class="form-section">
                    <div
                        class="initiative-member-list"
                        data-members
                    ></div>
                </div>
            </section>
            <section class="workspace-card">
                <div class="workspace-card-header">
                    <h2 class="h5 mb-0">Recent activity</h2>
                </div>
                <div class="form-section">
                    <div
                        class="initiative-event-list"
                        data-events
                    ></div>
                </div>
            </section>
        </div>
    </div>
</div>
<?php endif; ?>
