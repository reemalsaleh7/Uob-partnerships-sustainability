<?php

declare(strict_types=1);

$root = dirname(__DIR__);

function adminUserSource(string $relativePath): string
{
    global $root;
    $path = $root . '/' . ltrim($relativePath, '/');
    if (!is_file($path)) {
        throw new RuntimeException("Required file is missing: {$relativePath}");
    }

    $source = file_get_contents($path);
    if ($source === false) {
        throw new RuntimeException("Could not read: {$relativePath}");
    }

    return $source;
}

function adminUserAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$api = adminUserSource('api/index.php');
$route = adminUserSource('routes/admin-users.php');
$repository = adminUserSource('repositories/AdminUserRepository.php');
$service = adminUserSource('services/AdminUserService.php');
$hierarchy = adminUserSource('services/HierarchyResolver.php');
$initiativeAccess = adminUserSource(
    'routes/initiative-workflow/InitiativeAccessPolicy.php'
);
$workspace = adminUserSource('uob-agreements/workspace/admin-users.php');
$workspaceJs = adminUserSource(
    'uob-agreements/workspace/assets/js/admin-users.js'
);
$sidebarJs = adminUserSource(
    'uob-agreements/workspace/assets/js/workspace-sidebar-polish.js'
);
$migration = adminUserSource(
    'uob-agreements/data/sql/migrations/20260805_004500_admin_user_management.sql'
);

adminUserAssert(
    str_contains($api, "str_starts_with(\$requestPath, '/admin/users')"),
    'The admin user API is not routed.'
);
adminUserAssert(
    str_contains($route, "PermissionMiddleware::require('MANAGE_USERS')"),
    'The admin API is not protected by MANAGE_USERS.'
);
adminUserAssert(
    str_contains($service, 'You cannot deactivate your own administrator account.')
        && str_contains($service, 'At least one active user must retain')
        && str_contains($service, 'expected_updated_at'),
    'Administrator lockout and concurrency protections are incomplete.'
);
adminUserAssert(
    str_contains($repository, 'PDO::PARAM_BOOL')
        && str_contains($repository, 'insertAudit')
        && str_contains($repository, 'replaceActivePosition'),
    'Boolean safety, auditing, or position history support is incomplete.'
);
adminUserAssert(
    str_contains($hierarchy, "'CREATE_AGREEMENT'")
        && !str_contains($hierarchy, 'AGREEMENT_CREATOR_OFFICES'),
    'Agreement creation is not controlled exclusively by permission.'
);
adminUserAssert(
    str_contains($initiativeAccess, "'CREATE_INITIATIVE'")
        && !str_contains($initiativeAccess, "'student'")
        && !str_contains($initiativeAccess, 'user_positions'),
    'Initiative creation still bypasses explicit permission assignment.'
);
adminUserAssert(
    str_contains($workspace, 'data-admin-user-form')
        && str_contains($workspaceJs, '/admin/users/options')
        && str_contains($workspaceJs, 'data-admin-role-id'),
    'The user-management workspace is incomplete.'
);
adminUserAssert(
    str_contains($sidebarJs, "'MANAGE_USERS'")
        && str_contains($sidebarJs, 'admin-users.php'),
    'The protected administration navigation is missing.'
);
adminUserAssert(
    !preg_match('/\bCREATE\s+TABLE\b/i', $migration)
        && str_contains($migration, 'organizational_units')
        && str_contains($migration, 'user_positions')
        && str_contains($migration, 'role_permissions'),
    'The migration must reuse the existing organization and RBAC tables.'
);

echo "Admin user-management source checks passed.\n";
