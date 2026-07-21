<?php
require_once __DIR__ . '/../repositories/UserRepository.php';
require_once __DIR__ . '/../services/PermissionService.php';
require_once __DIR__ . '/../services/AuditService.php';
require_once __DIR__ . '/../helpers/ApiSession.php';

class AuthService {
    private UserRepository $userRepo;
    private PermissionService $permissionService;
    private AuditService $auditService;

    public function __construct() {
        $this->userRepo = new UserRepository();
        $this->permissionService = new PermissionService();
        $this->auditService = new AuditService();
    }

    public function login(string $email, string $password): array {
        $user = $this->userRepo->findByEmail($email);

        if (!$user) {
            return ['success' => false, 'error' => 'Invalid credentials'];
        }

        $this->userRepo->beginTransaction();

        try {
            $user = $this->userRepo->findByEmailForUpdate($email);
            if (!$user) {
                $this->userRepo->rollBack();
                return ['success' => false, 'error' => 'Invalid credentials'];
            }

            $userId = (int) ($user['user_id'] ?? 0);
            $lockedUntil = $user['locked_until'] ?? null;
            if (
                is_string($lockedUntil)
                && strtotime($lockedUntil) > time()
            ) {
                $this->userRepo->rollBack();
                return [
                    'success' => false,
                    'error' => 'Account temporarily locked. Try again later.',
                ];
            }

            if ($lockedUntil !== null) {
                $this->userRepo->resetFailedAttempts($userId);
            }

            if (!password_verify($password, $user['password_hash'])) {
                $this->userRepo->recordFailedLogin($userId);
                $this->userRepo->commit();
                return ['success' => false, 'error' => 'Invalid credentials'];
            }

            if (isset($user['is_active']) && $user['is_active'] === false) {
                $this->userRepo->rollBack();
                return ['success' => false, 'error' => 'Account is inactive'];
            }

            $roles = $this->permissionService->getRoleNames($userId);
            $permissions = $this->permissionService->getPermissionCodes($userId);
            $positions = $this->userRepo->getActivePositions($userId);

            $this->userRepo->resetFailedAttempts($userId);
            $this->userRepo->updateLastLogin($userId);
            $this->auditService->logLogin($userId, ['email' => $user['email']]);

            ApiSession::regenerate();

            $_SESSION['user_id'] = $userId;
            $_SESSION['email'] = $user['email'];
            $_SESSION['full_name'] = trim(implode(' ', array_filter([
                $user['first_name'] ?? '',
                $user['last_name'] ?? '',
            ])));
            ApiSession::markAuthenticated();

            $this->userRepo->commit();

            return ['success' => true, 'user' => $this->currentUser()];
        } catch (Throwable $e) {
            $this->userRepo->rollBack();
            throw $e;
        }
    }

    public function logout(): void {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $_SESSION = [];
        session_destroy();

        if ($userId > 0) {
            $this->auditService->logLogout($userId);
        }
    }

    public function isAuthenticated(): bool {
        return isset($_SESSION['user_id']);
    }

    public function currentUser(): ?array {
        if (!$this->isAuthenticated()) {
            return null;
        }

        $userId = (int) ($_SESSION['user_id'] ?? 0);

        return [
            'user_id' => $userId,
            'email' => $_SESSION['email'] ?? null,
            'full_name' => $_SESSION['full_name'] ?? null,
            'roles' => $this->permissionService->getRoleNames($userId),
            'permissions' => $this->permissionService->getPermissionCodes($userId),
            'positions' => $this->userRepo->getActivePositions($userId),
        ];
    }
}
