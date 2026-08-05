<?php

declare(strict_types=1);

require_once __DIR__ . '/../services/AdminWorkflowTemplateService.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/ApiRequest.php';

final class AdminWorkflowTemplateController
{
    private AdminWorkflowTemplateService $service;

    public function __construct(?AdminWorkflowTemplateService $service = null)
    {
        $this->service = $service ?? new AdminWorkflowTemplateService();
    }

    public function index(): void
    {
        $this->respond(fn (): array => $this->service->index());
    }

    public function options(): void
    {
        $this->respond(fn (): array => $this->service->options());
    }

    public function show(string $templateKey): void
    {
        $this->respond(fn (): array => $this->service->show($templateKey));
    }

    public function version(string $templateKey, int $templateId): void
    {
        $this->respond(
            fn (): array => $this->service->version($templateKey, $templateId)
        );
    }

    public function publish(string $templateKey): void
    {
        $input = ApiRequest::json();
        $actorUserId = (int) ($_SESSION['user_id'] ?? 0);
        $this->respond(
            fn (): array => $this->service->publish(
                $actorUserId,
                $templateKey,
                $input
            )
        );
    }

    private function respond(callable $operation): void
    {
        try {
            Response::success($operation());
        } catch (AdminWorkflowTemplateException $exception) {
            Response::error(
                $exception->getMessage(),
                $exception->httpStatus()
            );
        } catch (PDOException $exception) {
            error_log(
                '[Admin Workflow template database] '
                . $exception->getMessage()
            );
            Response::error(
                'The Workflow template could not be saved.',
                500
            );
        }
    }
}
