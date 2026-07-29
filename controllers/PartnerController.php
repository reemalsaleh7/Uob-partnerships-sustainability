<?php

declare(strict_types=1);

require_once __DIR__ . '/../services/PartnerService.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../middleware/PermissionMiddleware.php';
require_once __DIR__ . '/../helpers/ApiRequest.php';
require_once __DIR__ . '/../helpers/Response.php';

class PartnerController
{
    private PartnerService $partnerService;

    public function __construct()
    {
        $this->partnerService = new PartnerService();
    }

    public function index(): void
    {
        AuthMiddleware::handle();
        PermissionMiddleware::require('VIEW_AGREEMENT');

        Response::success($this->partnerService->findActive());
    }

    public function create(): void
    {
        AuthMiddleware::handle();
        PermissionMiddleware::require('CREATE_AGREEMENT');

        try {
            Response::success(
                $this->partnerService->create(
                    ApiRequest::json(),
                    (int) ($_SESSION['user_id'] ?? 0)
                )
            );
        } catch (InvalidArgumentException $exception) {
            Response::error($exception->getMessage(), 422);
        }
    }

    public function lookup(): void
    {
        AuthMiddleware::handle();
        PermissionMiddleware::requireAny([
            'CREATE_AGREEMENT',
            'EDIT_AGREEMENT',
        ]);

        try {
            Response::success($this->partnerService->lookup(
                trim((string) ($_GET['name'] ?? ''))
            ));
        } catch (InvalidArgumentException $exception) {
            Response::error($exception->getMessage(), 422);
        } catch (DomainException $exception) {
            Response::error($exception->getMessage(), 404);
        } catch (RuntimeException $exception) {
            Response::error($exception->getMessage(), 503);
        }
    }

    public function agreementContext(int $partnerId): void
    {
        AuthMiddleware::handle();
        PermissionMiddleware::requireAny([
            'CREATE_AGREEMENT',
            'EDIT_AGREEMENT',
        ]);

        $exclude = filter_input(
            INPUT_GET,
            'exclude_agreement_id',
            FILTER_VALIDATE_INT
        );
        try {
            Response::success(
                $this->partnerService->agreementContext(
                    $partnerId,
                    (int) ($_SESSION['user_id'] ?? 0),
                    is_int($exclude) && $exclude > 0 ? $exclude : null
                )
            );
        } catch (DomainException $exception) {
            Response::error($exception->getMessage(), 404);
        }
    }

    public function update(int $partnerId): void
    {
        AuthMiddleware::handle();
        PermissionMiddleware::requireAny([
            'CREATE_AGREEMENT',
            'EDIT_AGREEMENT',
        ]);

        try {
            Response::success(
                $this->partnerService->update(
                    $partnerId,
                    ApiRequest::json(),
                    (int) ($_SESSION['user_id'] ?? 0)
                )
            );
        } catch (InvalidArgumentException $exception) {
            Response::error($exception->getMessage(), 422);
        } catch (DomainException $exception) {
            Response::error($exception->getMessage(), 404);
        }
    }
}
