<?php
require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../services/PermissionService.php';

class PermissionMiddleware {
    public static function require(string $permission, ?array $context = null): void {
        self::enforce(function (int $userId, PermissionService $service) use ($permission, $context): bool {
            $unitId = $context['unit_id'] ?? null;
            return $service->hasPermissionForUnit($userId, $permission, $unitId ? (int) $unitId : null);
        });
    }

    public static function requireAny(array $permissions, ?array $context = null): void {
        self::enforce(function (int $userId, PermissionService $service) use ($permissions, $context): bool {
            $unitId = $context['unit_id'] ?? null;
            if ($unitId !== null && $unitId > 0) {
                foreach ($permissions as $permission) {
                    if ($service->hasPermissionForUnit($userId, $permission, (int) $unitId)) {
                        return true;
                    }
                }
                return false;
            }

            return $service->hasAnyPermission($userId, $permissions);
        });
    }

    public static function requireAll(array $permissions, ?array $context = null): void {
        self::enforce(function (int $userId, PermissionService $service) use ($permissions, $context): bool {
            $unitId = $context['unit_id'] ?? null;
            if ($unitId !== null && $unitId > 0) {
                foreach ($permissions as $permission) {
                    if (!$service->hasPermissionForUnit($userId, $permission, (int) $unitId)) {
                        return false;
                    }
                }
                return true;
            }

            return $service->hasAllPermissions($userId, $permissions);
        });
    }

    private static function enforce(callable $checker): void {
        if (!isset($_SESSION['user_id'])) {
            Response::error('Unauthorized', 401);
        }

        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $service = new PermissionService();

        if (!$checker($userId, $service)) {
            Response::error('Forbidden', 403);
        }
    }
}
