<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../helpers/ApiSession.php';
require_once __DIR__ . '/../../../config/database.php';

ApiSession::start();

function initiativeDb(): PDO
{
    return Database::connect();
}

function initiativeUserId(): int
{
    foreach (['user_id', 'auth_user_id', 'workspace_user_id'] as $key) {
        if (!empty($_SESSION[$key])) {
            return (int) $_SESSION[$key];
        }
    }

    if (!empty($_SESSION['user']['user_id'])) {
        return (int) $_SESSION['user']['user_id'];
    }

    http_response_code(401);
    exit('Authentication required.');
}

function initiativeH(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function initiativeCsrf(): string
{
    if (empty($_SESSION['initiative_csrf'])) {
        $_SESSION['initiative_csrf'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['initiative_csrf'];
}

function initiativeVerifyCsrf(): void
{
    $stored = (string) ($_SESSION['initiative_csrf'] ?? '');
    $submitted = (string) ($_POST['csrf'] ?? '');

    if ($stored === '' || !hash_equals($stored, $submitted)) {
        http_response_code(419);
        exit('Invalid CSRF token.');
    }
}

function initiativeSnapshot(array $initiative): string
{
    return json_encode(
        $initiative,
        JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
    );
}

function initiativeLoad(int $initiativeId): array
{
    if ($initiativeId <= 0) {
        http_response_code(404);
        exit('Initiative not found.');
    }

    $statement = initiativeDb()->prepare(
        "SELECT i.*, u.email AS creator_email
         FROM initiatives i
         JOIN users u ON u.user_id = i.created_by
         WHERE i.initiative_id = :initiative_id"
    );

    $statement->execute([
        'initiative_id' => $initiativeId,
    ]);

    $initiative = $statement->fetch();

    if (!$initiative) {
        http_response_code(404);
        exit('Initiative not found.');
    }

    return $initiative;
}

function initiativeCanEdit(array $initiative, int $userId): bool
{
    return (int) $initiative['created_by'] === $userId
        && in_array(
            (string) $initiative['status'],
            ['DRAFT', 'REVISION_REQUIRED'],
            true
        );
}

function initiativeUserHasRole(int $userId, string $roleName): bool
{
    $statement = initiativeDb()->prepare(
        "SELECT EXISTS (
            SELECT 1
            FROM user_roles ur
            JOIN roles r ON r.role_id = ur.role_id
            WHERE ur.user_id = :user_id
              AND r.role_name = :role_name
        )"
    );

    $statement->execute([
        'user_id' => $userId,
        'role_name' => $roleName,
    ]);

    return (bool) $statement->fetchColumn();
}


function initiativeUserHasPermission(
    int $userId,
    string $permissionCode
): bool {
    $statement = initiativeDb()->prepare(
        "SELECT EXISTS (
            SELECT 1
            FROM user_roles ur
            JOIN role_permissions rp
              ON rp.role_id = ur.role_id
            JOIN permissions p
              ON p.permission_id = rp.permission_id
            WHERE ur.user_id = :user_id
              AND p.permission_code = :permission_code
        )"
    );

    $statement->execute([
        'user_id' => $userId,
        'permission_code' => $permissionCode,
    ]);

    return (bool) $statement->fetchColumn();
}

function initiativeCanCreate(int $userId): bool
{
    return initiativeUserHasPermission(
        $userId,
        'CREATE_INITIATIVE'
    );
}

function initiativeRequireCreatePermission(): void
{
    $userId = initiativeUserId();

    if (!initiativeCanCreate($userId)) {
        http_response_code(403);
        exit('You do not have permission to create initiatives.');
    }
}


function initiativeFindPositionId(string $positionName): int
{
    $statement = initiativeDb()->prepare(
        "SELECT position_id
         FROM positions
         WHERE name = :position_name
         LIMIT 1"
    );

    $statement->execute([
        'position_name' => $positionName,
    ]);

    $positionId = (int) $statement->fetchColumn();

    if ($positionId <= 0) {
        throw new RuntimeException(
            'Required position is missing: ' . $positionName
        );
    }

    return $positionId;
}

function initiativeFindUnitIdByCode(string $unitCode): int
{
    $statement = initiativeDb()->prepare(
        "SELECT unit_id
         FROM organizational_units
         WHERE code = :unit_code
           AND is_active = TRUE
         LIMIT 1"
    );

    $statement->execute([
        'unit_code' => $unitCode,
    ]);

    $unitId = (int) $statement->fetchColumn();

    if ($unitId <= 0) {
        throw new RuntimeException(
            'Required organizational unit is missing: ' . $unitCode
        );
    }

    return $unitId;
}

function initiativeActiveUserPosition(int $userId): ?array
{
    $statement = initiativeDb()->prepare(
        "SELECT
            up.position_id,
            up.unit_id,
            p.name AS position_name,
            ou.unit_type,
            ou.parent_unit_id
         FROM user_positions up
         JOIN positions p
           ON p.position_id = up.position_id
         JOIN organizational_units ou
           ON ou.unit_id = up.unit_id
         WHERE up.user_id = :user_id
           AND up.is_active = TRUE
           AND up.start_date <= CURRENT_DATE
           AND (
               up.end_date IS NULL
               OR up.end_date >= CURRENT_DATE
           )
         ORDER BY up.start_date DESC, up.user_position_id DESC
         LIMIT 1"
    );

    $statement->execute([
        'user_id' => $userId,
    ]);

    $position = $statement->fetch();

    return $position ?: null;
}

function initiativeStudentDepartment(int $userId): ?array
{
    $statement = initiativeDb()->prepare(
        "SELECT
            sp.department_id AS unit_id,
            d.parent_unit_id
         FROM student_profiles sp
         JOIN organizational_units d
           ON d.unit_id = sp.department_id
         WHERE sp.user_id = :user_id
           AND sp.is_active = TRUE
           AND d.is_active = TRUE
         LIMIT 1"
    );

    $statement->execute([
        'user_id' => $userId,
    ]);

    $department = $statement->fetch();

    return $department ?: null;
}

function initiativeAssigneeIds(
    int $positionId,
    int $unitId
): array {
    $statement = initiativeDb()->prepare(
        "SELECT DISTINCT up.user_id
         FROM user_positions up
         WHERE up.position_id = :position_id
           AND up.unit_id = :unit_id
           AND up.is_active = TRUE
           AND up.start_date <= CURRENT_DATE
           AND (
               up.end_date IS NULL
               OR up.end_date >= CURRENT_DATE
           )
         ORDER BY up.user_id"
    );

    $statement->execute([
        'position_id' => $positionId,
        'unit_id' => $unitId,
    ]);

    return array_map(
        'intval',
        $statement->fetchAll(PDO::FETCH_COLUMN)
    );
}

function initiativeBuildApprovalRoute(int $creatorId): array
{
    $departmentHeadId = initiativeFindPositionId('Department Head');
    $deanId = initiativeFindPositionId('Dean');
    $vicePresidentId = initiativeFindPositionId('Vice President');
    $presidentId = initiativeFindPositionId('President');

    $vicePresidentUnitId = initiativeFindUnitIdByCode('VP');
    $presidentUnitId = initiativeFindUnitIdByCode('PRES');

    $studentDepartment = initiativeStudentDepartment($creatorId);
    $activePosition = initiativeActiveUserPosition($creatorId);

    $departmentId = null;
    $collegeId = null;
    $creatorPositionName = 'Student';

    if ($studentDepartment) {
        $departmentId = (int) $studentDepartment['unit_id'];
        $collegeId = (int) $studentDepartment['parent_unit_id'];
    } elseif ($activePosition) {
        $creatorPositionName =
            (string) $activePosition['position_name'];

        if ($creatorPositionName === 'Faculty Member') {
            $departmentId = (int) $activePosition['unit_id'];
            $collegeId = (int) $activePosition['parent_unit_id'];
        } elseif ($creatorPositionName === 'Department Head') {
            $departmentId = (int) $activePosition['unit_id'];
            $collegeId = (int) $activePosition['parent_unit_id'];
        } elseif ($creatorPositionName === 'Dean') {
            $collegeId = (int) $activePosition['unit_id'];
        } else {
            throw new RuntimeException(
                'This position is not allowed to create an initiative workflow: '
                . $creatorPositionName
            );
        }
    } else {
        throw new RuntimeException(
            'The initiative creator has no active student profile or position.'
        );
    }

    if ($collegeId === null || $collegeId <= 0) {
        throw new RuntimeException(
            'The creator department is not linked to a college.'
        );
    }

    $route = [];

    if (
        $creatorPositionName === 'Student'
        || $creatorPositionName === 'Faculty Member'
    ) {
        if ($departmentId === null || $departmentId <= 0) {
            throw new RuntimeException(
                'The creator department could not be resolved.'
            );
        }

        $route[] = [
            'position_id' => $departmentHeadId,
            'unit_id' => $departmentId,
            'label' => 'Department Head',
        ];
    }

    if ($creatorPositionName !== 'Dean') {
        $route[] = [
            'position_id' => $deanId,
            'unit_id' => $collegeId,
            'label' => 'Dean',
        ];
    }

    $route[] = [
        'position_id' => $vicePresidentId,
        'unit_id' => $vicePresidentUnitId,
        'label' => 'Vice President',
    ];

    $route[] = [
        'position_id' => $presidentId,
        'unit_id' => $presidentUnitId,
        'label' => 'President',
    ];

    foreach ($route as &$step) {
        $step['assignee_ids'] = initiativeAssigneeIds(
            (int) $step['position_id'],
            (int) $step['unit_id']
        );

        if (!$step['assignee_ids']) {
            throw new RuntimeException(
                'No active approver is assigned for: '
                . (string) $step['label']
            );
        }
    }

    unset($step);

    return $route;
}

function initiativeInsertDynamicWorkflowSteps(
    PDO $db,
    int $workflowInstanceId,
    int $creatorId,
    array $route
): void {
    $creatorStep = $db->prepare(
        "INSERT INTO workflow_instance_steps (
            workflow_instance_id,
            step_order,
            assigned_unit_id,
            assigned_position_id,
            status,
            started_at,
            completed_at,
            approved_by,
            approved_at
         ) VALUES (
            :workflow_instance_id,
            1,
            NULL,
            NULL,
            'APPROVED',
            CURRENT_TIMESTAMP,
            CURRENT_TIMESTAMP,
            :creator_id,
            CURRENT_TIMESTAMP
         )"
    );

    $creatorStep->execute([
        'workflow_instance_id' => $workflowInstanceId,
        'creator_id' => $creatorId,
    ]);

    $stepStatement = $db->prepare(
        "INSERT INTO workflow_instance_steps (
            workflow_instance_id,
            step_order,
            assigned_unit_id,
            assigned_position_id,
            status,
            started_at
         ) VALUES (
            :workflow_instance_id,
            :step_order,
            :assigned_unit_id,
            :assigned_position_id,
            'PENDING',
            :started_at
         )
         RETURNING instance_step_id"
    );

    $assignmentStatement = $db->prepare(
        "INSERT INTO workflow_step_assignments (
            workflow_instance_step_id,
            user_id,
            is_active
         ) VALUES (
            :workflow_instance_step_id,
            :user_id,
            TRUE
         )"
    );

    foreach ($route as $index => $routeStep) {
        $stepOrder = $index + 2;

        $stepStatement->execute([
            'workflow_instance_id' => $workflowInstanceId,
            'step_order' => $stepOrder,
            'assigned_unit_id' => (int) $routeStep['unit_id'],
            'assigned_position_id' =>
                (int) $routeStep['position_id'],
            'started_at' =>
                $stepOrder === 2 ? date('Y-m-d H:i:s') : null,
        ]);

        $instanceStepId =
            (int) $stepStatement->fetchColumn();

        foreach ($routeStep['assignee_ids'] as $assigneeId) {
            $assignmentStatement->execute([
                'workflow_instance_step_id' => $instanceStepId,
                'user_id' => (int) $assigneeId,
            ]);
        }
    }
}

