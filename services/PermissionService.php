<?php
require_once __DIR__ . '/../repositories/UserRepository.php';

class PermissionService {
    private UserRepository $userRepo;

    public function __construct() {
        $this->userRepo = new UserRepository();
    }

    public function getRoleNames(int $userId): array {
        return $this->userRepo->getRoles($userId);
    }

    public function getPermissionCodes(int $userId): array {
        return $this->userRepo->getPermissions($userId);
    }
}
