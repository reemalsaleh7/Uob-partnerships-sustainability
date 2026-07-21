<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

function readinessAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function readinessCount(PDO $db, string $sql): int
{
    return (int) $db->query($sql)->fetchColumn();
}

foreach (['pdo_pgsql', 'fileinfo', 'zip'] as $extension) {
    readinessAssert(
        extension_loaded($extension),
        "Required PHP extension {$extension} is not enabled"
    );
}

$db = Database::connect();

$requiredTables = [
    'agreements',
    'agreement_versions',
    'agreement_documents',
    'workflow_instances',
    'workflow_instance_steps',
    'workflow_step_assignments',
    'agreement_lifecycle_requests',
    'agreement_lifecycle_request_versions',
    'agreement_lifecycle_request_documents',
    'agreement_signing_records',
    'agreement_status_events',
    'agreement_performance_reports',
    'agreement_performance_metric_results',
    'agreement_executive_program_updates',
];

$tableStatement = $db->prepare(
    "SELECT to_regclass('public.' || :table_name) IS NOT NULL"
);
foreach ($requiredTables as $tableName) {
    $tableStatement->execute(['table_name' => $tableName]);
    readinessAssert(
        (bool) $tableStatement->fetchColumn(),
        "Required table {$tableName} is missing"
    );
}

$columnStatement = $db->prepare(
    'SELECT 1
     FROM information_schema.columns
     WHERE table_schema = \'public\'
       AND table_name = :table_name
       AND column_name = :column_name'
);
foreach ([
    ['users', 'locked_until'],
    ['agreements', 'annual_report_required'],
    ['agreement_lifecycle_requests', 'successor_agreement_id'],
    ['agreement_documents', 'storage_key'],
    ['workflow_instances', 'review_cycle'],
] as [$tableName, $columnName]) {
    $columnStatement->execute([
        'table_name' => $tableName,
        'column_name' => $columnName,
    ]);
    readinessAssert(
        (bool) $columnStatement->fetchColumn(),
        "Required column {$tableName}.{$columnName} is missing"
    );
}

$requiredPermissions = [
    'CREATE_AGREEMENT',
    'EDIT_AGREEMENT',
    'SUBMIT_AGREEMENT',
    'VIEW_AGREEMENT',
    'DELETE_AGREEMENT',
    'APPROVE_AGREEMENT',
    'REJECT_AGREEMENT',
    'MANAGE_AGREEMENT_OPERATIONS',
    'MANAGE_AGREEMENT_REPORTS',
    'REVIEW_AGREEMENT_REPORTS',
    'VIEW_AGREEMENT_DASHBOARD',
];
$permissionStatement = $db->prepare(
    'SELECT COUNT(*)
     FROM permissions
     WHERE permission_code = ANY(CAST(:codes AS TEXT[]))'
);
$permissionStatement->execute([
    'codes' => '{' . implode(',', $requiredPermissions) . '}',
]);
readinessAssert(
    (int) $permissionStatement->fetchColumn() === count($requiredPermissions),
    'One or more Agreement permissions are missing'
);

$roleExpectations = [
    'Agreement Creator' => [
        'CREATE_AGREEMENT', 'EDIT_AGREEMENT', 'SUBMIT_AGREEMENT',
        'VIEW_AGREEMENT', 'DELETE_AGREEMENT',
        'MANAGE_AGREEMENT_OPERATIONS', 'MANAGE_AGREEMENT_REPORTS',
    ],
    'Agreement Approver' => [
        'VIEW_AGREEMENT', 'APPROVE_AGREEMENT', 'REJECT_AGREEMENT',
        'REVIEW_AGREEMENT_REPORTS', 'VIEW_AGREEMENT_DASHBOARD',
    ],
    'System Administrator' => $requiredPermissions,
];
$roleStatement = $db->prepare(
    'SELECT p.permission_code
     FROM roles r
     JOIN role_permissions rp ON rp.role_id = r.role_id
     JOIN permissions p ON p.permission_id = rp.permission_id
     WHERE r.role_name = :role_name'
);
foreach ($roleExpectations as $roleName => $expectedCodes) {
    $roleStatement->execute(['role_name' => $roleName]);
    $actualCodes = $roleStatement->fetchAll(PDO::FETCH_COLUMN);
    $missing = array_values(array_diff($expectedCodes, $actualCodes));
    readinessAssert(
        $missing === [],
        $roleName . ' is missing: ' . implode(', ', $missing)
    );
}