function initiativeActiveWorkflow(int $initiativeId, bool $forUpdate = false): ?array
{
    $sql =
        "SELECT workflow_instance_id, current_step, status, started_at
         FROM workflow_instances
         WHERE entity_type = 'INITIATIVE'
           AND entity_id = :initiative_id
           AND status = 'IN_PROGRESS'
         ORDER BY started_at DESC
         LIMIT 1";

    if ($forUpdate) {
        $sql .= ' FOR UPDATE';
    }

    $statement = initiativeDb()->prepare($sql);
    $statement->execute([
        'initiative_id' => $initiativeId,
    ]);

    $workflow = $statement->fetch();

    return $workflow ?: null;
}

function initiativeCurrentWorkflowStep(
    int $initiativeId,
    bool $forUpdate = false
): ?array {
    $sql =
        "SELECT
            wi.workflow_instance_id,
            wi.current_step,
            wis.instance_step_id,
            wis.step_order,
            wis.assigned_position_id,
            wis.assigned_unit_id,
            wis.status AS step_status,
            wis.comments
         FROM workflow_instances wi
         JOIN workflow_instance_steps wis
           ON wis.workflow_instance_id = wi.workflow_instance_id
          AND wis.step_order = wi.current_step
         WHERE wi.entity_type = 'INITIATIVE'
           AND wi.entity_id = :initiative_id
           AND wi.status = 'IN_PROGRESS'
         ORDER BY wi.started_at DESC
         LIMIT 1";

    if ($forUpdate) {
        $sql .= ' FOR UPDATE OF wi, wis';
    }

    $statement = initiativeDb()->prepare($sql);
    $statement->execute([
        'initiative_id' => $initiativeId,
    ]);

    $step = $statement->fetch();

    return $step ?: null;
}

