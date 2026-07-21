<?php

declare(strict_types=1);

require_once __DIR__ . '/../services/AgreementPerformanceService.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../middleware/PermissionMiddleware.php';
require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../helpers/ApiRequest.php';

class AgreementPerformanceController
{
    private AgreementPerformanceService $service;

    public function __construct()
    {
        $this->service = new AgreementPerformanceService();
    }

    public function queue(): void
    {
        AuthMiddleware::handle();
        PermissionMiddleware::requireAny([
            'MANAGE_AGREEMENT_REPORTS',
            'REVIEW_AGREEMENT_REPORTS',
        ]);
        Response::success($this->service->queue($this->userId()));
    }

    public function agreementReports(int $agreementId): void
    {
        AuthMiddleware::handle();
        PermissionMiddleware::requireAny([
            'MANAGE_AGREEMENT_REPORTS',
            'REVIEW_AGREEMENT_REPORTS',
        ]);
        try {
            Response::success(
                $this->service->agreementReports($agreementId, $this->userId())
            );
        } catch (DomainException $exception) {
            Response::error($exception->getMessage(), 404);
        }
    }

    public function show(int $reportId): void
    {
        AuthMiddleware::handle();
        PermissionMiddleware::requireAny([
            'MANAGE_AGREEMENT_REPORTS',
            'REVIEW_AGREEMENT_REPORTS',
        ]);
        try {
            Response::success($this->service->report($reportId, $this->userId()));
        } catch (DomainException $exception) {
            Response::error($exception->getMessage(), 404);
        }
    }

    public function update(int $reportId): void
    {
        AuthMiddleware::handle();
        PermissionMiddleware::require('MANAGE_AGREEMENT_REPORTS');
        $input = ApiRequest::json();
        try {
            Response::success(
                $this->service->update($reportId, $this->userId(), $input)
            );
        } catch (InvalidArgumentException $exception) {
            Response::error($exception->getMessage(), 422);
        } catch (DomainException $exception) {
            Response::error($exception->getMessage(), 403);
        }
    }

    public function submit(int $reportId): void
    {
        AuthMiddleware::handle();
        PermissionMiddleware::require('MANAGE_AGREEMENT_REPORTS');
        try {
            Response::success($this->service->submit($reportId, $this->userId()));
        } catch (InvalidArgumentException $exception) {
            Response::error($exception->getMessage(), 422);
        } catch (DomainException $exception) {
            Response::error($exception->getMessage(), 403);
        }
    }

    public function review(int $reportId): void
    {
        AuthMiddleware::handle();
        PermissionMiddleware::require('REVIEW_AGREEMENT_REPORTS');
        $input = ApiRequest::json();
        try {
            Response::success($this->service->review(
                $reportId,
                $this->userId(),
                (string) ($input['decision'] ?? ''),
                isset($input['comments']) ? (string) $input['comments'] : null
            ));
        } catch (InvalidArgumentException $exception) {
            Response::error($exception->getMessage(), 422);
        } catch (DomainException $exception) {
            Response::error($exception->getMessage(), 403);
        }
    }

    public function dashboard(): void
    {
        AuthMiddleware::handle();
        PermissionMiddleware::requireAny([
            'VIEW_AGREEMENT_DASHBOARD',
            'MANAGE_AGREEMENT_REPORTS',
        ]);
        $year = isset($_GET['year'])
            ? (int) $_GET['year']
            : (int) date('Y');
        try {
            Response::success($this->service->dashboard($year, $this->userId()));
        } catch (InvalidArgumentException $exception) {
            Response::error($exception->getMessage(), 422);
        } catch (DomainException $exception) {
            Response::error($exception->getMessage(), 403);
        }
    }

    private function userId(): int
    {
        return (int) ($_SESSION['user_id'] ?? 0);
    }
}
