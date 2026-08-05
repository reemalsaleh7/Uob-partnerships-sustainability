<?php

declare(strict_types=1);

require_once __DIR__ . '/../services/ConfigurableAgreementWorkflowService.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/ApiRequest.php';

final class ConfigurableWorkflowController
{
    private ConfigurableAgreementWorkflowService $service;

    public function __construct(?ConfigurableAgreementWorkflowService $service = null)
    {
        $this->service = $service ?? new ConfigurableAgreementWorkflowService();
    }

    public function inbox(): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $this->respond(fn (): array => $this->service->configurableInbox($userId));
    }

    public function show(int $instanceId): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $this->respond(
            fn (): array => $this->service->reviewDetail($instanceId, $userId)
        );
    }

    public function decide(int $instanceId): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $input = ApiRequest::json();
        $includeOptional = $input['include_optional_step_keys'] ?? [];
        if (!is_array($includeOptional)) {
            $includeOptional = [];
        }
        $this->respond(
            fn (): array => $this->service->decide(
                $instanceId,
                $userId,
                (string) ($input['action'] ?? ''),
                isset($input['comment']) ? (string) $input['comment'] : null,
                $includeOptional
            )
        );
    }

    private function respond(callable $operation): void
    {
        try {
            Response::success($operation());
        } catch (InvalidArgumentException $exception) {
            Response::error($exception->getMessage(), 422);
        } catch (DomainException $exception) {
            Response::error($exception->getMessage(), 409);
        } catch (PDOException $exception) {
            error_log('[Configurable Workflow database] ' . $exception->getMessage());
            Response::error('The Workflow operation could not be completed.', 500);
        }
    }
}