function initiativeUserMatchesStep(int $userId, array $step): bool
{
    $assignmentStatement = initiativeDb()->prepare(
        "SELECT EXISTS (
            SELECT 1
            FROM workflow_step_assignments wsa
            WHERE wsa.workflow_instance_step_id =
                :workflow_instance_step_id
              AND wsa.user_id = :user_id
              AND wsa.is_active = TRUE
        )"
    );

    $assignmentStatement->execute([
        'workflow_instance_step_id' =>
            (int) $step['instance_step_id'],
        'user_id' => $userId,
    ]);

    if ((bool) $assignmentStatement->fetchColumn()) {
        return true;
    }

    $positionId = $step['assigned_position_id'] ?? null;
    $unitId = $step['assigned_unit_id'] ?? null;

    if ($positionId === null && $unitId === null) {
        return false;
    }

    $conditions = [
        'up.user_id = :user_id',
        'up.is_active = TRUE',
        'up.start_date <= CURRENT_DATE',
        '(up.end_date IS NULL OR up.end_date >= CURRENT_DATE)',
    ];

    $parameters = [
        'user_id' => $userId,
    ];

    if ($positionId !== null) {
        $conditions[] = 'up.position_id = :position_id';
        $parameters['position_id'] = (int) $positionId;
    }

    if ($unitId !== null) {
        $conditions[] = 'up.unit_id = :unit_id';
        $parameters['unit_id'] = (int) $unitId;
    }

    $statement = initiativeDb()->prepare(
        'SELECT EXISTS (
            SELECT 1
            FROM user_positions up
            WHERE ' . implode(' AND ', $conditions) . '
        )'
    );

    $statement->execute($parameters);

    return (bool) $statement->fetchColumn();
}

