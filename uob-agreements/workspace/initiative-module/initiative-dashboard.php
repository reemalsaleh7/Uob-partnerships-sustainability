<?php

declare(strict_types=1);

require_once __DIR__ . '/initiative-common.php';
require_once __DIR__ . '/../includes/layout.php';

$uid = initiativeUserId();
$db = initiativeDb();

$canCreateInitiative = initiativeCanCreate($uid);

$mine = $db->prepare(
    "SELECT i.*
     FROM initiatives i
     WHERE i.created_by = :uid
        OR EXISTS (
            SELECT 1
            FROM initiative_participants ip
            WHERE ip.initiative_id = i.initiative_id
              AND ip.user_id = :uid
        )
     ORDER BY i.updated_at DESC"
);

$mine->execute([
    'uid' => $uid,
]);

$initiatives = $mine->fetchAll();

$pending = $db->prepare(
    "SELECT DISTINCT i.*
     FROM initiatives i
     JOIN workflow_instances wi
       ON wi.entity_type = 'INITIATIVE'
      AND wi.entity_id = i.initiative_id
      AND wi.status = 'IN_PROGRESS'
     JOIN workflow_instance_steps wis
       ON wis.workflow_instance_id = wi.workflow_instance_id
      AND wis.step_order = wi.current_step
     JOIN user_positions up
       ON up.user_id = :uid
      AND up.is_active = TRUE
      AND up.position_id = wis.assigned_position_id
      AND (
          wis.assigned_unit_id IS NULL
          OR wis.assigned_unit_id = up.unit_id
      )
     ORDER BY i.updated_at DESC"
);

$pending->execute([
    'uid' => $uid,
]);

$reviews = $pending->fetchAll();

$notificationStatement = $db->prepare(
    "SELECT COUNT(*)
     FROM initiative_notifications
     WHERE user_id = :uid
       AND is_read = FALSE"
);

$notificationStatement->execute([
    'uid' => $uid,
]);

$unread = (int) $notificationStatement->fetchColumn();

workspaceHeader('Initiatives', 'initiatives');
?>

<section class="workspace-page-header">
    <div>
        <p class="eyebrow mb-2">
            Initiatives
        </p>

        <h1 class="h2 mb-2">
            Initiative workspace
        </h1>

        <p class="text-secondary mb-0">
            Create requests, follow revisions, and complete assigned decisions.
        </p>
    </div>

    <div class="d-flex gap-2">
        <a
            class="btn btn-outline-secondary"
            href="initiative-module/notifications.php"
        >
            Notifications<?= $unread > 0 ? ' (' . $unread . ')' : '' ?>
        </a>

        <?php if ($canCreateInitiative): ?>
            <a
                class="btn btn-primary"
                href="initiative-module/initiative-create.php"
            >
                Create initiative
            </a>
        <?php endif; ?>
    </div>
</section>

<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="workspace-card form-section">
            <small class="text-secondary">
                My initiatives
            </small>

            <div class="display-6 fw-bold">
                <?= count($initiatives) ?>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="workspace-card form-section">
            <small class="text-secondary">
                Assigned decisions
            </small>

            <div class="display-6 fw-bold">
                <?= count($reviews) ?>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="workspace-card form-section">
            <small class="text-secondary">
                Unread updates
            </small>

            <div class="display-6 fw-bold">
                <?= $unread ?>
            </div>
        </div>
    </div>
</div>

<section class="workspace-card mb-4">
    <div class="workspace-card-header">
        <div>
            <h2 class="h5 mb-1">
                Assigned to me
            </h2>

            <p class="small text-secondary mb-0">
                Initiatives waiting for your decision.
            </p>
        </div>
    </div>

    <?php if (!$reviews): ?>
        <div class="form-section text-center py-5 text-secondary">
            No pending decisions.
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Status</th>
                        <th>Updated</th>
                        <th></th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($reviews as $initiative): ?>
                        <tr>
                            <td>
                                <strong>
                                    <?= initiativeH($initiative['title']) ?>
                                </strong>
                            </td>

                            <td>
                                <span class="status-badge">
                                    <?= initiativeH(
                                        str_replace(
                                            '_',
                                            ' ',
                                            (string) $initiative['status']
                                        )
                                    ) ?>
                                </span>
                            </td>

                            <td>
                                <?= initiativeH(
                                    (string) $initiative['updated_at']
                                ) ?>
                            </td>

                            <td class="text-end">
                                <a
                                    class="btn btn-sm btn-primary"
                                    href="initiative-module/initiative-view.php?id=<?= (int) $initiative['initiative_id'] ?>"
                                >
                                    Review
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="workspace-card">
    <div class="workspace-card-header">
        <div>
            <h2 class="h5 mb-1">
                My initiatives and participation
            </h2>
        </div>
    </div>

    <?php if (!$initiatives): ?>
        <div class="form-section text-center py-5 text-secondary">
            <p class="mb-3">
                No initiatives yet.
            </p>

            <?php if ($canCreateInitiative): ?>
                <a
                    class="btn btn-primary"
                    href="initiative-module/initiative-create.php"
                >
                    Create initiative
                </a>
            <?php else: ?>
                <p class="small mb-0">
                    You do not have permission to create initiatives.
                </p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Code</th>
                        <th>Status</th>
                        <th>Updated</th>
                        <th></th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($initiatives as $initiative): ?>
                        <tr>
                            <td>
                                <strong>
                                    <?= initiativeH($initiative['title']) ?>
                                </strong>
                            </td>

                            <td>
                                <?= initiativeH(
                                    $initiative['initiative_code'] ?? '—'
                                ) ?>
                            </td>

                            <td>
                                <span class="status-badge">
                                    <?= initiativeH(
                                        str_replace(
                                            '_',
                                            ' ',
                                            (string) $initiative['status']
                                        )
                                    ) ?>
                                </span>
                            </td>

                            <td>
                                <?= initiativeH(
                                    (string) $initiative['updated_at']
                                ) ?>
                            </td>

                            <td class="text-end">
                                <a
                                    class="btn btn-sm btn-outline-primary"
                                    href="initiative-module/initiative-view.php?id=<?= (int) $initiative['initiative_id'] ?>"
                                >
                                    Open
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php workspaceFooter(); ?>