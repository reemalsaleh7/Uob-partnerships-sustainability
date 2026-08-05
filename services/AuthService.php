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
        $this->userRepo->beginTransaction();

        try {
            $user = $this->userRepo->findByEmailForUpdate($email);

            if (!$user) {
                $this->userRepo->rollBack();
                return ['success' => false, 'error' => 'Invalid credentials'];
            }

            $userId = (int) ($user['user_id'] ?? 0);
            $failedAttempts = (int) ($user['failed_login_attempts'] ?? 0);
            $lockedUntil = trim((string) ($user['locked_until'] ?? ''));
            $lockedUntilTimestamp = $lockedUntil === ''
                ? false
                : strtotime($lockedUntil);

            if (
                $failedAttempts >= 5
                && $lockedUntilTimestamp !== false
                && $lockedUntilTimestamp > time()
            ) {
                $this->userRepo->rollBack();
                return [
                    'success' => false,
                    'error' => 'Account temporarily locked.',
                ];
            }

            if ($failedAttempts >= 5) {
                $this->userRepo->resetFailedAttempts($userId);
            }

            if (!password_verify($password, $user['password_hash'])) {
                $this->userRepo->recordFailedLogin($userId);
                $this->userRepo->commit();
                return ['success' => false, 'error' => 'Invalid credentials'];
            }

            if (!$this->userRepo->isActive($userId)) {
                $this->userRepo->rollBack();
                return ['success' => false, 'error' => 'Account is inactive'];
            }

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

    public function createLegacyInitiativeHandoff(): array {
        if (!$this->isAuthenticated()) {
            throw new DomainException('Authentication is required.');
        }

        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if (!$this->permissionService->hasPermission(
            $userId,
            'CREATE_INITIATIVE'
        )) {
            throw new DomainException(
                'Your account is not authorized to create Initiatives.'
            );
        }

        $token = bin2hex(random_bytes(32));
        $expiresAt = new DateTimeImmutable('+2 minutes');

        $this->userRepo->beginTransaction();

        try {
            $this->userRepo->createLegacyHandoff(
                $userId,
                hash('sha256', $token),
                $expiresAt
            );
            $this->userRepo->commit();
        } catch (Throwable $exception) {
            $this->userRepo->rollBack();
            throw $exception;
        }

        return [
            'token' => $token,
            'expires_at' => $expiresAt->format(DATE_ATOM),
        ];
    }
}