$workflowSteps = $db->query(
    "SELECT wts.step_key
     FROM workflow_templates wt
     JOIN workflow_template_steps wts
       ON wts.workflow_template_id = wt.workflow_template_id
     WHERE wt.name = 'Agreement Approval'
       AND wt.is_active = TRUE
     ORDER BY wts.step_order"
)->fetchAll(PDO::FETCH_COLUMN);
readinessAssert(
    $workflowSteps === [
        'CREATOR', 'VP_INITIAL', 'LEGAL_REVIEW', 'FINANCE_REVIEW',
        'VP_FINAL', 'PRESIDENT_APPROVAL',
    ],
    'Agreement Approval workflow template is missing or out of order'
);

$activeReviewers = $db->query(
    "SELECT ou.code,
            COUNT(DISTINCT u.user_id) FILTER (
                WHERE EXISTS (
                    SELECT 1
                    FROM user_roles ur
                    JOIN role_permissions rp ON rp.role_id = ur.role_id
                    JOIN permissions p ON p.permission_id = rp.permission_id
                    WHERE ur.user_id = u.user_id
                      AND p.permission_code = 'APPROVE_AGREEMENT'
                )
                AND EXISTS (
                    SELECT 1
                    FROM user_roles ur
                    JOIN role_permissions rp ON rp.role_id = ur.role_id
                    JOIN permissions p ON p.permission_id = rp.permission_id
                    WHERE ur.user_id = u.user_id
                      AND p.permission_code = 'REJECT_AGREEMENT'
                )
            ) AS reviewer_count
     FROM organizational_units ou
     LEFT JOIN user_positions up
       ON up.unit_id = ou.unit_id
      AND up.is_active = TRUE
      AND (up.end_date IS NULL OR up.end_date >= CURRENT_DATE)
     LEFT JOIN users u
       ON u.user_id = up.user_id
      AND u.is_active = TRUE
     WHERE ou.code IN ('VP', 'LEGAL', 'FIN', 'PRES')
     GROUP BY ou.code"
)->fetchAll(PDO::FETCH_KEY_PAIR);
foreach (['VP', 'LEGAL', 'FIN', 'PRES'] as $officeCode) {
    readinessAssert(
        (int) ($activeReviewers[$officeCode] ?? 0) > 0,
        "No active reviewer is assigned to office {$officeCode}"
    );
}

readinessAssert(
    readinessCount(
        $db,
        "SELECT COUNT(*)
         FROM agreements a
         WHERE a.status = 'UNDER_REVIEW'
           AND NOT EXISTS (
               SELECT 1 FROM workflow_instances wi
               WHERE wi.entity_type = 'AGREEMENT'
                 AND wi.entity_id = a.agreement_id
                 AND wi.status = 'IN_PROGRESS'
           )"
    ) === 0,
    'An UNDER_REVIEW Agreement has no active workflow'
);

readinessAssert(
    readinessCount(
        $db,
        "SELECT COUNT(*)
         FROM workflow_instances wi
         WHERE wi.status = 'IN_PROGRESS'
           AND NOT EXISTS (
               SELECT 1
               FROM workflow_instance_steps wis
               JOIN workflow_step_assignments wsa
                 ON wsa.workflow_instance_step_id = wis.instance_step_id
               WHERE wis.workflow_instance_id = wi.workflow_instance_id
                 AND wis.status = 'IN_PROGRESS'
                 AND wsa.is_active = TRUE
           )"
    ) === 0,
    'An active workflow has no active reviewer/creator assignment'
);

readinessAssert(
    readinessCount(
        $db,
        'SELECT COUNT(*)
         FROM agreement_signing_records sr
         JOIN agreement_documents ad
           ON ad.document_id = sr.signed_document_id
         WHERE ad.agreement_id <> sr.agreement_id'
    ) === 0,
    'A signing record references another Agreement\'s document'
);

readinessAssert(
    readinessCount(
        $db,
        'SELECT COUNT(*)
         FROM agreement_performance_reports pr
         JOIN agreement_documents ad
           ON ad.document_id = pr.report_document_id
         WHERE ad.agreement_id <> pr.agreement_id'
    ) === 0,
    'A performance report references another Agreement\'s document'
);

foreach ([
    __DIR__ . '/../storage/private',
    __DIR__ . '/../storage/private/agreement-documents',
    __DIR__ . '/../storage/private/lifecycle-request-documents',
] as $storagePath) {
    $writablePath = is_dir($storagePath)
        ? $storagePath
        : dirname($storagePath);
    readinessAssert(
        is_dir($writablePath) && is_writable($writablePath),
        "Private storage path is unavailable or not writable: {$storagePath}"
    );
}

echo "Agreement release-readiness smoke test passed.\n";
