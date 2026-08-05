<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

$language = workspaceResolveLanguage();
$isArabic = $language === 'ar';
$text = static fn (string $english, string $arabic): string =>
    $isArabic ? $arabic : $english;

workspaceHeader(
    $text('User management', 'إدارة المستخدمين'),
    'admin-users',
    ['assets/css/admin-users.css']
);
?>

<section class="page-heading admin-users-heading">
    <div>
        <p class="eyebrow mb-2">
            <?= htmlspecialchars($text('Administration', 'الإدارة'), ENT_QUOTES, 'UTF-8') ?>
        </p>
        <h1 class="display-6 mb-2">
            <?= htmlspecialchars($text('User management', 'إدارة المستخدمين'), ENT_QUOTES, 'UTF-8') ?>
        </h1>
        <p class="text-secondary mb-0">
            <?= htmlspecialchars(
                $text(
                    'Manage identity data, workflow authority, creation access, and organizational assignments from one audited workspace.',
                    'إدارة بيانات الهوية وصلاحيات سير العمل وإمكانية إنشاء الاتفاقيات والمبادرات والتكليفات التنظيمية من مساحة واحدة موثقة.'
                ),
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </p>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap">

        <a class="btn btn-outline-primary" href="admin-workflows.php">

            <?= htmlspecialchars($text('Workflow templates', 'قوالب سير العمل'), ENT_QUOTES, 'UTF-8') ?>

        </a>

        <div class="admin-heading-badge" aria-label="Protected administrator area">

            <span aria-hidden="true">🔐</span>

            <strong><?= htmlspecialchars($text('Admin only', 'للإدارة فقط'), ENT_QUOTES, 'UTF-8') ?></strong>

        </div>

    </div>
</section>

<div class="alert alert-danger mt-4 d-none" role="alert" tabindex="-1" data-admin-users-alert></div>
<div class="alert alert-success mt-4 d-none" role="status" tabindex="-1" data-admin-users-success></div>

<section class="workspace-card admin-users-controls mt-4" aria-labelledby="admin-user-search-title">
    <div class="workspace-card-header align-items-start">
        <div>
            <h2 class="h5 mb-1" id="admin-user-search-title">
                <?= htmlspecialchars($text('Find a user', 'البحث عن مستخدم'), ENT_QUOTES, 'UTF-8') ?>
            </h2>
            <p class="small text-secondary mb-0">
                <?= htmlspecialchars(
                    $text(
                        'Search by name, University ID, email, phone, or filter by organizational unit.',
                        'ابحث بالاسم أو الرقم الجامعي أو البريد الإلكتروني أو الهاتف، أو صفِّ حسب الوحدة التنظيمية.'
                    ),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </p>
        </div>
        <button class="btn btn-outline-primary" type="button" data-admin-users-refresh>
            <?= htmlspecialchars($text('Refresh', 'تحديث'), ENT_QUOTES, 'UTF-8') ?>
        </button>
    </div>

    <form class="row g-3 mt-1" data-admin-users-filters>
        <div class="col-lg-5">
            <label class="form-label" for="admin-user-search">
                <?= htmlspecialchars($text('Search', 'البحث'), ENT_QUOTES, 'UTF-8') ?>
            </label>
            <input
                class="form-control"
                id="admin-user-search"
                type="search"
                autocomplete="off"
                maxlength="150"
                placeholder="<?= htmlspecialchars($text('Name, ID, email, or phone', 'الاسم أو الرقم أو البريد أو الهاتف'), ENT_QUOTES, 'UTF-8') ?>"
                data-admin-user-search
            >
        </div>
        <div class="col-md-4 col-lg-3">
            <label class="form-label" for="admin-user-status">
                <?= htmlspecialchars($text('Account status', 'حالة الحساب'), ENT_QUOTES, 'UTF-8') ?>
            </label>
            <select class="form-select" id="admin-user-status" data-admin-user-status>
                <option value=""><?= htmlspecialchars($text('All accounts', 'جميع الحسابات'), ENT_QUOTES, 'UTF-8') ?></option>
                <option value="true"><?= htmlspecialchars($text('Active only', 'الحسابات النشطة'), ENT_QUOTES, 'UTF-8') ?></option>
                <option value="false"><?= htmlspecialchars($text('Inactive only', 'الحسابات غير النشطة'), ENT_QUOTES, 'UTF-8') ?></option>
            </select>
        </div>
        <div class="col-md-8 col-lg-4">
            <label class="form-label" for="admin-user-unit-filter">
                <?= htmlspecialchars($text('Organizational unit', 'الوحدة التنظيمية'), ENT_QUOTES, 'UTF-8') ?>
            </label>
            <select class="form-select" id="admin-user-unit-filter" data-admin-user-unit-filter>
                <option value=""><?= htmlspecialchars($text('All units', 'جميع الوحدات'), ENT_QUOTES, 'UTF-8') ?></option>
            </select>
        </div>
    </form>
</section>

<div class="admin-users-layout mt-4">
    <section class="workspace-card admin-users-list-card" aria-labelledby="admin-users-list-title">
        <div class="workspace-card-header">
            <div>
                <h2 class="h5 mb-1" id="admin-users-list-title">
                    <?= htmlspecialchars($text('Users', 'المستخدمون'), ENT_QUOTES, 'UTF-8') ?>
                </h2>
                <p class="small text-secondary mb-0" data-admin-users-count></p>
            </div>
        </div>

        <div class="loading-state py-5" data-admin-users-loading>
            <div class="spinner-border text-primary" aria-hidden="true"></div>
            <span><?= htmlspecialchars($text('Loading users…', 'جارٍ تحميل المستخدمين…'), ENT_QUOTES, 'UTF-8') ?></span>
        </div>

        <div class="admin-users-empty d-none" data-admin-users-empty>
            <span aria-hidden="true">👤</span>
            <h3 class="h6 mb-1"><?= htmlspecialchars($text('No users found', 'لم يتم العثور على مستخدمين'), ENT_QUOTES, 'UTF-8') ?></h3>
            <p class="text-secondary mb-0"><?= htmlspecialchars($text('Change the search or filters and try again.', 'غيّر البحث أو عوامل التصفية وحاول مرة أخرى.'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>

        <div class="table-responsive d-none" data-admin-users-table-wrap>
            <table class="table align-middle admin-users-table mb-0">
                <thead>
                    <tr>
                        <th><?= htmlspecialchars($text('User', 'المستخدم'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars($text('Access', 'الصلاحيات'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars($text('Organization', 'الجهة التنظيمية'), ENT_QUOTES, 'UTF-8') ?></th>
                        <th><?= htmlspecialchars($text('Status', 'الحالة'), ENT_QUOTES, 'UTF-8') ?></th>
                    </tr>
                </thead>
                <tbody data-admin-users-body></tbody>
            </table>
        </div>

        <div class="admin-users-pagination d-none" data-admin-users-pagination>
            <button class="btn btn-outline-secondary btn-sm" type="button" data-admin-users-prev>
                <?= htmlspecialchars($text('Previous', 'السابق'), ENT_QUOTES, 'UTF-8') ?>
            </button>
            <span data-admin-users-page></span>
            <button class="btn btn-outline-secondary btn-sm" type="button" data-admin-users-next>
                <?= htmlspecialchars($text('Next', 'التالي'), ENT_QUOTES, 'UTF-8') ?>
            </button>
        </div>
    </section>

    <section class="workspace-card admin-user-editor" aria-labelledby="admin-user-editor-title">
        <div class="admin-user-placeholder" data-admin-user-placeholder>
            <span aria-hidden="true">🪪</span>
            <h2 class="h5 mb-2" id="admin-user-editor-title">
                <?= htmlspecialchars($text('Select a user', 'اختر مستخدمًا'), ENT_QUOTES, 'UTF-8') ?>
            </h2>
            <p class="text-secondary mb-0">
                <?= htmlspecialchars(
                    $text(
                        'Choose a user from the list to edit identity, access, workflow roles, and organizational assignment.',
                        'اختر مستخدمًا من القائمة لتعديل بياناته وصلاحياته وأدواره في سير العمل وتكليفه التنظيمي.'
                    ),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </p>
        </div>

        <div class="loading-state py-5 d-none" data-admin-user-detail-loading>
            <div class="spinner-border text-primary" aria-hidden="true"></div>
            <span><?= htmlspecialchars($text('Loading user…', 'جارٍ تحميل بيانات المستخدم…'), ENT_QUOTES, 'UTF-8') ?></span>
        </div>

        <form class="d-none" data-admin-user-form novalidate>
            <div class="admin-user-editor-header">
                <div class="admin-user-avatar" data-admin-user-avatar>U</div>
                <div>
                    <p class="eyebrow mb-1"><?= htmlspecialchars($text('Selected account', 'الحساب المحدد'), ENT_QUOTES, 'UTF-8') ?></p>
                    <h2 class="h4 mb-1" data-admin-user-name></h2>
                    <p class="text-secondary mb-0" data-admin-user-context></p>
                </div>
                <label class="admin-account-toggle ms-auto">
                    <input type="checkbox" class="form-check-input" data-admin-user-active>
                    <span><?= htmlspecialchars($text('Active account', 'حساب نشط'), ENT_QUOTES, 'UTF-8') ?></span>
                </label>
            </div>

            <div class="admin-access-summary" data-admin-access-summary></div>

            <nav class="admin-editor-tabs" aria-label="User editor sections">
                <button class="is-active" type="button" data-admin-tab="identity">
                    <?= htmlspecialchars($text('Identity', 'البيانات'), ENT_QUOTES, 'UTF-8') ?>
                </button>
                <button type="button" data-admin-tab="access">
                    <?= htmlspecialchars($text('Access', 'الصلاحيات'), ENT_QUOTES, 'UTF-8') ?>
                </button>
                <button type="button" data-admin-tab="workflow">
                    <?= htmlspecialchars($text('Workflow position', 'الموقع في سير العمل'), ENT_QUOTES, 'UTF-8') ?>
                </button>
                <button type="button" data-admin-tab="organization">
                    <?= htmlspecialchars($text('Organization', 'الجهة التنظيمية'), ENT_QUOTES, 'UTF-8') ?>
                </button>
                <button type="button" data-admin-tab="history">
                    <?= htmlspecialchars($text('History', 'السجل'), ENT_QUOTES, 'UTF-8') ?>
                </button>
            </nav>

            <div class="admin-editor-panel" data-admin-panel="identity">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="admin-university-id"><?= htmlspecialchars($text('University ID', 'الرقم الجامعي/الوظيفي'), ENT_QUOTES, 'UTF-8') ?></label>
                        <input class="form-control" id="admin-university-id" maxlength="30" required data-admin-field="university_id">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="admin-user-email"><?= htmlspecialchars($text('Email', 'البريد الإلكتروني'), ENT_QUOTES, 'UTF-8') ?></label>
                        <input class="form-control" id="admin-user-email" type="email" maxlength="255" required data-admin-field="email">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="admin-first-name"><?= htmlspecialchars($text('First name', 'الاسم الأول'), ENT_QUOTES, 'UTF-8') ?></label>
                        <input class="form-control" id="admin-first-name" maxlength="100" required data-admin-field="first_name">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="admin-last-name"><?= htmlspecialchars($text('Last name', 'اسم العائلة'), ENT_QUOTES, 'UTF-8') ?></label>
                        <input class="form-control" id="admin-last-name" maxlength="100" required data-admin-field="last_name">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="admin-phone"><?= htmlspecialchars($text('Phone', 'رقم الهاتف'), ENT_QUOTES, 'UTF-8') ?></label>
                        <input class="form-control" id="admin-phone" maxlength="30" data-admin-field="phone">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><?= htmlspecialchars($text('Last sign-in', 'آخر تسجيل دخول'), ENT_QUOTES, 'UTF-8') ?></label>
                        <div class="form-control admin-readonly-value" data-admin-readonly="last_login">—</div>
                    </div>
                </div>
            </div>

            <div class="admin-editor-panel d-none" data-admin-panel="access">
                <div class="admin-role-explainer">
                    <strong><?= htmlspecialchars($text('How access works', 'طريقة عمل الصلاحيات'), ENT_QUOTES, 'UTF-8') ?></strong>
                    <p class="mb-0">
                        <?= htmlspecialchars(
                            $text(
                                'Creation access comes from Creator roles. Approval authority comes from Approver roles. President, Vice President, Dean, and Department Head routing comes from the organizational position selected in the next tab.',
                                'صلاحية الإنشاء تأتي من أدوار المنشئ، وصلاحية الاعتماد تأتي من أدوار المعتمد. أما توجيه الرئيس ونائب الرئيس والعميد ورئيس القسم فيعتمد على المنصب التنظيمي المحدد في التبويب التالي.'
                            ),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </p>
                </div>
                <div class="admin-role-groups" data-admin-role-groups></div>
            </div>



            <div class="admin-editor-panel d-none" data-admin-panel="workflow">
                <div class="admin-workflow-intro">
                    <div>
                        <strong><?= htmlspecialchars($text('Place the user in the workflow', 'تحديد موقع المستخدم في سير العمل'), ENT_QUOTES, 'UTF-8') ?></strong>
                        <p class="mb-0">
                            <?= htmlspecialchars(
                                $text(
                                    'Choose the exact user position directly from the steps. Each step shows who comes before and after, plus the people currently occupying that stage. Selecting a place prepares the required role and position; save the form to activate it.',
                                    'اختاري موقع المستخدم مباشرة من الخطوات. كل خطوة توضّح من يسبق المستخدم ومن يأتي بعده، وتعرض الأشخاص الموجودين حاليًا في المرحلة. الضغط على الموقع يجهز الدور والمنصب المطلوبين ثم تحفظين التغييرات.'
                                ),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </p>
                    </div>
                    <span class="admin-workflow-legend">
                        <i aria-hidden="true"></i>
                        <?= htmlspecialchars($text('Current user position', 'موقع المستخدم الحالي'), ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </div>

                <div class="admin-workflow-grid">
                    <section class="admin-workflow-card" data-admin-workflow-card="initiative">
                        <header>
                            <div>
                                <p class="eyebrow mb-1"><?= htmlspecialchars($text('Initiatives', 'المبادرات'), ENT_QUOTES, 'UTF-8') ?></p>
                                <h3 class="h6 mb-1"><?= htmlspecialchars($text('Initiative approval path', 'مسار اعتماد المبادرات'), ENT_QUOTES, 'UTF-8') ?></h3>
                            </div>
                            <span class="admin-workflow-stage-count">5 <?= htmlspecialchars($text('stages', 'مراحل'), ENT_QUOTES, 'UTF-8') ?></span>
                        </header>

                        <div class="admin-workflow-instruction">
                            <strong><?= htmlspecialchars($text('Choose the exact step', 'اختاري الموقع الدقيق من الخطوات'), ENT_QUOTES, 'UTF-8') ?></strong>
                            <span><?= htmlspecialchars($text('The card states who is before and after the selected user.', 'كل خطوة توضّح من قبل المستخدم ومن بعده.'), ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <select class="d-none" id="admin-initiative-workflow-assignment" data-admin-workflow-select="initiative" aria-hidden="true">
                            <option value=""></option>
                            <option value="INIT_CREATOR">1</option>
                            <option value="INIT_DEPARTMENT_HEAD">2</option>
                            <option value="INIT_DEAN">3</option>
                            <option value="INIT_VP">4</option>
                            <option value="INIT_VP_DELEGATE">4D</option>
                            <option value="INIT_VP_STAFF">4S</option>
                            <option value="INIT_PRESIDENT">5</option>
                            <option value="INIT_PRESIDENT_DELEGATE">5D</option>
                            <option value="INIT_PRESIDENT_STAFF">5S</option>
                        </select>
                        <div class="admin-workflow-route is-initiative" data-admin-workflow-route="initiative" aria-label="Initiative workflow path"></div>
                        <p class="admin-workflow-message mb-0" data-admin-workflow-message="initiative" aria-live="polite"></p>
                        <p class="admin-workflow-current mb-0" data-admin-workflow-current="initiative"></p>
                    </section>

                    <section class="admin-workflow-card" data-admin-workflow-card="agreement">
                        <header>
                            <div>
                                <p class="eyebrow mb-1"><?= htmlspecialchars($text('Agreements', 'الاتفاقيات'), ENT_QUOTES, 'UTF-8') ?></p>
                                <h3 class="h6 mb-1"><?= htmlspecialchars($text('Agreement approval path', 'مسار اعتماد الاتفاقيات'), ENT_QUOTES, 'UTF-8') ?></h3>
                            </div>
                            <span class="admin-workflow-stage-count">6 <?= htmlspecialchars($text('stages', 'مراحل'), ENT_QUOTES, 'UTF-8') ?></span>
                        </header>

                        <div class="admin-workflow-instruction">
                            <strong><?= htmlspecialchars($text('Choose the exact step', 'اختاري الموقع الدقيق من الخطوات'), ENT_QUOTES, 'UTF-8') ?></strong>
                            <span><?= htmlspecialchars($text('VP placement appears in both the initial and final VP steps.', 'اختيار نائب الرئيس يظهر في مرحلتي المراجعة الأولية والنهائية.'), ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <select class="d-none" id="admin-agreement-workflow-assignment" data-admin-workflow-select="agreement" aria-hidden="true">
                            <option value=""></option>
                            <option value="AGREEMENT_CREATOR">1</option>
                            <option value="AGREEMENT_VP">2-5</option>
                            <option value="AGREEMENT_VP_DELEGATE">2-5D</option>
                            <option value="AGREEMENT_VP_STAFF">2-5S</option>
                            <option value="AGREEMENT_LEGAL">3</option>
                            <option value="AGREEMENT_FINANCE">4</option>
                            <option value="AGREEMENT_PRESIDENT">6</option>
                            <option value="AGREEMENT_PRESIDENT_DELEGATE">6D</option>
                            <option value="AGREEMENT_PRESIDENT_STAFF">6S</option>
                        </select>
                        <div class="admin-workflow-route is-agreement" data-admin-workflow-route="agreement" aria-label="Agreement workflow path"></div>
                        <p class="admin-workflow-message mb-0" data-admin-workflow-message="agreement" aria-live="polite"></p>
                        <p class="admin-workflow-current mb-0" data-admin-workflow-current="agreement"></p>
                    </section>
                </div>
            </div>

            <div class="admin-editor-panel d-none" data-admin-panel="organization">
                <div class="row g-3">
                    <div class="col-lg-6">
                        <label class="form-label" for="admin-user-unit"><?= htmlspecialchars($text('Organizational unit', 'الوحدة التنظيمية'), ENT_QUOTES, 'UTF-8') ?></label>
                        <select class="form-select" id="admin-user-unit" data-admin-user-unit>
                            <option value=""><?= htmlspecialchars($text('No active assignment', 'بدون تكليف نشط'), ENT_QUOTES, 'UTF-8') ?></option>
                        </select>
                        <div class="form-text"><?= htmlspecialchars($text('Office, college, department, or University unit.', 'مكتب أو كلية أو قسم أو وحدة على مستوى الجامعة.'), ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                    <div class="col-lg-6">
                        <label class="form-label" for="admin-user-position"><?= htmlspecialchars($text('Position', 'المنصب'), ENT_QUOTES, 'UTF-8') ?></label>
                        <select class="form-select" id="admin-user-position" data-admin-user-position>
                            <option value=""><?= htmlspecialchars($text('No active assignment', 'بدون تكليف نشط'), ENT_QUOTES, 'UTF-8') ?></option>
                        </select>
                        <div class="form-text"><?= htmlspecialchars($text('Examples: President, Vice President, Dean, Department Head, reviewer, or staff member.', 'مثل: رئيس الجامعة أو نائب الرئيس أو عميد أو رئيس قسم أو مراجع أو موظف.'), ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="admin-effective-date"><?= htmlspecialchars($text('Effective date', 'تاريخ سريان التغيير'), ENT_QUOTES, 'UTF-8') ?></label>
                        <input class="form-control" id="admin-effective-date" type="date" data-admin-effective-date>
                    </div>
                </div>
                <div class="admin-transfer-note mt-3">
                    <span aria-hidden="true">ℹ️</span>
                    <p class="mb-0"><?= htmlspecialchars($text('Changing the unit or position closes the current assignment and preserves it in history; it is never deleted.', 'تغيير الوحدة أو المنصب ينهي التكليف الحالي ويحفظه في السجل، ولا يتم حذفه.'), ENT_QUOTES, 'UTF-8') ?></p>
                </div>
            </div>

            <div class="admin-editor-panel d-none" data-admin-panel="history">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th><?= htmlspecialchars($text('Position', 'المنصب'), ENT_QUOTES, 'UTF-8') ?></th>
                                <th><?= htmlspecialchars($text('Unit', 'الوحدة'), ENT_QUOTES, 'UTF-8') ?></th>
                                <th><?= htmlspecialchars($text('Period', 'الفترة'), ENT_QUOTES, 'UTF-8') ?></th>
                                <th><?= htmlspecialchars($text('Status', 'الحالة'), ENT_QUOTES, 'UTF-8') ?></th>
                            </tr>
                        </thead>
                        <tbody data-admin-position-history></tbody>
                    </table>
                </div>
            </div>

            <div class="admin-save-panel">
                <div class="flex-grow-1">
                    <label class="form-label" for="admin-change-reason">
                        <?= htmlspecialchars($text('Reason for change', 'سبب التغيير'), ENT_QUOTES, 'UTF-8') ?>
                    </label>
                    <textarea
                        class="form-control"
                        id="admin-change-reason"
                        rows="2"
                        minlength="5"
                        maxlength="500"
                        required
                        placeholder="<?= htmlspecialchars($text('Required for the audit log', 'مطلوب لتسجيل التغيير في سجل التدقيق'), ENT_QUOTES, 'UTF-8') ?>"
                        data-admin-change-reason
                    ></textarea>
                </div>
                <button class="btn btn-primary btn-lg" type="submit" data-admin-user-save>
                    <span data-admin-save-label><?= htmlspecialchars($text('Save changes', 'حفظ التغييرات'), ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="spinner-border spinner-border-sm d-none" aria-hidden="true" data-admin-save-spinner></span>
                </button>
            </div>
        </form>
    </section>
</div>

<?php workspaceFooter(['assets/js/admin-users.js']); ?>
