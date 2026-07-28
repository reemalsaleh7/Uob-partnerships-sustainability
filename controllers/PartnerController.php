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
}
