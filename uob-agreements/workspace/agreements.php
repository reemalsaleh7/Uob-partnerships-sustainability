<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

workspaceHeader('Agreements', 'agreements');
?>
<style>
/* AGREEMENTS TABLE - CLEAN */

.workspace-card{
    overflow:hidden !important;
}

.table-responsive{
    overflow-x:auto !important;
}

table{
    width:100% !important;
    border-collapse:collapse !important;
}

table th,
table td{
    padding:14px 16px !important;
    vertical-align:middle !important;
    border-bottom:1px solid #e7edf2 !important;
}

table th{
    background:#f7f9fb !important;
    color:#425a72 !important;
    font-size:10px !important;
    font-weight:900 !important;
    text-transform:uppercase !important;
}

table td{
    color:#173650 !important;
    font-size:11px !important;
    line-height:1.45 !important;
}

table td:nth-child(2){
    min-width:220px;
    font-weight:900;
    color:#0b3157 !important;
}

table td:nth-child(3){
    min-width:170px;
}

table td:nth-child(4){
    min-width:90px;
}

table td:nth-child(5){
    min-width:120px;
}

table td:nth-child(6){
    min-width:130px;
}

table td:nth-child(7){
    min-width:180px;
}

table td:nth-child(8){
    min-width:130px;
}

/* View column */
table th:last-child,
table td:last-child{
    transform:translateX(-1px);

    width:85px !important;
    min-width:85px !important;
    text-align:center !important;
}

/* View button */
table td:last-child a,
table td:last-child button{
    display:inline-flex !important;
    align-items:center !important;
    justify-content:center !important;

    min-width:58px;
    padding:7px 11px;

    background:#f6f9fc !important;
    color:#1765a4 !important;

    border:1px solid #d8e3ed !important;
    border-radius:8px !important;

    font-size:9px !important;
    font-weight:900 !important;

    text-decoration:none !important;
}

table tbody tr:hover{
    background:#fafcfd !important;
}
/* ترتيب أعمدة Status / Origin / Relationship */

table th:nth-child(4),
table td:nth-child(4),
table th:nth-child(5),
table td:nth-child(5),
table th:nth-child(6),
table td:nth-child(6){
    text-align:center !important;
}

/* نخلي محتوى الخلايا بالنص */
table td:nth-child(4),
table td:nth-child(5),
table td:nth-child(6){
    vertical-align:middle !important;
}

/* توحيد شكل الـ badges */
table td:nth-child(4) .badge,
table td:nth-child(5) .badge,
table td:nth-child(6) .badge,
table td:nth-child(4) span,
table td:nth-child(5) span,
table td:nth-child(6) span{
    display:inline-flex !important;
    align-items:center !important;
    justify-content:center !important;

    min-height:36px !important;

    padding:7px 12px !important;

    border-radius:999px !important;

    line-height:1.2 !important;
    text-align:center !important;
}

/* Status أصغر شوي */
table td:nth-child(4) .badge,
table td:nth-child(4) span{
    min-width:70px !important;
}

/* Origin */
table td:nth-child(5) .badge,
table td:nth-child(5) span{
    min-width:110px !important;
}

/* Relationship */
table td:nth-child(6) .badge,
table td:nth-child(6) span{
    min-width:110px !important;
}
</style>

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

    <div class="filter-bar">
        <div class="row g-3">
            <div class="col-lg-6">
                <label for="agreement-search" class="form-label">Advanced search</label>
                <input
                    id="agreement-search"
                    type="search"
                    class="form-control"
                    placeholder='Try partner:"Bahrain Polytechnic" status:active'
                    aria-describedby="agreement-search-help"
                    disabled
                >
                <div id="agreement-search-help" class="form-text">
                    Use several words, quoted phrases, <code>field:value</code>,
                    <code>a|b</code>, <code>-term</code>, or <code>updated&gt;=2026-01-01</code>.
                    Fields: title, code,
                    type, status, origin, partner, creator, unit, start, end, updated.
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <label for="agreement-status" class="form-label">Status</label>
                <select id="agreement-status" class="form-select" disabled>
                    <option value="">All statuses</option>
                </select>
            </div>
            <div class="col-md-6 col-lg-3">
                <label for="agreement-type" class="form-label">Agreement type</label>
                <select id="agreement-type" class="form-select" disabled>
                    <option value="">All types</option>
                </select>
            </div>
            <div class="col-md-6 col-lg-3">
                <label for="agreement-origin" class="form-label">Record origin</label>
                <select id="agreement-origin" class="form-select" disabled>
                    <option value="">All origins</option>
                    <option value="LEGACY_IMPORT">Legacy system — real records</option>
                    <option value="DEVELOPMENT">Development / demo</option>
                    <option value="NEW_SYSTEM">New system</option>
                </select>
            </div>
            <div class="col-md-6 col-lg-4">
                <label for="agreement-partner" class="form-label">Partner</label>
                <select id="agreement-partner" class="form-select" disabled>
                    <option value="">All partners</option>
                </select>
            </div>
            <div class="col-md-6 col-lg-3">
                <label for="agreement-updated-from" class="form-label">Updated from</label>
                <input id="agreement-updated-from" type="date" class="form-control" disabled>
            </div>
            <div class="col-md-6 col-lg-3">
                <label for="agreement-updated-to" class="form-label">Updated to</label>
                <input id="agreement-updated-to" type="date" class="form-control" disabled>
            </div>
            <div class="col-md-6 col-lg-2 d-flex align-items-end">
                <button class="btn btn-outline-secondary w-100" type="button" data-clear-agreement-filters disabled>
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
        <p class="text-secondary mb-0">Try changing the search or status filter.</p>
    </div>

    <div id="agreement-table-wrap" class="table-responsive d-none">
        <table class="table workspace-table align-middle mb-0">
            <thead>
                <tr>
                    <th scope="col">ID</th>
                    <th scope="col">Title</th>
                    <th scope="col">Type</th>
                    <th scope="col">Status</th>
                    <th scope="col">Origin</th>
                    <th scope="col">Relationship</th>
                    <th scope="col">Partner</th>
                    <th scope="col">Updated</th>
                    <th scope="col"><span class="visually-hidden">Actions</span></th>
                </tr>
            </thead>
            <tbody id="agreement-table-body"></tbody>
        </table>
    </div>
</section>

<?php workspaceFooter([
    'assets/js/advanced-search.js?v=20260802-advanced-search',
    'assets/js/agreements.js?v=20260802-advanced-search',
]); ?>
