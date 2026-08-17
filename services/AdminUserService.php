<?php

declare(strict_types=1);

require_once __DIR__ . '/../repositories/AdminUserRepository.php';

final class AdminUserManagementException extends RuntimeException
{
    public function __construct(string $message, private int $httpStatus = 422)
    {
        parent::__construct($message);
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }
}

final class AdminUserService
{
    private const MAX_PAGE_SIZE = 100;

    private AdminUserRepository $repository;

    public function __construct(?AdminUserRepository $repository = null)
    {
        $this->repository = $repository ?? new AdminUserRepository();
    }

    public function listUsers(array $query): array
    {
        $search = trim((string) ($query['search'] ?? ''));

        if (mb_strlen($search) > 150) {
            throw new AdminUserManagementException(
                'Search text cannot exceed 150 characters.'
            );
        }

        $active = $this->nullableBoolean(
            $query['active'] ?? null
        );

        $unitIds = [];
        $expandUnitHierarchy = false;

        $rawUnitIds = trim(
            (string) ($query['unit_ids'] ?? '')
        );

        if ($rawUnitIds !== '') {
            foreach (explode(',', $rawUnitIds) as $rawUnitId) {
                $rawUnitId = trim($rawUnitId);

                if ($rawUnitId === '') {
                    continue;
                }

                $unitId =
                    $this->nullablePositiveInteger(
                        $rawUnitId
                    );

                if ($unitId === null) {
                    throw new AdminUserManagementException(
                        'One or more organizational unit filters are invalid.'
                    );
                }

                $unitIds[] = $unitId;
            }

            $unitIds = array_values(
                array_unique($unitIds)
            );
        } else {
            $legacyUnitId =
                $this->nullablePositiveInteger(
                    $query['unit_id'] ?? null
                );

            if ($legacyUnitId !== null) {
                $unitIds = [$legacyUnitId];
                $expandUnitHierarchy = true;
            }
        }

        $page = max(
            1,
            (int) ($query['page'] ?? 1)
        );

        $limit = min(
            self::MAX_PAGE_SIZE,
            max(
                10,
                (int) ($query['limit'] ?? 25)
            )
        );

        return $this->repository->searchUsers(
            $search,
            $active,
            $unitIds,
            $expandUnitHierarchy,
            $page,
            $limit
        );
    }

    public function options(): array
    {
        return [
            'roles' => $this->repository->listRoles(),
            'units' => $this->repository->listUnits(),
            'positions' => $this->repository->listPositions(),
            'rules' => [
                'agreement_creator_role' => 'Agreement Creator',
                'agreement_approver_role' => 'Agreement Approver',
                'initiative_creator_role' => 'Initiative Creator',
                'initiative_approver_role' => 'Initiative Approver',
                'administrator_role' => 'System Administrator',
                'creation_access_is_permission_based' => true,
                'position_changes_preserve_history' => true,
            ],
        ];
    }

    public function user(int $userId): array
    {
        $user = $this->repository->findUserDetail($userId);
        if ($user === null) {
            throw new AdminUserManagementException('User was not found.', 404);
        }

        return $this->addAccessSummary($user);
    }

