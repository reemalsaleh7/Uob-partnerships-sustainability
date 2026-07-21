<?php

declare(strict_types=1);

require_once __DIR__ . '/../services/AgreementOperationService.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../middleware/PermissionMiddleware.php';
require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../helpers/ApiRequest.php';

class AgreementOperationController
{
    private AgreementOperationService $service;

    public function __construct()
    {
        $this->service = new AgreementOperationService();
    }

    public function summary(int $agreementId): void
    {
        AuthMiddleware::handle();
        PermissionMiddleware::require('VIEW_AGREEMENT');
        $summary = $this->service->summary($agreementId, $this->userId());
        if ($summary === null) {
            Response::error('Agreement not found', 404);
        }
        Response::success($summary);
    }

    public function finalize(int $agreementId): void
    {
        AuthMiddleware::handle();
        PermissionMiddleware::require('MANAGE_AGREEMENT_OPERATIONS');
        $input = ApiRequest::json();
        try {
            Response::success($this->service->finalizeSigning(
                $agreementId,
                $this->userId(),
                $input
            ));
        } catch (InvalidArgumentException $exception) {
            Response::error($exception->getMessage(), 422);
        } catch (DomainException $exception) {
            Response::error($exception->getMessage(), 403);
        } catch (RuntimeException $exception) {
            Response::error($exception->getMessage(), 500);
        }
    }

    private function userId(): int
    {
        return (int) ($_SESSION['user_id'] ?? 0);
    }
}
