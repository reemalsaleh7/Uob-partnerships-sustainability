<?php

declare(strict_types=1);

require_once __DIR__ . '/../services/AgreementLifecycleService.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../middleware/PermissionMiddleware.php';
require_once __DIR__ . '/../helpers/Response.php';

class AgreementLifecycleController
{
    private AgreementLifecycleService $service;

    public function __construct()
    {
        $this->service = new AgreementLifecycleService();
    }

    public function index(): void
    {
        $this->requireView();
        Response::success($this->service->findAll($this->userId()));
    }

    public function show(int $requestId): void
    {
        $this->requireView();
        $request = $this->service->findByIdForUser($requestId, $this->userId());
        if ($request === null) {
            Response::error('Lifecycle request not found', 404);
        }
        Response::success($request);
    }

    public function versions(int $requestId): void
    {
        $this->requireView();
        $versions = $this->service->findVersionsForUser($requestId, $this->userId());
        if ($versions === null) {
            Response::error('Lifecycle request not found', 404);
        }
        Response::success($versions);
    }

    public function create(int $agreementId): void
    {
        AuthMiddleware::handle();
        PermissionMiddleware::require('CREATE_AGREEMENT');
        $result = $this->service->create(
            $agreementId,
            $this->userId(),
            $this->input()
        );
        $this->respond($result);
    }

    public function update(int $requestId): void
    {
        AuthMiddleware::handle();
        PermissionMiddleware::require('EDIT_AGREEMENT');
        $result = $this->service->update(
            $requestId,
            $this->userId(),
            $this->input()
        );
        $this->respond($result);
    }

    public function submit(int $requestId): void
    {
        AuthMiddleware::handle();
        PermissionMiddleware::require('SUBMIT_AGREEMENT');
        $this->domainResponse(fn (): array => $this->service->submit(
            $requestId,
            $this->userId()
        ));
    }

    public function decide(int $instanceId): void
    {
        AuthMiddleware::handle();
        PermissionMiddleware::requireAny(['APPROVE_AGREEMENT', 'REJECT_AGREEMENT']);
        $input = $this->input();
        $this->domainResponse(fn (): array => $this->service->decide(
            $instanceId,
            $this->userId(),
            (string) ($input['action'] ?? ''),
            isset($input['comments']) ? (string) $input['comments'] : null,
            (bool) ($input['include_finance'] ?? false)
        ));
    }

    private function requireView(): void
    {
        AuthMiddleware::handle();
        PermissionMiddleware::require('VIEW_AGREEMENT');
    }

    private function input(): array
    {
        return json_decode(file_get_contents('php://input'), true) ?? [];
    }

    private function userId(): int
    {
        return (int) ($_SESSION['user_id'] ?? 0);
    }

    private function respond(array $result): void
    {
        if (!($result['success'] ?? false)) {
            Response::error(implode(', ', $result['errors'] ?? ['Request failed']), 422);
        }
        Response::success($result);
    }

    private function domainResponse(callable $operation): void
    {
        try {
            $this->respond($operation());
        } catch (InvalidArgumentException $exception) {
            Response::error($exception->getMessage(), 422);
        } catch (DomainException $exception) {
            Response::error($exception->getMessage(), 403);
        }
    }
}
