<?php

declare(strict_types=1);

require_once __DIR__ . '/initiative-common.php';
require_once __DIR__ . '/../includes/layout.php';

$userId = initiativeUserId();

$initiativeId = (int) (
    $_GET['id']
    ?? $_POST['initiative_id']
    ?? 0
);

$initiative = initiativeLoad($initiativeId);

if (!initiativeCanEdit($initiative, $userId)) {
    http_response_code(403);
    exit('Not editable.');
}

$db = initiativeDb();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    initiativeVerifyCsrf();

    try {
        $title = trim((string) ($_POST['title'] ?? ''));
        $type = trim((string) ($_POST['initiative_type'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $objectives = trim((string) ($_POST['objectives'] ?? ''));
        $budget = trim((string) ($_POST['expected_budget'] ?? ''));
        $startDate = trim((string) ($_POST['planned_start_date'] ?? ''));
        $endDate = trim((string) ($_POST['planned_end_date'] ?? ''));
        $changeSummary = trim(
            (string) ($_POST['change_summary'] ?? '')
        );

        if ($title === '') {
            throw new RuntimeException('Title is required.');
        }

        if ($type === '') {
            throw new RuntimeException('Initiative type is required.');
        }

        if (
            $startDate !== ''
            && $endDate !== ''
            && $endDate < $startDate
        ) {
            throw new RuntimeException(
                'Planned end date cannot be before the planned start date.'
            );
        }

        $db->beginTransaction();

        $statement = $db->prepare(
            "UPDATE initiatives
             SET
                title = :title,
                description = :description,
                objectives = :objectives,
                initiative_type = :type,
                expected_budget = NULLIF(:budget, '')::numeric,
                planned_start_date = NULLIF(:start_date, '')::date,
                planned_end_date = NULLIF(:end_date, '')::date,
                updated_at = CURRENT_TIMESTAMP
             WHERE initiative_id = :initiative_id"
        );

        $statement->execute([
            'title' => $title,
            'description' => $description,
            'objectives' => $objectives,
            'type' => $type,
            'budget' => $budget,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'initiative_id' => $initiativeId,
        ]);

        $initiative = initiativeLoad($initiativeId);

        $versionStatement = $db->prepare(
            "INSERT INTO initiative_versions (
                initiative_id,
                version_number,
                initiative_snapshot,
                change_summary,
                created_by
             )
             SELECT
                :initiative_id,
                COALESCE(MAX(version_number), 0) + 1,
                CAST(:snapshot AS jsonb),
                :change_summary,
                :user_id
             FROM initiative_versions
             WHERE initiative_id = :initiative_id"
        );

        $versionStatement->execute([
            'initiative_id' => $initiativeId,
            'snapshot' => initiativeSnapshot($initiative),
            'change_summary' => $changeSummary !== ''
                ? $changeSummary
                : 'Updated draft',
            'user_id' => $userId,
        ]);

        $db->commit();

        header(
            'Location: initiative-view.php?id=' . $initiativeId,
            true,
            303
        );
        exit;
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        $error = $exception->getMessage();
    }
}

workspaceHeader('Edit initiative', 'initiatives');
?>

<section class="workspace-page-header">
    <div>
        <p class="eyebrow mb-2">
            Initiatives
        </p>

        <h1 class="h2 mb-2">
            Edit initiative
        </h1>

        <p class="text-secondary mb-0">
            Update the initiative draft before submitting it
            for approval.
        </p>
    </div>

    <a
        class="btn btn-outline-secondary"
        href="initiative-module/initiative-view.php?id=<?= $initiativeId ?>"
    >
        Back to initiative
    </a>
</section>

<?php if ($error !== ''): ?>
    <div class="alert alert-danger" role="alert">
        <?= initiativeH($error) ?>
    </div>
<?php endif; ?>

<section class="workspace-card">
    <div class="workspace-card-header">
        <div>
            <h2 class="h5 mb-1">
                Initiative information
            </h2>

            <p class="small text-secondary mb-0">
                Update the details and save a new version
                of the draft.
            </p>
        </div>
    </div>

    <form method="post" class="form-section">
        <input
            type="hidden"
            name="csrf"
            value="<?= initiativeCsrf() ?>"
        >

        <input
            type="hidden"
            name="initiative_id"
            value="<?= $initiativeId ?>"
        >

        <div class="row g-4">
            <div class="col-12">
                <label
                    for="title"
                    class="form-label"
                >
                    Title
                </label>

                <input
                    id="title"
                    name="title"
                    type="text"
                    class="form-control"
                    value="<?= initiativeH(
                        $_POST['title']
                        ?? $initiative['title']
                    ) ?>"
                    required
                >
            </div>

            <div class="col-md-6">
                <label
                    for="initiative_type"
                    class="form-label"
                >
                    Type
                </label>

                <input
                    id="initiative_type"
                    name="initiative_type"
                    type="text"
                    class="form-control"
                    value="<?= initiativeH(
                        $_POST['initiative_type']
                        ?? $initiative['initiative_type']
                    ) ?>"
                    required
                >
            </div>

            <div class="col-md-6">
                <label
                    for="expected_budget"
                    class="form-label"
                >
                    Expected budget
                </label>

                <input
                    id="expected_budget"
                    name="expected_budget"
                    type="number"
                    min="0"
                    step="0.01"
                    class="form-control"
                    value="<?= initiativeH(
                        $_POST['expected_budget']
                        ?? (string) (
                            $initiative['expected_budget']
                            ?? ''
                        )
                    ) ?>"
                >
            </div>

            <div class="col-12">
                <label
                    for="description"
                    class="form-label"
                >
                    Description
                </label>

                <textarea
                    id="description"
                    name="description"
                    class="form-control"
                    rows="5"
                ><?= initiativeH(
                    $_POST['description']
                    ?? $initiative['description']
                    ?? ''
                ) ?></textarea>
            </div>

            <div class="col-12">
                <label
                    for="objectives"
                    class="form-label"
                >
                    Objectives
                </label>

                <textarea
                    id="objectives"
                    name="objectives"
                    class="form-control"
                    rows="5"
                ><?= initiativeH(
                    $_POST['objectives']
                    ?? $initiative['objectives']
                    ?? ''
                ) ?></textarea>
            </div>

            <div class="col-md-6">
                <label
                    for="planned_start_date"
                    class="form-label"
                >
                    Planned start
                </label>

                <input
                    id="planned_start_date"
                    name="planned_start_date"
                    type="date"
                    class="form-control"
                    value="<?= initiativeH(
                        $_POST['planned_start_date']
                        ?? (string) (
                            $initiative['planned_start_date']
                            ?? ''
                        )
                    ) ?>"
                >
            </div>

            <div class="col-md-6">
                <label
                    for="planned_end_date"
                    class="form-label"
                >
                    Planned end
                </label>

                <input
                    id="planned_end_date"
                    name="planned_end_date"
                    type="date"
                    class="form-control"
                    value="<?= initiativeH(
                        $_POST['planned_end_date']
                        ?? (string) (
                            $initiative['planned_end_date']
                            ?? ''
                        )
                    ) ?>"
                >
            </div>

            <div class="col-12">
                <label
                    for="change_summary"
                    class="form-label"
                >
                    Change summary
                </label>

                <input
                    id="change_summary"
                    name="change_summary"
                    type="text"
                    class="form-control"
                    value="<?= initiativeH(
                        $_POST['change_summary']
                        ?? ''
                    ) ?>"
                    placeholder="Describe what changed in this version"
                >
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-4">
            <a
                class="btn btn-outline-secondary"
                href="initiative-module/initiative-view.php?id=<?= $initiativeId ?>"
            >
                Cancel
            </a>

            <button
                type="submit"
                class="btn btn-primary"
            >
                Save changes
            </button>
        </div>
    </form>
</section>

<?php workspaceFooter(); ?>