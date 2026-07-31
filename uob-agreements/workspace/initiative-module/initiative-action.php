<?php

declare(strict_types=1);

require_once __DIR__ . '/initiative-common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

$userId = initiativeUserId();

initiativeVerifyCsrf();

$initiativeId = (int) ($_POST['initiative_id'] ?? 0);
$action = trim((string) ($_POST['action'] ?? ''));
$comments = trim((string) ($_POST['comments'] ?? ''));

$db = initiativeDb();

try {
    $db->beginTransaction();

    $initiativeStatement = $db->prepare(
        "SELECT *
         FROM initiatives
         WHERE initiative_id = :initiative_id
         FOR UPDATE"
    );

    $initiativeStatement->execute([
        'initiative_id' => $initiativeId,
    ]);

    $initiative = $initiativeStatement->fetch();

    if (!$initiative) {
        throw new RuntimeException('Initiative not found.');
    }

    initiativeRequireView($initiative, $userId);

    if ($action === 'submit') {
        if (!initiativeCanEdit($initiative, $userId)) {
            throw new RuntimeException(
                'Initiative cannot be submitted.'
            );
        }

        if (initiativeActiveWorkflow($initiativeId, true)) {
            throw new RuntimeException(
                'This initiative already has an active approval workflow.'
            );
        }

        $templateStatement = $db->query(
            "SELECT workflow_template_id
             FROM workflow_templates
             WHERE name = 'Initiative Approval'
               AND is_active = TRUE
             ORDER BY workflow_template_id DESC
             LIMIT 1"
        );

        $workflowTemplateId = (int) $templateStatement->fetchColumn();

        if ($workflowTemplateId <= 0) {
            throw new RuntimeException(
                'Initiative Approval workflow template is missing.'
            );
        }

        $route = initiativeBuildApprovalRoute($userId);

        $workflowStatement = $db->prepare(
            "INSERT INTO workflow_instances (
                workflow_template_id,
                entity_type,
                entity_id,
                current_step,
                status,
                started_by
             ) VALUES (
                :workflow_template_id,
                'INITIATIVE',
                :initiative_id,
                2,
                'IN_PROGRESS',
                :started_by
             )
             RETURNING workflow_instance_id"
        );

        $workflowStatement->execute([
            'workflow_template_id' => $workflowTemplateId,
            'initiative_id' => $initiativeId,
            'started_by' => $userId,
        ]);

        $workflowInstanceId =
            (int) $workflowStatement->fetchColumn();

        initiativeInsertDynamicWorkflowSteps(
            $db,
            $workflowInstanceId,
            $userId,
            $route
        );

        $openRevision = initiativeOpenRevision($initiativeId);

        if ($openRevision) {
            $revisionStatement = $db->prepare(
                "UPDATE initiative_revision_rounds
                 SET status = 'RESUBMITTED',
                     resolved_at = CURRENT_TIMESTAMP,
                     resolved_by = :resolved_by
                 WHERE revision_round_id = :revision_round_id"
            );

            $revisionStatement->execute([
                'resolved_by' => $userId,
                'revision_round_id' =>
                    (int) $openRevision['revision_round_id'],
            ]);

            $eventType = 'RESUBMITTED';
        } else {
            $eventType = 'SUBMITTED';
        }

        $updateInitiative = $db->prepare(
            "UPDATE initiatives
             SET status = 'UNDER_REVIEW',
                 submitted_at = COALESCE(
                     submitted_at,
                     CURRENT_TIMESTAMP
                 ),
                 updated_at = CURRENT_TIMESTAMP
             WHERE initiative_id = :initiative_id"
        );

        $updateInitiative->execute([
            'initiative_id' => $initiativeId,
        ]);

        initiativeEvent(
            $initiativeId,
            $userId,
            $eventType,
            [
                'workflow_instance_id' => $workflowInstanceId,
            ]
        );

        initiativeNotifyCurrentApprovers(
            $initiativeId,
            (string) $initiative['title']
        );
    } else {
        $workflow = initiativeActiveWorkflow(
            $initiativeId,
            true
        );

        if (!$workflow) {
            throw new RuntimeException('No active workflow.');
        }

        $step = initiativeCurrentWorkflowStep(
            $initiativeId,
            true
        );

        if (!$step) {
            throw new RuntimeException(
                'Current workflow step is missing.'
            );
        }

        if (!initiativeUserMatchesStep($userId, $step)) {
            throw new RuntimeException(
                'You are not assigned to the current approval step.'
            );
        }

        if ((string) $step['step_status'] !== 'PENDING') {
            throw new RuntimeException(
                'This approval step has already been completed.'
            );
        }

        if (
            in_array(
                $action,
                ['request_changes', 'reject'],
                true
            )
            && $comments === ''
        ) {
            throw new RuntimeException(
                'Comments are required for this decision.'
            );
        }

        if ($action === 'approve') {
            $approveStatement = $db->prepare(
                "UPDATE workflow_instance_steps
                 SET status = 'APPROVED',
                     approved_by = :approved_by,
                     approved_at = CURRENT_TIMESTAMP,
                     completed_at = CURRENT_TIMESTAMP,
                     comments = :comments
                 WHERE instance_step_id = :instance_step_id"
            );

            $approveStatement->execute([
                'approved_by' => $userId,
                'comments' => $comments,
                'instance_step_id' =>
                    (int) $step['instance_step_id'],
            ]);

            $maximumStepStatement = $db->prepare(
                "SELECT MAX(step_order)
                 FROM workflow_instance_steps
                 WHERE workflow_instance_id =
                     :workflow_instance_id"
            );

            $maximumStepStatement->execute([
                'workflow_instance_id' =>
                    (int) $workflow['workflow_instance_id'],
            ]);

            $maximumStep =
                (int) $maximumStepStatement->fetchColumn();

            if ((int) $step['step_order'] >= $maximumStep) {
                $completeWorkflow = $db->prepare(
                    "UPDATE workflow_instances
                     SET status = 'COMPLETED',
                         completed_at = CURRENT_TIMESTAMP
                     WHERE workflow_instance_id =
                         :workflow_instance_id"
                );

                $completeWorkflow->execute([
                    'workflow_instance_id' =>
                        (int) $workflow['workflow_instance_id'],
                ]);

                $approveInitiative = $db->prepare(
                    "UPDATE initiatives
                     SET status = 'APPROVED',
                         final_decision_at = CURRENT_TIMESTAMP,
                         updated_at = CURRENT_TIMESTAMP
                     WHERE initiative_id = :initiative_id"
                );

                $approveInitiative->execute([
                    'initiative_id' => $initiativeId,
                ]);

                initiativeEvent(
                    $initiativeId,
                    $userId,
                    'APPROVED',
                    [
                        'comments' => $comments,
                    ]
                );

                initiativeNotify(
                    (int) $initiative['created_by'],
                    $initiativeId,
                    'APPROVED',
                    'Initiative request approved',
                    (string) $initiative['title']
                );
            } else {
                $nextStep = (int) $step['step_order'] + 1;

                $moveWorkflow = $db->prepare(
                    "UPDATE workflow_instances
                     SET current_step = :current_step
                     WHERE workflow_instance_id =
                         :workflow_instance_id"
                );

                $moveWorkflow->execute([
                    'current_step' => $nextStep,
                    'workflow_instance_id' =>
                        (int) $workflow['workflow_instance_id'],
                ]);

                $startNextStep = $db->prepare(
                    "UPDATE workflow_instance_steps
                     SET started_at = COALESCE(
                         started_at,
                         CURRENT_TIMESTAMP
                     )
                     WHERE workflow_instance_id =
                         :workflow_instance_id
                       AND step_order = :step_order"
                );

                $startNextStep->execute([
                    'workflow_instance_id' =>
                        (int) $workflow['workflow_instance_id'],
                    'step_order' => $nextStep,
                ]);

                initiativeEvent(
                    $initiativeId,
                    $userId,
                    'STEP_APPROVED',
                    [
                        'step' => (int) $step['step_order'],
                        'comments' => $comments,
                    ]
                );

                initiativeNotifyCurrentApprovers(
                    $initiativeId,
                    (string) $initiative['title']
                );
            }
        } elseif ($action === 'request_changes') {
            $requestChangesStep = $db->prepare(
                "UPDATE workflow_instance_steps
                 SET status = 'CHANGES_REQUESTED',
                     completed_at = CURRENT_TIMESTAMP,
                     comments = :comments
                 WHERE instance_step_id = :instance_step_id"
            );

            $requestChangesStep->execute([
                'comments' => $comments,
                'instance_step_id' =>
                    (int) $step['instance_step_id'],
            ]);

            $requestChangesWorkflow = $db->prepare(
                "UPDATE workflow_instances
                 SET status = 'CHANGES_REQUESTED',
                     completed_at = CURRENT_TIMESTAMP
                 WHERE workflow_instance_id =
                     :workflow_instance_id"
            );

            $requestChangesWorkflow->execute([
                'workflow_instance_id' =>
                    (int) $workflow['workflow_instance_id'],
            ]);

            $requestChangesInitiative = $db->prepare(
                "UPDATE initiatives
                 SET status = 'REVISION_REQUIRED',
                     revision_count = revision_count + 1,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE initiative_id = :initiative_id"
            );

            $requestChangesInitiative->execute([
                'initiative_id' => $initiativeId,
            ]);

            $revisionRoundStatement = $db->prepare(
                "INSERT INTO initiative_revision_rounds (
                    initiative_id,
                    workflow_instance_id,
                    round_number,
                    requested_by,
                    request_comments
                 )
                 SELECT
                    :initiative_id,
                    :workflow_instance_id,
                    COALESCE(MAX(round_number), 0) + 1,
                    :requested_by,
                    :request_comments
                 FROM initiative_revision_rounds
                 WHERE initiative_id = :initiative_id
                 RETURNING revision_round_id"
            );

            $revisionRoundStatement->execute([
                'initiative_id' => $initiativeId,
                'workflow_instance_id' =>
                    (int) $workflow['workflow_instance_id'],
                'requested_by' => $userId,
                'request_comments' => $comments,
            ]);

            initiativeEvent(
                $initiativeId,
                $userId,
                'CHANGES_REQUESTED',
                [
                    'comments' => $comments,
                ]
            );

            initiativeNotify(
                (int) $initiative['created_by'],
                $initiativeId,
                'CHANGES_REQUESTED',
                'Changes requested',
                (string) $initiative['title']
            );
        } elseif ($action === 'reject') {
            $rejectStep = $db->prepare(
                "UPDATE workflow_instance_steps
                 SET status = 'REJECTED',
                     approved_by = :rejected_by,
                     completed_at = CURRENT_TIMESTAMP,
                     comments = :comments
                 WHERE instance_step_id = :instance_step_id"
            );

            $rejectStep->execute([
                'rejected_by' => $userId,
                'comments' => $comments,
                'instance_step_id' =>
                    (int) $step['instance_step_id'],
            ]);

            $rejectWorkflow = $db->prepare(
                "UPDATE workflow_instances
                 SET status = 'REJECTED',
                     completed_at = CURRENT_TIMESTAMP
                 WHERE workflow_instance_id =
                     :workflow_instance_id"
            );

            $rejectWorkflow->execute([
                'workflow_instance_id' =>
                    (int) $workflow['workflow_instance_id'],
            ]);

            $rejectInitiative = $db->prepare(
                "UPDATE initiatives
                 SET status = 'REJECTED',
                     final_decision_at = CURRENT_TIMESTAMP,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE initiative_id = :initiative_id"
            );

            $rejectInitiative->execute([
                'initiative_id' => $initiativeId,
            ]);

            initiativeEvent(
                $initiativeId,
                $userId,
                'REJECTED',
                [
                    'comments' => $comments,
                ]
            );

            initiativeNotify(
                (int) $initiative['created_by'],
                $initiativeId,
                'REJECTED',
                'Initiative request rejected',
                (string) $initiative['title']
            );
        } else {
            throw new RuntimeException('Unknown action.');
        }
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

    http_response_code(400);
    exit(initiativeH($exception->getMessage()));
}
