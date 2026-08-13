<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

workspaceHeader('Agreements', 'agreements');
?>

<section class="page-heading d-flex flex-column flex-lg-row justify-content-between gap-3">
    <div>
        <p class="eyebrow mb-2">Partnership portfolio</p>
        <h1 class="display-6 mb-2">Agreements</h1>
        <p class="text-secondary mb-0" data-agreement-page-description>
            Explore active University partnerships and manage Agreements within your authority.
        </p>
    </div>

    <div class="align-self-lg-end">
        <a
            href="agreement-form.php"
            class="btn btn-primary d-none"
            data-create-agreement
        >
            Create Agreement
        </a>
    </div>
</section>

<section class="workspace-card mt-4" aria-labelledby="agreement-list-title">
    <div class="workspace-card-header">
        <div>
            <h2 id="agreement-list-title" class="h5 mb-1">Agreement register</h2>
            <p class="text-secondary small mb-0" data-result-summary>
                Loading Agreements…
            </p>
        </div>
    </div>

    <div class="agreement-scope-bar" aria-label="Agreement view" data-agreement-scopes>
        <button class="agreement-scope-button active" type="button" data-agreement-scope="ACTIVE">
            <strong data-scope-count="ACTIVE">0</strong>
            <span>Active Agreements</span>
            <small>University partnerships available now</small>
        </button>
        <button class="agreement-scope-button" type="button" data-agreement-scope="MY_ACTIVE">
            <strong data-scope-count="MY_ACTIVE">0</strong>
            <span>My active Agreements</span>
            <small>Active records created by you</small>
        </button>
        <button class="agreement-scope-button" type="button" data-agreement-scope="MINE">
            <strong data-scope-count="MINE">0</strong>
            <span>My Agreements</span>
            <small>Draft through operational delivery</small>
        </button>
        <button class="agreement-scope-button" type="button" data-agreement-scope="ALL">
            <strong data-scope-count="ALL">0</strong>
            <span>All visible</span>
            <small>Every record available to your role</small>
        </button>
    </div>

    <div class="agreement-discovery-note d-none" data-faculty-agreement-note>
        <div>
            <strong>Build an Initiative on an active partnership</strong>
            <p>Choose an active Agreement below, review its objectives, then use it as the partnership context for your Initiative request.</p>
        </div>
        <a class="btn btn-sm btn-outline-primary" href="initiative-hub.php">Initiative guidance</a>
    </div>

    <div class="filter-bar progressive-search" data-agreement-search-filters aria-label="Advanced search">
        <div class="progressive-search-primary">
            <div class="progressive-search-field">
                <label for="agreement-search" class="form-label">Search Agreements</label>
                <div class="progressive-search-input-wrap">
                    <span class="progressive-search-icon" aria-hidden="true"></span>
                    <input
                        id="agreement-search"
                        type="search"
                        class="form-control"
                        placeholder="Search by title, partner, description, or keyword"
                        autocomplete="off"
                        data-search-input
                        disabled
                    >
                </div>
            </div>
            <div class="progressive-search-action">
                <button
                    class="btn btn-outline-primary progressive-search-toggle"
                    type="button"
                    aria-expanded="false"
                    aria-controls="agreement-more-filters"
                    data-advanced-search-toggle
                >
                    <span>More filters</span>
                    <span class="progressive-search-count d-none" data-advanced-filter-count></span>
                    <span class="progressive-search-chevron" aria-hidden="true"></span>
                </button>
            </div>
        </div>

        <p class="progressive-search-summary d-none" data-advanced-filter-summary aria-live="polite"></p>

        <div id="agreement-more-filters" class="progressive-search-panel" data-advanced-search-panel hidden>
            <div class="progressive-search-panel-heading">
                <div>
                    <h3 class="h6 mb-1">Refine your results</h3>
                    <p class="small text-secondary mb-0">Choose only the filters you need. You can also add precise conditions below.</p>
                </div>
            </div>

            <div class="agreement-filter-groups">
                <fieldset class="agreement-filter-group">
                    <legend>
                        <span class="agreement-filter-group-number">1</span>
                        Agreement and partner information
                    </legend>
                    <p>Search the same core information collected in the Agreement form.</p>
                    <div class="row g-3">
                        <div class="col-md-6 col-xl-3">
                            <label for="agreement-title" class="form-label">Title contains</label>
                            <input id="agreement-title" type="text" class="form-control" placeholder="Enter part of the title" data-advanced-filter data-filter-label="title" disabled>
                        </div>
                        <div class="col-md-6 col-xl-3">
                            <label for="agreement-title-ar" class="form-label">Arabic title contains</label>
                            <input id="agreement-title-ar" type="text" class="form-control" dir="rtl" placeholder="أدخل جزءاً من العنوان" data-advanced-filter data-filter-label="Arabic title" disabled>
                        </div>
                        <div class="col-md-6 col-xl-3">
                            <label for="agreement-type" class="form-label">Agreement type</label>
                            <select id="agreement-type" class="form-select" data-advanced-filter data-filter-label="type" disabled>
                                <option value="">All types</option>
                            </select>
                        </div>
                        <div class="col-md-6 col-xl-3">
                            <label for="agreement-partner" class="form-label">Partner organization</label>
                            <select id="agreement-partner" class="form-select" data-advanced-filter data-filter-label="partner" disabled>
                                <option value="">All partners</option>
                            </select>
                        </div>
                        <div class="col-md-6 col-xl-3">
                            <label for="agreement-partner-type" class="form-label">Partner type</label>
                            <select id="agreement-partner-type" class="form-select" data-advanced-filter data-filter-label="partner type" disabled>
                                <option value="">All partner types</option>
                                <option value="PUBLIC_GOVERNMENT">Public / government</option>
                                <option value="PRIVATE">Private</option>
                                <option value="ACADEMIC">Academic</option>
                                <option value="NON_PROFIT">Non-profit</option>
                            </select>
                        </div>
                        <div class="col-md-6 col-xl-3">
                            <label for="agreement-partner-country" class="form-label">Partner country</label>
                            <select id="agreement-partner-country" class="form-select" data-advanced-filter data-filter-label="partner country" disabled>
                                <option value="">All countries</option>
                            </select>
                        </div>
                        <div class="col-md-6 col-xl-3">
                            <label for="agreement-description" class="form-label">Description contains</label>
                            <input id="agreement-description" type="text" class="form-control" placeholder="Enter words from the brief profile" data-advanced-filter data-filter-label="description" disabled>
                        </div>
                    </div>
                </fieldset>

                <fieldset class="agreement-filter-group">
                    <legend>
                        <span class="agreement-filter-group-number">2</span>
                        Purpose and expected impact
                    </legend>
                    <p>Search the narrative fields that explain why the Agreement exists and what it should achieve.</p>
                    <div class="row g-3">
                        <div class="col-md-6 col-xl-3">
                            <label for="agreement-need" class="form-label">Need / justification contains</label>
                            <input id="agreement-need" type="text" class="form-control" placeholder="Enter a word or phrase" data-advanced-filter data-filter-label="need and justification" disabled>
                        </div>
                        <div class="col-md-6 col-xl-3">
                            <label for="agreement-objectives" class="form-label">Objectives contain</label>
                            <input id="agreement-objectives" type="text" class="form-control" placeholder="Enter an objective" data-advanced-filter data-filter-label="objectives" disabled>
                        </div>
                        <div class="col-md-6 col-xl-3">
                            <label for="agreement-impact" class="form-label">Expected impact contains</label>
                            <input id="agreement-impact" type="text" class="form-control" placeholder="Enter an expected impact" data-advanced-filter data-filter-label="expected impact" disabled>
                        </div>
                        <div class="col-md-6 col-xl-3">
                            <label for="agreement-focus" class="form-label">Focus areas contain</label>
                            <input id="agreement-focus" type="text" class="form-control" placeholder="Research, training, exchange…" data-advanced-filter data-filter-label="focus areas" disabled>
                        </div>
                    </div>
                </fieldset>

                <fieldset class="agreement-filter-group">
                    <legend>
                        <span class="agreement-filter-group-number">3</span>
                        Dates and renewal terms
                    </legend>
                    <p>Use either side of a range. Leaving a date blank keeps that side open.</p>
                    <div class="agreement-date-filter-grid">
                        <div class="agreement-date-filter">
                            <span>Project starts</span>
                            <label for="agreement-start-from" class="visually-hidden">Project starts from</label>
                            <input id="agreement-start-from" type="date" class="form-control" title="Project starts from" data-advanced-filter data-filter-label="start date" disabled>
                            <span class="agreement-date-separator">to</span>
                            <label for="agreement-start-to" class="visually-hidden">Project starts to</label>
                            <input id="agreement-start-to" type="date" class="form-control" title="Project starts to" data-advanced-filter data-filter-label="start date" disabled>
                        </div>
                        <div class="agreement-date-filter">
                            <span>Project ends</span>
                            <label for="agreement-end-from" class="visually-hidden">Project ends from</label>
                            <input id="agreement-end-from" type="date" class="form-control" title="Project ends from" data-advanced-filter data-filter-label="end date" disabled>
                            <span class="agreement-date-separator">to</span>
                            <label for="agreement-end-to" class="visually-hidden">Project ends to</label>
                            <input id="agreement-end-to" type="date" class="form-control" title="Project ends to" data-advanced-filter data-filter-label="end date" disabled>
                        </div>
                    </div>
                    <div class="row g-3 mt-1">
                        <div class="col-md-6 col-xl-3">
                            <label for="agreement-fixed-term-min" class="form-label">Agreement term (months)</label>
                            <div class="input-group">
                                <input id="agreement-fixed-term-min" type="number" min="1" class="form-control" placeholder="Minimum" aria-label="Minimum fixed term months" data-advanced-filter data-filter-label="Agreement term" disabled>
                                <input id="agreement-fixed-term-max" type="number" min="1" class="form-control" placeholder="Maximum" aria-label="Maximum fixed term months" data-advanced-filter data-filter-label="Agreement term" disabled>
                            </div>
                        </div>
                        <div class="col-md-6 col-xl-3">
                            <label for="agreement-renewal-term-min" class="form-label">Renewal term (months)</label>
                            <div class="input-group">
                                <input id="agreement-renewal-term-min" type="number" min="1" class="form-control" placeholder="Minimum" aria-label="Minimum renewal term months" data-advanced-filter data-filter-label="renewal term" disabled>
                                <input id="agreement-renewal-term-max" type="number" min="1" class="form-control" placeholder="Maximum" aria-label="Maximum renewal term months" data-advanced-filter data-filter-label="renewal term" disabled>
                            </div>
                        </div>
                        <div class="col-md-6 col-xl-3">
                            <label for="agreement-notice-min" class="form-label">Non-renewal notice (months)</label>
                            <div class="input-group">
                                <input id="agreement-notice-min" type="number" min="0" class="form-control" placeholder="Minimum" aria-label="Minimum non-renewal notice months" data-advanced-filter data-filter-label="non-renewal notice" disabled>
                                <input id="agreement-notice-max" type="number" min="0" class="form-control" placeholder="Maximum" aria-label="Maximum non-renewal notice months" data-advanced-filter data-filter-label="non-renewal notice" disabled>
                            </div>
                        </div>
                        <div class="col-md-6 col-xl-3">
                            <label for="agreement-auto-renew" class="form-label">Automatic renewal</label>
                            <select id="agreement-auto-renew" class="form-select" data-advanced-filter data-filter-label="automatic renewal" disabled>
                                <option value="">Any</option>
                                <option value="YES">Enabled</option>
                                <option value="NO">Not enabled</option>
                            </select>
                        </div>
                    </div>
                </fieldset>

                <fieldset class="agreement-filter-group">
                    <legend>
                        <span class="agreement-filter-group-number">4</span>
                        Resources, reporting, and SDGs
                    </legend>
                    <p>Find Agreements with specific commitments, reporting, or renewal requirements.</p>
                    <div class="row g-3">
                        <div class="col-md-6 col-xl-3">
                            <label for="agreement-financial" class="form-label">Financial commitments</label>
                            <select id="agreement-financial" class="form-select" data-advanced-filter data-filter-label="financial commitments" disabled>
                                <option value="">Any</option>
                                <option value="YES">Required</option>
                                <option value="NO">Not required</option>
                            </select>
                        </div>
                        <div class="col-md-6 col-xl-3">
                            <label for="agreement-annual-report" class="form-label">Annual report</label>
                            <select id="agreement-annual-report" class="form-select" data-advanced-filter data-filter-label="annual report" disabled>
                                <option value="">Any</option>
                                <option value="YES">Required</option>
                                <option value="NO">Not required</option>
                            </select>
                        </div>
                        <div class="col-md-6 col-xl-3">
                            <label for="agreement-human-resources" class="form-label">Human-resources commitments</label>
                            <select id="agreement-human-resources" class="form-select" data-advanced-filter data-filter-label="human-resources commitments" disabled>
                                <option value="">Any</option>
                                <option value="YES">Required</option>
                                <option value="NO">Not required</option>
                            </select>
                        </div>
                        <div class="col-md-6 col-xl-3">
                            <label for="agreement-training" class="form-label">Training programmes</label>
                            <select id="agreement-training" class="form-select" data-advanced-filter data-filter-label="training programmes" disabled>
                                <option value="">Any</option>
                                <option value="YES">Included</option>
                                <option value="NO">Not included</option>
                            </select>
                        </div>
                        <div class="col-md-6 col-xl-3">
                            <label for="agreement-financial-min" class="form-label">Financial amount</label>
                            <div class="input-group">
                                <input id="agreement-financial-min" type="number" min="0" step="0.01" class="form-control" placeholder="Minimum" aria-label="Minimum financial amount" data-advanced-filter data-filter-label="financial amount" disabled>
                                <input id="agreement-financial-max" type="number" min="0" step="0.01" class="form-control" placeholder="Maximum" aria-label="Maximum financial amount" data-advanced-filter data-filter-label="financial amount" disabled>
                            </div>
                        </div>
                        <div class="col-md-6 col-xl-3">
                            <label for="agreement-currency" class="form-label">Currency</label>
                            <select id="agreement-currency" class="form-select" data-advanced-filter data-filter-label="currency" disabled>
                                <option value="">All currencies</option>
                            </select>
                        </div>
                        <div class="col-md-6 col-xl-3">
                            <label for="agreement-sdg" class="form-label">Sustainable Development Goal</label>
                            <select id="agreement-sdg" class="form-select" data-advanced-filter data-filter-label="SDG" disabled>
                                <option value="">All SDGs</option>
                                <?php for ($sdg = 1; $sdg <= 17; $sdg++): ?>
                                    <option value="<?= $sdg ?>">SDG <?= $sdg ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                </fieldset>
            </div>

            <div class="search-rule-builder" data-search-rule-builder>
                <div class="search-rule-builder-header">
                    <div>
                        <h4 class="h6 mb-1">Build a precise search</h4>
                        <p class="small text-secondary mb-0">Add conditions in plain language—no search commands required.</p>
                    </div>
                    <label class="search-match-mode d-none" data-search-match-mode-wrap>
                        <span>Match</span>
                        <select class="form-select form-select-sm" data-search-match-mode>
                            <option value="all">all conditions</option>
                            <option value="any">any condition</option>
                        </select>
                    </label>
                </div>
                <div class="search-rule-list" data-search-rule-list></div>
                <button class="btn btn-sm btn-outline-primary" type="button" data-add-search-rule>
                    <span aria-hidden="true">+</span> Add condition
                </button>
            </div>

            <div class="progressive-search-footer">
                <button class="btn btn-link text-secondary p-0" type="button" data-clear-agreement-filters disabled>
                    Clear filters
                </button>
            </div>
        </div>
    </div>

    <div
        id="agreement-alert"
        class="alert alert-danger m-3 d-none"
        role="alert"
        aria-live="polite"
    ></div>

    <div id="agreement-loading" class="loading-state" aria-live="polite">
        <div class="spinner-border text-primary" aria-hidden="true"></div>
        <span>Loading Agreements…</span>
    </div>

    <div id="agreement-empty" class="empty-state d-none">
        <h3 class="h5">No Agreements found</h3>
        <p class="text-secondary mb-0">Try changing the search or one of the selected filters.</p>
    </div>

    <div id="agreement-table-wrap" class="table-responsive agreement-register-wrap d-none">
        <table class="table workspace-table agreement-register-table align-middle mb-0">
            <thead>
                <tr>
                    <th scope="col">Agreement</th>
                    <th scope="col">Partner organization</th>
                    <th scope="col">Project type</th>
                    <th scope="col">Project period</th>
                    <th scope="col"><span class="visually-hidden">Actions</span></th>
                </tr>
            </thead>
            <tbody id="agreement-table-body"></tbody>
        </table>
    </div>
</section>

<?php workspaceFooter([
    'assets/js/advanced-search.js',
    'assets/js/agreements.js',
]); ?>