function initiativeCanDecide(int $initiativeId, int $userId): bool
{
    $step = initiativeCurrentWorkflowStep($initiativeId);

    if (!$step || (string) $step['step_status'] !== 'PENDING') {
        return false;
    }

    return initiativeUserMatchesStep($userId, $step);
}

function initiativeCanView(array $initiative, int $userId): bool
{
    if ((int) $initiative['created_by'] === $userId) {
        return true;
    }

    if (initiativeUserHasRole($userId, 'Initiative Approver')) {
        return true;
    }

    $participant = initiativeDb()->prepare(
        "SELECT EXISTS (SELECT 1 FROM initiative_participants WHERE initiative_id = :initiative_id AND user_id = :user_id)"
    );
    $participant->execute([
        'initiative_id' => (int) $initiative['initiative_id'],
        'user_id' => $userId,
    ]);
    if ((bool) $participant->fetchColumn()) {
        return true;
    }

    $statement = initiativeDb()->prepare(
        "SELECT EXISTS (
            SELECT 1
            FROM workflow_instances wi
            JOIN workflow_instance_steps wis
              ON wis.workflow_instance_id = wi.workflow_instance_id
            JOIN user_positions up
              ON up.user_id = :user_id
             AND up.is_active = TRUE
             AND up.start_date <= CURRENT_DATE
             AND (up.end_date IS NULL OR up.end_date >= CURRENT_DATE)
             AND (
                    wis.assigned_position_id IS NULL
                    OR up.position_id = wis.assigned_position_id
                 )
             AND (
                    wis.assigned_unit_id IS NULL
                    OR up.unit_id = wis.assigned_unit_id
                 )
            WHERE wi.entity_type = 'INITIATIVE'
              AND wi.entity_id = :initiative_id
        )"
    );

    $statement->execute([
        'user_id' => $userId,
        'initiative_id' => (int) $initiative['initiative_id'],
    ]);

    return (bool) $statement->fetchColumn();
}

