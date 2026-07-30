<?php

declare(strict_types=1);

require_once __DIR__ . '/initiative-common.php';
require_once __DIR__ . '/../includes/layout.php';

$userId = initiativeUserId();
$db = initiativeDb();

$statement = $db->prepare(
    "SELECT
        initiative_id,
        title,
        initiative_type,
        status,
        created_at,
        updated_at
     FROM initiatives
     WHERE created_by = :user_id
     ORDER BY updated_at DESC, created_at DESC"
);

$statement->execute([
    'user_id' => $userId,
]);

$initiatives = $statement->fetchAll();

workspaceHeader('Initiatives', 'initiatives');
?>

<section class="workspace-page-header">
    <div>
        <p class="eyebrow mb-2">Initiatives</p>

        <h1 class="h2 mb-2">
            My initiatives
        </h1>

        <p class="text-secondary mb-0">
            Create initiatives and follow their approval progress.
        </p>
    </div>

    <a
        class="btn btn-primary"
        href="initiative-module/initiative-create.php"
    >
        Create initiative
    </a>
</section>

<section class="workspace-card">
    <div class="workspace-card-header">
        <div>
            <h2 class="h5 mb-1">
                Initiative drafts and requests
            </h2>

            <p class="small text-secondary mb-0">
                Initiatives created using your account.
            </p>
        </div>
    </div>

    <?php if (!$initiatives): ?>
        <div class="form-section text-center py-5">
            <h3 class="h5">
                No initiatives yet
            </h3>

            <p class="text-secondary">
                Create your first initiative draft to get started.
            </p>

            <a
                class="btn btn-primary"
                href="initiative-module/initiative-create.php"
            >
                Create initiative
            </a>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Updated</th>
                        <th class="text-end">
                            Action
                        </th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($initiatives as $initiative): ?>
                        <tr>
                            <td>
                                <strong>
                                    <?= initiativeH(
                                        $initiative['title']
                                    ) ?>
                                </strong>
                            </td>

                            <td>
                                <?= initiativeH(
                                    $initiative['initiative_type']
                                    ?? '—'
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
                                    $initiative['updated_at']
                                    ?? $initiative['created_at']
                                    ?? '—'
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