    public function updateUser(
        int $actorUserId,
        int $targetUserId,
        array $input
    ): array {
        if ($actorUserId <= 0 || $targetUserId <= 0) {
            throw new AdminUserManagementException('Invalid user identifier.');
        }

        $payload = $this->validateUpdatePayload($input);
        $roleIds = $payload['role_ids'];

        $existingRoleIds = $this->repository->existingRoleIds($roleIds);
        sort($existingRoleIds);
        $expectedRoleIds = $roleIds;
        sort($expectedRoleIds);
        if ($existingRoleIds !== $expectedRoleIds) {
            throw new AdminUserManagementException(
                'One or more selected roles do not exist.'
            );
        }

        if ($payload['unit_id'] !== null) {
            $unit = $this->repository->findUnit($payload['unit_id']);
            if ($unit === null || ($unit['is_active'] ?? false) !== true) {
                throw new AdminUserManagementException(
                    'The selected organizational unit is unavailable.'
                );
            }

            $position = $this->repository->findPosition(
                (int) $payload['position_id']
            );
            if ($position === null) {
                throw new AdminUserManagementException(
                    'The selected position is unavailable.'
                );
            }

            $this->assertPositionCompatibleWithUnit($position, $unit);

            if ($this->repository->hasUniquePositionConflict(
                $targetUserId,
                (int) $payload['position_id'],
                $payload['unit_id']
            )) {
                throw new AdminUserManagementException(
                    'This unique position already has an active holder in the selected organizational unit.',
                    409
                );
            }
        }

        $managerRoleIds = $this->repository->roleIdsGrantingPermission(
            'MANAGE_USERS'
        );
        $willManageUsers = count(array_intersect(
            $roleIds,
            $managerRoleIds
        )) > 0;

        $this->repository->beginTransaction();

        try {
            $lockedUser = $this->repository->findUserForUpdate($targetUserId);
            if ($lockedUser === null) {
                throw new AdminUserManagementException('User was not found.', 404);
            }

            $before = $this->repository->findUserDetail($targetUserId);
            if ($before === null) {
                throw new AdminUserManagementException('User was not found.', 404);
            }

            $this->assertFreshVersion(
                $payload['expected_updated_at'],
                (string) ($lockedUser['updated_at'] ?? '')
            );

            $currentlyManagesUsers = in_array(
                'MANAGE_USERS',
                $before['permissions'] ?? [],
                true
            );

            if ($targetUserId === $actorUserId) {
                if (!$payload['is_active']) {
                    throw new AdminUserManagementException(
                        'You cannot deactivate your own administrator account.',
                        409
                    );
                }
                if (!$willManageUsers) {
                    throw new AdminUserManagementException(
                        'You cannot remove your own user-management authority.',
                        409
                    );
                }
            }

            if (
                $currentlyManagesUsers
                && (!$payload['is_active'] || !$willManageUsers)
                && $this->repository->countActiveManagersExcluding(
                    $targetUserId
                ) === 0
            ) {
                throw new AdminUserManagementException(
                    'At least one active user must retain user-management authority.',
                    409
                );
            }

            $this->repository->updateUser($targetUserId, $payload);
            $this->repository->replaceRoles($targetUserId, $roleIds);

            $activePosition = $this->activePosition($before['positions'] ?? []);
            $positionChanged =
                ($activePosition['position_id'] ?? null) !== $payload['position_id']
                || ($activePosition['unit_id'] ?? null) !== $payload['unit_id'];

            if ($positionChanged) {
                $this->repository->replaceActivePosition(
                    $targetUserId,
                    $payload['position_id'],
                    $payload['unit_id'],
                    $payload['effective_date']
                );
            }

            $after = $this->repository->findUserDetail($targetUserId);
            if ($after === null) {
                throw new RuntimeException(
                    'The updated user could not be reloaded.'
                );
            }

            $this->repository->insertAudit(
                $actorUserId,
                $targetUserId,
                $this->auditSnapshot($before),
                $this->auditSnapshot($after),
                $payload['reason']
            );

            $this->repository->commit();
            return $this->addAccessSummary($after);
        } catch (Throwable $exception) {
            $this->repository->rollBack();
            throw $exception;
        }
    }

    private function validateUpdatePayload(array $input): array
    {
        $universityId = $this->requiredText(
            $input['university_id'] ?? null,
            'University ID',
            30
        );
        $firstName = $this->requiredText(
            $input['first_name'] ?? null,
            'First name',
            100
        );
        $lastName = $this->requiredText(
            $input['last_name'] ?? null,
            'Last name',
            100
        );
        $email = mb_strtolower($this->requiredText(
            $input['email'] ?? null,
            'Email',
            255
        ));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new AdminUserManagementException(
                'Enter a valid email address.'
            );
        }