function initiativeRequireView(array $initiative, int $userId): void
{
    if (!initiativeCanView($initiative, $userId)) {
        http_response_code(403);
        exit('You do not have permission to view this initiative.');
    }
}

function initiativeEvent(int $initiativeId, ?int $actorId, string $type, array $data = []): void
{
    $statement = initiativeDb()->prepare(
        "INSERT INTO initiative_events (initiative_id, actor_id, event_type, event_data)
         VALUES (:initiative_id, :actor_id, :event_type, CAST(:event_data AS jsonb))"
    );
    $statement->execute([
        'initiative_id' => $initiativeId,
        'actor_id' => $actorId,
        'event_type' => $type,
        'event_data' => json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
    ]);
}

function initiativeNotify(int $userId, int $initiativeId, string $type, string $title, string $message): void
{
    $statement = initiativeDb()->prepare(
        "INSERT INTO initiative_notifications
            (user_id, initiative_id, notification_type, title, message)
         VALUES (:user_id, :initiative_id, :type, :title, :message)"
    );
    $statement->execute([
        'user_id' => $userId,
        'initiative_id' => $initiativeId,
        'type' => $type,
        'title' => $title,
        'message' => $message,
    ]);
}

function initiativeParticipantIds(int $initiativeId): array
{
    $statement = initiativeDb()->prepare(
        "SELECT user_id FROM initiative_participants WHERE initiative_id = :initiative_id"
    );
    $statement->execute(['initiative_id' => $initiativeId]);
    return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
}

function initiativeCanManageParticipants(array $initiative, int $userId): bool
{
    return (int) $initiative['created_by'] === $userId
        && in_array((string) $initiative['status'], ['DRAFT', 'REVISION_REQUIRED'], true);
}

function initiativeOpenRevision(int $initiativeId): ?array
{
    $statement = initiativeDb()->prepare(
        "SELECT * FROM initiative_revision_rounds
         WHERE initiative_id = :initiative_id AND status = 'OPEN'
         ORDER BY round_number DESC LIMIT 1"
    );
    $statement->execute(['initiative_id' => $initiativeId]);
    $row = $statement->fetch();
    return $row ?: null;
}

function initiativeGenerateCode(PDO $db): string
{
    $year = date('Y');
    $statement = $db->prepare(
        "SELECT COALESCE(MAX((regexp_match(initiative_code, '[0-9]+$'))[1]::int), 0) + 1
         FROM initiatives WHERE initiative_code LIKE :prefix"
    );
    $statement->execute(['prefix' => "INIT-{$year}-%"]);
    return sprintf('INIT-%s-%05d', $year, (int) $statement->fetchColumn());
}

function initiativeNotifyCurrentApprovers(
    int $initiativeId,
    string $title
): void {
    $step = initiativeCurrentWorkflowStep($initiativeId);

    if (!$step) {
        return;
    }

    $statement = initiativeDb()->prepare(
        "SELECT DISTINCT wsa.user_id
         FROM workflow_step_assignments wsa
         WHERE wsa.workflow_instance_step_id =
             :workflow_instance_step_id
           AND wsa.is_active = TRUE
         ORDER BY wsa.user_id"
    );

    $statement->execute([
        'workflow_instance_step_id' =>
            (int) $step['instance_step_id'],
    ]);

    $userIds = array_map(
        'intval',
        $statement->fetchAll(PDO::FETCH_COLUMN)
    );

    if (!$userIds) {
        $userIds = initiativeAssigneeIds(
            (int) $step['assigned_position_id'],
            (int) $step['assigned_unit_id']
        );
    }

    foreach ($userIds as $userId) {
        initiativeNotify(
            $userId,
            $initiativeId,
            'APPROVAL_REQUIRED',
            'Initiative decision required',
            $title
        );
    }
}