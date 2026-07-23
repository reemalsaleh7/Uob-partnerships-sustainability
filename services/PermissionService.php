<?php
require_once __DIR__ . '/../repositories/PermissionRepository.php';
require_once __DIR__ . '/../repositories/UserRepository.php';

class PermissionService {
    private PermissionRepository $permissionRepo;
    private UserRepository $userRepo;
    private array $permissionCache = [];

    public function __construct() {
        $this->permissionRepo = new PermissionRepository();
        $this->userRepo = new UserRepository();
    }

    public function getRoleNames(int $userId): array {
        return $this->userRepo->getRoles($userId);
    }

    public function getPermissionCodes(int $userId): array {
        if (!isset($this->permissionCache[$userId])) {
            $this->permissionCache[$userId] = $this->permissionRepo->getPermissionsForUser($userId);
        }

        return $this->permissionCache[$userId];
    }

    public function getPermissions(int $userId): array {
        return $this->getPermissionCodes($userId);
    }

    public function hasPermission(int $userId, string $permission): bool {
        return in_array($permission, $this->getPermissionCodes($userId), true);
    }

    public function hasAnyPermission(int $userId, array $permissions): bool {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($userId, $permission)) {
                return true;
            }
        }

        return false;
    }

    public function hasAllPermissions(int $userId, array $permissions): bool {
        foreach ($permissions as $permission) {
            if (!$this->hasPermission($userId, $permission)) {
                return false;
            }
        }

        return true;
    }

    public function hasPermissionForUnit(int $userId, string $permission, ?int $unitId = null): bool {
        if ($unitId === null || $unitId <= 0) {
            return $this->hasPermission($userId, $permission);
        }

        return $this->hasPermission($userId, $permission)
            && $this->permissionRepo->hasActivePositionForUnit($userId, $unitId);
    }
}