        $phone = trim((string) ($input['phone'] ?? ''));
        if (mb_strlen($phone) > 30) {
            throw new AdminUserManagementException(
                'Phone number cannot exceed 30 characters.'
            );
        }
        if ($phone !== '' && preg_match('/^[0-9+()\-\s.]+$/u', $phone) !== 1) {
            throw new AdminUserManagementException(
                'Phone number contains unsupported characters.'
            );
        }

        $roleIds = $input['role_ids'] ?? [];
        if (!is_array($roleIds)) {
            throw new AdminUserManagementException(
                'Roles must be provided as a list.'
            );
        }
        $roleIds = array_values(array_unique(array_map(
            static fn (mixed $value): int => (int) $value,
            $roleIds
        )));
        foreach ($roleIds as $roleId) {
            if ($roleId <= 0) {
                throw new AdminUserManagementException(
                    'A selected role is invalid.'
                );
            }
        }

        $positionId = $this->nullablePositiveInteger(
            $input['position_id'] ?? null
        );
        $unitId = $this->nullablePositiveInteger($input['unit_id'] ?? null);
        if (($positionId === null) !== ($unitId === null)) {
            throw new AdminUserManagementException(
                'Position and organizational unit must be selected together.'
            );
        }

        $effectiveDate = trim((string) (
            $input['effective_date'] ?? date('Y-m-d')
        ));
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $effectiveDate);
        if ($date === false || $date->format('Y-m-d') !== $effectiveDate) {
            throw new AdminUserManagementException(
                'Enter a valid effective date.'
            );
        }
        if ($date > new DateTimeImmutable('today')) {
            throw new AdminUserManagementException(
                'Future-dated organizational transfers are not supported yet.'
            );
        }

        $reason = trim((string) ($input['reason'] ?? ''));
        if (mb_strlen($reason) < 5 || mb_strlen($reason) > 500) {
            throw new AdminUserManagementException(
                'Change reason must contain between 5 and 500 characters.'
            );
        }

        $expectedUpdatedAt = trim((string) (
            $input['expected_updated_at'] ?? ''
        ));
        if ($expectedUpdatedAt === '' || mb_strlen($expectedUpdatedAt) > 64) {
            throw new AdminUserManagementException(
                'Reload the user record before saving changes.',
                409
            );
        }

        return [
            'university_id' => $universityId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'phone' => $phone === '' ? null : $phone,
            'is_active' => $this->requiredBoolean(
                $input['is_active'] ?? null,
                'Account status'
            ),
            'role_ids' => $roleIds,
            'position_id' => $positionId,
            'unit_id' => $unitId,
            'effective_date' => $effectiveDate,
            'reason' => $reason,
            'expected_updated_at' => $expectedUpdatedAt,
        ];
    }

    private function assertFreshVersion(
        string $expectedUpdatedAt,
        string $actualUpdatedAt
    ): void {
        if (
            $actualUpdatedAt === ''
            || $expectedUpdatedAt !== $actualUpdatedAt
        ) {
            throw new AdminUserManagementException(
                'This user was changed by another administrator. Reload the record before saving.',
                409
            );
        }
    }

    private function assertPositionCompatibleWithUnit(
        array $position,
        array $unit
    ): void {
        $positionName = strtolower(trim((string) ($position['name'] ?? '')));
        $unitCode = strtoupper(trim((string) ($unit['code'] ?? '')));
        $unitType = strtoupper(trim((string) ($unit['unit_type'] ?? '')));

        $requiredCode = match ($positionName) {
            'president',
            'president office staff',
            'president office delegate' => 'PRES',
            'vice president',
            'vice president office staff',
            'vice president office delegate' => 'VP',
            'vice president for academic affairs',
            'vice president for academic affairs office staff',
            'vice president for academic affairs office delegate' => 'VPAA',
            'legal reviewer' => 'LEGAL',
            'finance reviewer' => 'FIN',
            default => null,
        };

        if ($requiredCode !== null && $unitCode !== $requiredCode) {
            throw new AdminUserManagementException(
                sprintf(
                    'The %s position must be assigned to organizational unit %s.',
                    (string) ($position['name'] ?? 'selected'),
                    $requiredCode
                )
            );
        }

        $allowedTypes = match ($positionName) {
            'dean' => ['COLLEGE'],
            'department head', 'head of department' => ['DEPARTMENT'],
            'faculty member' => ['COLLEGE', 'DEPARTMENT'],
            'president',
            'vice president',
            'president office staff',
            'president office delegate',
            'vice president office staff',
            'vice president office delegate',
            'vice president for academic affairs',
            'vice president for academic affairs office staff',
            'vice president for academic affairs office delegate',
            'legal reviewer',
            'finance reviewer' => ['OFFICE'],
            default => [],
        };

        if ($allowedTypes !== [] && !in_array($unitType, $allowedTypes, true)) {
            throw new AdminUserManagementException(
                sprintf(
                    'The %s position is not compatible with the selected %s unit.',
                    (string) ($position['name'] ?? 'selected'),
                    strtolower($unitType ?: 'organizational')
                )
            );
        }
    }

    private function addAccessSummary(array $user): array
    {
        $permissions = $user['permissions'] ?? [];
        $user['access_summary'] = [
            'can_create_agreement' => in_array(
                'CREATE_AGREEMENT',
                $permissions,
                true
            ),
            'can_create_initiative' => in_array(
                'CREATE_INITIATIVE',
                $permissions,
                true
            ),
            'can_manage_users' => in_array(
                'MANAGE_USERS',
                $permissions,
                true
            ),
            'can_approve_agreement' => in_array(
                'APPROVE_AGREEMENT',
                $permissions,
                true
            ),
            'can_approve_initiative' => in_array(
                'APPROVE_INITIATIVE',
                $permissions,
                true
            ),
        ];

        return $user;
    }

    private function auditSnapshot(array $user): array
    {
        return [
            'user_id' => $user['user_id'] ?? null,
            'university_id' => $user['university_id'] ?? null,
            'first_name' => $user['first_name'] ?? null,
            'last_name' => $user['last_name'] ?? null,
            'email' => $user['email'] ?? null,
            'phone' => $user['phone'] ?? null,
            'is_active' => $user['is_active'] ?? null,
            'roles' => $user['roles'] ?? [],
            'permissions' => $user['permissions'] ?? [],
            'active_position' => $this->activePosition(
                $user['positions'] ?? []
            ),
        ];
    }

    private function activePosition(array $positions): ?array
    {
        foreach ($positions as $position) {
            if (($position['is_active'] ?? false) === true) {
                return $position;
            }
        }

        return null;
    }

    private function requiredText(
        mixed $value,
        string $label,
        int $maximumLength
    ): string {
        $text = trim((string) ($value ?? ''));
        if ($text === '') {
            throw new AdminUserManagementException("{$label} is required.");
        }
        if (mb_strlen($text) > $maximumLength) {
            throw new AdminUserManagementException(
                "{$label} cannot exceed {$maximumLength} characters."
            );
        }
        return $text;
    }

    private function requiredBoolean(mixed $value, string $label): bool
    {
        $normalized = filter_var(
            $value,
            FILTER_VALIDATE_BOOLEAN,
            FILTER_NULL_ON_FAILURE
        );
        if ($normalized === null) {
            throw new AdminUserManagementException("{$label} is invalid.");
        }
        return $normalized;
    }

    private function nullableBoolean(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }
        return $this->requiredBoolean($value, 'Active filter');
    }

    private function nullablePositiveInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $integer = filter_var($value, FILTER_VALIDATE_INT);
        if ($integer === false || $integer <= 0) {
            throw new AdminUserManagementException(
                'A selected identifier is invalid.'
            );
        }
        return (int) $integer;
    }
}
