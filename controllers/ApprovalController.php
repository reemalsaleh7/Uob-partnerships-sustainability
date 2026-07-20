<?php

declare(strict_types=1);

require_once __DIR__ . '/../services/ApprovalService.php';
require_once __DIR__ . '/../repositories/WorkflowRepository.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../middleware/PermissionMiddleware.php';
require_once __DIR__ . '/../helpers/Response.php';

class ApprovalController
{
    private ApprovalService $approvalService;
    private WorkflowRepository $workflowRepository;

    public function __construct()
    {
        $this->approvalService =
            new ApprovalService();

        $this->workflowRepository =
            new WorkflowRepository();
    }

    public function inbox(): void
    {
        AuthMiddleware::handle();

        PermissionMiddleware::requireAny([
            'APPROVE_AGREEMENT',
            'REJECT_AGREEMENT',
        ]);

        $userId =
            (int) ($_SESSION['user_id'] ?? 0);

        Response::success(
            $this->workflowRepository
                ->findInboxForUser($userId)
        );
    }

    public function approveInitialVp(
        int $instanceId
    ): void {
        $this->requireApprovalPermission();

        $input = $this->input();

        $this->respond(function () use (
            $instanceId,
            $input
        ): array {
            return $this->approvalService
                ->completeInitialVpReview(
                    $instanceId,
                    $this->userId(),
                    (bool) (
                        $input[
                            'include_finance'
                        ] ?? false
                    ),
                    $this->optionalString(
                        $input['comments'] ?? null
                    )
                );
        });
    }

    public function approveSpecialist(
        int $instanceId
    ): void {
        $this->requireApprovalPermission();

        $input = $this->input();

        $this->respond(function () use (
            $instanceId,
            $input
        ): array {
            return $this->approvalService
                ->completeSpecialistReview(
                    $instanceId,
                    (string) (
                        $input['step_key'] ?? ''
                    ),
                    $this->userId(),
                    $this->optionalString(
                        $input['comments'] ?? null
                    )
                );
        });
    }

    public function approveFinalVp(
        int $instanceId
    ): void {
        $this->requireApprovalPermission();

        $input = $this->input();

        $this->respond(function () use (
            $instanceId,
            $input
        ): array {
            return $this->approvalService
                ->completeFinalVpReview(
                    $instanceId,
                    $this->userId(),
                    $this->optionalString(
                        $input['comments'] ?? null
                    )
                );
        });
    }

    public function approvePresident(
        int $instanceId
    ): void {
        $this->requireApprovalPermission();

        $input = $this->input();

        $this->respond(function () use (
            $instanceId,
            $input
        ): array {
            return $this->approvalService
                ->completePresidentApproval(
                    $instanceId,
                    $this->userId(),
                    $this->optionalString(
                        $input['comments'] ?? null
                    )
                );
        });
    }

    public function requestChanges(
        int $instanceId
    ): void {
        $this->requireApprovalPermission();

        $input = $this->input();

        $this->respond(function () use (
            $instanceId,
            $input
        ): array {
            return $this->approvalService
                ->requestAgreementChanges(
                    $instanceId,
                    (string) (
                        $input['step_key'] ?? ''
                    ),
                    $this->userId(),
                    (string) (
                        $input['reason'] ?? ''
                    )
                );
        });
    }

    public function routeByVp(
        int $instanceId
    ): void {
        AuthMiddleware::handle();

        PermissionMiddleware::requireAny([
            'APPROVE_AGREEMENT',
            'REJECT_AGREEMENT',
        ]);

        $input = $this->input();

        $this->respond(function () use (
            $instanceId,
            $input
        ): array {
            return $this->approvalService
                ->routeAgreementChangeRequest(
                    $instanceId,
                    $this->userId(),
                    (string) (
                        $input['destination'] ?? ''
                    ),
                    (string) (
                        $input['reason'] ?? ''
                    )
                );
        });
    }

    public function resubmitRedraft(
        int $instanceId
    ): void {
        AuthMiddleware::handle();

        PermissionMiddleware::require(
            'SUBMIT_AGREEMENT'
        );

        $input = $this->input();

        $this->respond(function () use (
            $instanceId,
            $input
        ): array {
            return $this->approvalService
                ->resubmitAgreementAfterRedraft(
                    $instanceId,
                    $this->userId(),
                    $this->optionalString(
                        $input['comments'] ?? null
                    )
                );
        });
    }

    public function decideVpOutcome(
        int $instanceId
    ): void {
        AuthMiddleware::handle();

        PermissionMiddleware::requireAny([
            'APPROVE_AGREEMENT',
            'REJECT_AGREEMENT',
        ]);

        $input = $this->input();

        $this->respond(function () use (
            $instanceId,
            $input
        ): array {
            return $this->approvalService
                ->decideVpReviewOutcome(
                    $instanceId,
                    (string) (
                        $input['step_key'] ?? ''
                    ),
                    $this->userId(),
                    (string) (
                        $input['decision'] ?? ''
                    ),
                    (string) (
                        $input['reason'] ?? ''
                    )
                );
        });
    }

    public function rejectPresident(
        int $instanceId
    ): void {
        AuthMiddleware::handle();

        PermissionMiddleware::require(
            'REJECT_AGREEMENT'
        );

        $input = $this->input();

        $this->respond(function () use (
            $instanceId,
            $input
        ): array {
            return $this->approvalService
                ->rejectAgreementByPresident(
                    $instanceId,
                    $this->userId(),
                    (string) (
                        $input['reason'] ?? ''
                    )
                );
        });
    }

    private function requireApprovalPermission(): void
    {
        AuthMiddleware::handle();

        PermissionMiddleware::require(
            'APPROVE_AGREEMENT'
        );
    }

    private function respond(callable $operation): void
    {
        try {
            Response::success($operation());
        } catch (InvalidArgumentException $exception) {
            Response::error(
                $exception->getMessage(),
                422
            );
        } catch (DomainException $exception) {
            Response::error(
                $exception->getMessage(),
                409
            );
        }
    }

    private function input(): array
    {
        $input = json_decode(
            file_get_contents('php://input'),
            true
        );

        if (!is_array($input)) {
            return [];
        }

        return $input;
    }

    private function userId(): int
    {
        return (int) (
            $_SESSION['user_id'] ?? 0
        );
    }

    private function optionalString(
        mixed $value
    ): ?string {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === ''
            ? null
            : $value;
    }
}