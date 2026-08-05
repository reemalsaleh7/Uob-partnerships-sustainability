<?php

declare(strict_types=1);

require_once __DIR__ . '/../services/AdminUserService.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/ApiRequest.php';

final class AdminUserController
{
    private AdminUserService $service;

    public function __construct(?AdminUserService $service = null)
    {
        $this->service = $service ?? new AdminUserService();
    }

    public function index(): void
    {
        $this->respond(fn (): array => $this->service->listUsers($_GET));
    }

    public function options(): void
    {
        $this->respond(fn (): array => $this->service->options());
    }

    public function show(int $userId): void
    {
        $this->respond(fn (): array => $this->service->user($userId));
    }

    public function update(int $userId): void
    {
        $input = ApiRequest::json();
        $actorUserId = (int) ($_SESSION['user_id'] ?? 0);

        $this->respond(
            fn (): array => $this->service->updateUser(
                $actorUserId,
                $userId,
                $input
            )
        );
    }

    private function respond(callable $operation): void
    {
        try {
            Response::success($operation());
        } catch (AdminUserManagementException $exception) {
            Response::error(
                $exception->getMessage(),
                $exception->httpStatus()
            );
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23505') {
                Response::error(
                    'University ID or email is already assigned to another user.',
                    409
                );
            }

            if (
                in_array($exception->getCode(), ['23514', 'P0001'], true)
                && stripos($exception->getMessage(), 'position') !== false
            ) {
                Response::error(
                    'The selected unique position already has an active holder in this organizational unit.',
                    409
                );
            }

            error_log('[Admin user management database] ' . $exception->getMessage());
            Response::error('The user could not be saved.', 500);
        }
    }
}
