<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

$language = workspaceResolveLanguage();
$isArabic = $language === 'ar';
$text = static fn (string $english, string $arabic): string =>
    $isArabic ? $arabic : $english;

workspaceHeader(
    $text('Workflow templates', 'قوالب سير العمل'),
    'admin-workflows',
    ['assets/css/admin-workflows.css']
);
?>

<section class="page-heading workflow-admin-heading">
    <div>
        <p class="eyebrow mb-2"><?= htmlspecialchars($text('Administration', 'الإدارة'), ENT_QUOTES, 'UTF-8') ?></p>
        <h1 class="display-6 mb-2"><?= htmlspecialchars($text('Workflow template management', 'إدارة قوالب سير العمل'), ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="text-secondary mb-0"><?= htmlspecialchars(
            $text(
                'Build versioned Agreement and Initiative approval routes. Published changes apply only to new workflows.',
                'أنشئ مسارات اعتماد بإصدارات للاتفاقيات والمبادرات. التغييرات المنشورة تطبق على مسارات العمل الجديدة فقط.'
            ),
            ENT_QUOTES,
            'UTF-8'
        ) ?></p>
    </div>
    <div class="workflow-admin-heading-actions">
        <a class="btn btn-outline-secondary" href="admin-users.php"><?= htmlspecialchars($text('User management', 'إدارة المستخدمين'), ENT_QUOTES, 'UTF-8') ?></a>
        <button class="btn btn-outline-primary" type="button" data-workflow-refresh><?= htmlspecialchars($text('Refresh', 'تحديث'), ENT_QUOTES, 'UTF-8') ?></button>
    </div>
</section>

<div class="alert alert-danger d-none mt-4" role="alert" tabindex="-1" data-workflow-alert></div>
<div class="alert alert-success d-none mt-4" role="status" tabindex="-1" data-workflow-success></div>

<section class="workflow-safety-note mt-4" aria-label="Publishing behavior">
    <span aria-hidden="true">🛡️</span>
    <div>
        <strong><?= htmlspecialchars($text('Safe version publishing', 'نشر آمن بالإصدارات'), ENT_QUOTES, 'UTF-8') ?></strong>
        <p class="mb-0"><?= htmlspecialchars(
            $text(
                'Open requests keep their existing stage snapshot. A new version is used only when a new Agreement or Initiative enters approval.',
                'الطلبات المفتوحة تحتفظ بنسخة مراحلها الحالية. الإصدار الجديد يستخدم فقط عند دخول اتفاقية أو مبادرة جديدة إلى الاعتماد.'
            ),
            ENT_QUOTES,
            'UTF-8'
        ) ?></p>
    </div>
</section>

<div class="workflow-template-shell mt-4">
    <aside class="workspace-card workflow-template-list-card">
        <div class="workspace-card-header">
            <div>
                <h2 class="h5 mb-1"><?= htmlspecialchars($text('Templates', 'القوالب'), ENT_QUOTES, 'UTF-8') ?></h2>
                <p class="small text-secondary mb-0"><?= htmlspecialchars($text('Choose the route to edit', 'اختاري المسار المراد تعديله'), ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        </div>
        <div class="workflow-template-list" data-template-list></div>
        <div class="workflow-version-panel">
            <h3 class="h6 mb-2"><?= htmlspecialchars($text('Published versions', 'الإصدارات المنشورة'), ENT_QUOTES, 'UTF-8') ?></h3>
            <div data-version-list></div>
        </div>
    </aside>

    <main class="workspace-card workflow-template-editor-card">
        <div class="workflow-editor-loading" data-workflow-loading>
            <span class="spinner-border" aria-hidden="true"></span>
            <p class="mb-0"><?= htmlspecialchars($text('Loading Workflow template…', 'جاري تحميل قالب سير العمل…'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>

        <form class="d-none" data-workflow-form>
            <div class="workflow-editor-header">
                <div>
                    <div class="workflow-template-title-row">
                        <h2 class="h4 mb-0" data-template-title></h2>
                        <span class="workflow-version-badge" data-template-version></span>
                    </div>
                    <p class="small text-secondary mt-2 mb-0" data-template-meta></p>
                </div>
                <button class="btn btn-primary" type="button" data-add-stage>
                    <span aria-hidden="true">＋</span>
                    <?= htmlspecialchars($text('Add stage', 'إضافة مرحلة'), ENT_QUOTES, 'UTF-8') ?>
                </button>
            </div>

            <div class="mt-4">
                <label class="form-label" for="workflow-description"><?= htmlspecialchars($text('Template description', 'وصف القالب'), ENT_QUOTES, 'UTF-8') ?></label>
                <textarea class="form-control" id="workflow-description" rows="2" maxlength="1000" data-template-description></textarea>
            </div>

            <div class="workflow-route-summary mt-4" data-route-summary aria-live="polite"></div>

            <div class="workflow-stage-list mt-4" data-stage-list></div>

            <section class="workflow-publish-panel mt-4">
                <div class="flex-grow-1">
                    <label class="form-label" for="workflow-publish-reason"><?= htmlspecialchars($text('Reason for publishing', 'سبب نشر الإصدار'), ENT_QUOTES, 'UTF-8') ?></label>
                    <textarea
                        class="form-control"
                        id="workflow-publish-reason"
                        rows="2"
                        minlength="5"
                        maxlength="500"
                        required
                        placeholder="<?= htmlspecialchars($text('Required for the audit record', 'مطلوب لسجل التدقيق'), ENT_QUOTES, 'UTF-8') ?>"
                        data-publish-reason
                    ></textarea>
                </div>
                <button class="btn btn-primary btn-lg" type="submit" data-publish-button>
                    <span data-publish-label><?= htmlspecialchars($text('Publish new version', 'نشر إصدار جديد'), ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="spinner-border spinner-border-sm d-none" aria-hidden="true" data-publish-spinner></span>
                </button>
            </section>
        </form>
    </main>
</div>

<template data-stage-template>
    <article class="workflow-stage-card">
        <header class="workflow-stage-card-header">
            <div class="workflow-stage-index" data-stage-index></div>
            <div class="workflow-stage-heading">
                <strong data-stage-heading></strong>
                <small data-stage-phase></small>
            </div>
            <div class="workflow-stage-actions">
                <button class="btn btn-sm btn-outline-secondary" type="button" data-stage-up aria-label="Move stage up">↑</button>
                <button class="btn btn-sm btn-outline-secondary" type="button" data-stage-down aria-label="Move stage down">↓</button>
                <button class="btn btn-sm btn-outline-danger" type="button" data-stage-delete><?= htmlspecialchars($text('Delete', 'حذف'), ENT_QUOTES, 'UTF-8') ?></button>
            </div>
        </header>
        <div class="workflow-stage-fields">
            <div class="workflow-field workflow-field-wide">
                <label class="form-label"><?= htmlspecialchars($text('Stage name', 'اسم المرحلة'), ENT_QUOTES, 'UTF-8') ?></label>
                <input class="form-control" type="text" minlength="2" maxlength="150" required data-stage-label>
            </div>
            <div class="workflow-field">
                <label class="form-label"><?= htmlspecialchars($text('Execution', 'طريقة التنفيذ'), ENT_QUOTES, 'UTF-8') ?></label>
                <select class="form-select" data-stage-execution>
                    <option value="SEQUENTIAL"><?= htmlspecialchars($text('Sequential — after previous phase', 'متسلسلة — بعد المرحلة السابقة'), ENT_QUOTES, 'UTF-8') ?></option>
                    <option value="PARALLEL"><?= htmlspecialchars($text('Parallel — with previous stage', 'متوازية — مع المرحلة السابقة'), ENT_QUOTES, 'UTF-8') ?></option>
                </select>
            </div>
            <div class="workflow-field">
                <label class="form-label"><?= htmlspecialchars($text('Requirement', 'الإلزام'), ENT_QUOTES, 'UTF-8') ?></label>
                <select class="form-select" data-stage-optional>
                    <option value="false"><?= htmlspecialchars($text('Required', 'إلزامية'), ENT_QUOTES, 'UTF-8') ?></option>
                    <option value="true"><?= htmlspecialchars($text('Optional', 'اختيارية'), ENT_QUOTES, 'UTF-8') ?></option>
                </select>
            </div>
            <div class="workflow-field">
                <label class="form-label"><?= htmlspecialchars($text('Responsible by', 'تحديد المسؤول حسب'), ENT_QUOTES, 'UTF-8') ?></label>
                <select class="form-select" data-stage-responsibility>
                    <option value="POSITION"><?= htmlspecialchars($text('Position', 'المنصب'), ENT_QUOTES, 'UTF-8') ?></option>
                    <option value="UNIT"><?= htmlspecialchars($text('Office or unit', 'المكتب أو الوحدة'), ENT_QUOTES, 'UTF-8') ?></option>
                </select>
            </div>
            <div class="workflow-field">
                <label class="form-label"><?= htmlspecialchars($text('Organizational scope', 'النطاق التنظيمي'), ENT_QUOTES, 'UTF-8') ?></label>
                <select class="form-select" data-stage-scope>
                    <option value="FIXED_UNIT"><?= htmlspecialchars($text('A fixed office or unit', 'مكتب أو وحدة ثابتة'), ENT_QUOTES, 'UTF-8') ?></option>
                    <option value="REQUESTER_DEPARTMENT"><?= htmlspecialchars($text("Requester's department", 'قسم مقدم الطلب'), ENT_QUOTES, 'UTF-8') ?></option>
                    <option value="REQUESTER_COLLEGE"><?= htmlspecialchars($text("Requester's college", 'كلية مقدم الطلب'), ENT_QUOTES, 'UTF-8') ?></option>
                    <option value="UNIVERSITY"><?= htmlspecialchars($text('Across the University', 'على مستوى الجامعة'), ENT_QUOTES, 'UTF-8') ?></option>
                </select>
            </div>
            <div class="workflow-field" data-unit-field>
                <label class="form-label"><?= htmlspecialchars($text('Office / unit', 'المكتب / الوحدة'), ENT_QUOTES, 'UTF-8') ?></label>
                <select class="form-select" data-stage-unit></select>
            </div>
            <div class="workflow-field" data-position-field>
                <label class="form-label"><?= htmlspecialchars($text('Position', 'المنصب'), ENT_QUOTES, 'UTF-8') ?></label>
                <select class="form-select" data-stage-position></select>
            </div>
            <div class="workflow-field">
                <label class="form-label"><?= htmlspecialchars($text('Reminder after', 'التذكير بعد'), ENT_QUOTES, 'UTF-8') ?></label>
                <div class="input-group">
                    <input class="form-control" type="number" min="1" max="90" value="3" data-stage-reminder>
                    <span class="input-group-text"><?= htmlspecialchars($text('days', 'أيام'), ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            </div>
            <label class="form-check workflow-revision-check">
                <input class="form-check-input" type="checkbox" checked data-stage-revision>
                <span class="form-check-label"><?= htmlspecialchars($text('Allow returning for changes', 'السماح بالإرجاع للتعديل'), ENT_QUOTES, 'UTF-8') ?></span>
            </label>
        </div>
        <div class="workflow-stage-lock d-none" data-stage-lock></div>
    </article>
</template>

<?php workspaceFooter(['assets/js/admin-workflows.js']); ?>
