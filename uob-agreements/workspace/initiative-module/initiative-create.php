<?php

declare(strict_types=1);

require_once __DIR__ . '/initiative-common.php';
require_once __DIR__ . '/../includes/layout.php';

$uid = initiativeUserId();
$db = initiativeDb();

$agreements = $db->query(
    "SELECT agreement_id, title
     FROM agreements
     ORDER BY title"
)->fetchAll();

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
        $agreementId = trim((string) ($_POST['agreement_id'] ?? ''));
        $relationNotes = trim((string) ($_POST['relation_notes'] ?? ''));

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
            "INSERT INTO initiatives (
                title,
                description,
                objectives,
                initiative_type,
                expected_budget,
                planned_start_date,
                planned_end_date,
                status,
                created_by
             ) VALUES (
                :title,
                :description,
                :objectives,
                :type,
                NULLIF(:budget, '')::numeric,
                NULLIF(:start_date, '')::date,
                NULLIF(:end_date, '')::date,
                'DRAFT',
                :user_id
             )
             RETURNING initiative_id"
        );

        $statement->execute([
            'title' => $title,
            'description' => $description,
            'objectives' => $objectives,
            'type' => $type,
            'budget' => $budget,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'user_id' => $uid,
        ]);

        $initiativeId = (int) $statement->fetchColumn();
        $initiative = initiativeLoad($initiativeId);

        $version = $db->prepare(
            "INSERT INTO initiative_versions (
                initiative_id,
                version_number,
                initiative_snapshot,
                change_summary,
                created_by
             ) VALUES (
                :initiative_id,
                1,
                CAST(:snapshot AS jsonb),
                'Initial draft',
                :user_id
             )"
        );

        $version->execute([
            'initiative_id' => $initiativeId,
            'snapshot' => initiativeSnapshot($initiative),
            'user_id' => $uid,
        ]);

        if ($agreementId !== '') {
            $agreementLink = $db->prepare(
                "INSERT INTO initiative_agreements (
                    initiative_id,
                    agreement_id,
                    relation_notes
                 ) VALUES (
                    :initiative_id,
                    :agreement_id,
                    :relation_notes
                 )
                 ON CONFLICT DO NOTHING"
            );

            $agreementLink->execute([
                'initiative_id' => $initiativeId,
                'agreement_id' => (int) $agreementId,
                'relation_notes' => $relationNotes,
            ]);
        }

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

workspaceHeader('Create initiative', 'initiatives');
?>

<section class="workspace-page-header">
    <div>
        <p class="eyebrow mb-2">Initiatives</p>
        <h1 class="h2 mb-2">Create initiative</h1>
        <p class="text-secondary mb-0">
            Create a draft initiative and optionally link it to an existing Agreement.
        </p>
    </div>

    <a
        class="btn btn-outline-secondary"
        href="initiative-hub.php"
    >
        Back to Initiative hub
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
            <h2 class="h5 mb-1">Initiative information</h2>
            <p class="small text-secondary mb-0">
                Complete the basic details before saving the draft.
            </p>
        </div>
    </div>

    <form method="post" class="form-section">
        <input
            type="hidden"
            name="csrf"
            value="<?= initiativeCsrf() ?>"
        >

        <div class="row g-4">
            <div class="col-12">
                <label for="title" class="form-label">
                    Title
                </label>

                <input
                    id="title"
                    name="title"
                    type="text"
                    class="form-control"
                    value="<?= initiativeH($_POST['title'] ?? '') ?>"
                    required
                >
            </div>

            <div class="col-md-6">
                <label for="initiative_type" class="form-label">
                    Type
                </label>

                <input
                    id="initiative_type"
                    name="initiative_type"
                    type="text"
                    class="form-control"
                    value="<?= initiativeH($_POST['initiative_type'] ?? '') ?>"
                    required
                >
            </div>

            <div class="col-md-6">
                <label for="expected_budget" class="form-label">
                    Expected budget
                </label>

                <input
                    id="expected_budget"
                    name="expected_budget"
                    type="number"
                    min="0"
                    step="0.01"
                    class="form-control"
                    value="<?= initiativeH($_POST['expected_budget'] ?? '') ?>"
                >
            </div>

            <div class="col-12">
                <label for="description" class="form-label">
                    Description
                </label>

                <textarea
                    id="description"
                    name="description"
                    class="form-control"
                    rows="5"
                ><?= initiativeH($_POST['description'] ?? '') ?></textarea>
            </div>

            <div class="col-12">
                <label for="objectives" class="form-label">
                    Objectives
                </label>

                <textarea
                    id="objectives"
                    name="objectives"
                    class="form-control"
                    rows="5"
                ><?= initiativeH($_POST['objectives'] ?? '') ?></textarea>
            </div>

            <div class="col-md-6">
                <label for="planned_start_date" class="form-label">
                    Planned start
                </label>

                <input
                    id="planned_start_date"
                    name="planned_start_date"
                    type="date"
                    class="form-control"
                    value="<?= initiativeH($_POST['planned_start_date'] ?? '') ?>"
                >
            </div>

            <div class="col-md-6">
                <label for="planned_end_date" class="form-label">
                    Planned end
                </label>

                <input
                    id="planned_end_date"
                    name="planned_end_date"
                    type="date"
                    class="form-control"
                    value="<?= initiativeH($_POST['planned_end_date'] ?? '') ?>"
                >
            </div>
        </div>

        <hr class="my-4">

        <div class="row g-4">
            <div class="col-md-6">
                <label for="agreement_id" class="form-label">
                    Related Agreement
                    <span class="text-secondary">(optional)</span>
                </label>

                <select
                    id="agreement_id"
                    name="agreement_id"
                    class="form-select"
                >
                    <option value="">None</option>

                    <?php foreach ($agreements as $agreement): ?>
                        <option
                            value="<?= (int) $agreement['agreement_id'] ?>"
                            <?= (
                                (string) ($_POST['agreement_id'] ?? '')
                                === (string) $agreement['agreement_id']
                            ) ? 'selected' : '' ?>
                        >
                            <?= initiativeH($agreement['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6">
                <label for="relation_notes" class="form-label">
                    Relationship notes
                </label>

                <textarea
                    id="relation_notes"
                    name="relation_notes"
                    class="form-control"
                    rows="3"
                ><?= initiativeH($_POST['relation_notes'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-4">
            <a
                class="btn btn-outline-secondary"
                href="../initiative-hub.php"
            >
                Cancel
            </a>

            <button
                type="submit"
                class="btn btn-primary"
            >
                Create draft
            </button>
        </div>
    </form>
</section>

<?php workspaceFooter(); ?>