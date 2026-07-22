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

        $profile = $this->userRepo->findProfileById($userId) ?? [];

        return [
            'user_id' => $userId,
            'university_id' => $profile['university_id'] ?? null,
            'first_name' => $profile['first_name'] ?? null,
            'last_name' => $profile['last_name'] ?? null,
            'email' => $profile['email'] ?? ($_SESSION['email'] ?? null),
            'phone' => $profile['phone'] ?? null,
            'full_name' => trim(implode(' ', array_filter([
                $profile['first_name'] ?? null,
                $profile['last_name'] ?? null,
            ]))) ?: ($_SESSION['full_name'] ?? null),
            'last_login' => $profile['last_login'] ?? null,
            'password_changed_at' => $profile['password_changed_at'] ?? null,
            'account_created_at' => $profile['created_at'] ?? null,
            'is_active' => (bool) ($profile['is_active'] ?? true),
            'roles' => $this->permissionService->getRoleNames($userId),
            'permissions' => $this->permissionService->getPermissionCodes($userId),
            'positions' => $this->userRepo->getActivePositions($userId),
        ];
    }

    public function createLegacyInitiativeHandoff(): array {
        if (!$this->isAuthenticated()) {
            throw new DomainException('Not authenticated');
        }

        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if (
            !$this->permissionService->hasPermission(
                $userId,
                'CREATE_INITIATIVE'
            )
            && !in_array(
                'Initiative Creator',
                $this->permissionService->getRoleNames($userId),
                true
            )
        ) {
            throw new DomainException(
                'Your role cannot create Initiative requests'
            );
        }

        $token = bin2hex(random_bytes(32));
        $expiresAt = new DateTimeImmutable('+2 minutes');
        $this->userRepo->createLegacyHandoff(
            $userId,
            hash('sha256', $token),
            $expiresAt
        );

        return [
            'token' => $token,
            'expires_at' => $expiresAt->format(DATE_ATOM),
        ];
    }
}
