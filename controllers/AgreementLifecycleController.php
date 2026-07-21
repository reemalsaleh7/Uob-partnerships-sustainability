<?php

declare(strict_types=1);

require_once __DIR__ . '/../services/AgreementLifecycleService.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../middleware/PermissionMiddleware.php';
require_once __DIR__ . '/../helpers/ApiRequest.php';
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

    public function documents(int $requestId): void
    {
        $this->requireView();
        try {
            Response::success($this->service->listDocuments(
                $requestId,
                $this->userId()
            ));
        } catch (DomainException $exception) {
            Response::error('Lifecycle request not found', 404);
        }
    }

    public function uploadDocument(int $requestId): void
    {
        $this->requireView();
        if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
            Response::error('Choose a document to upload', 422);
        }
        try {
            Response::success($this->service->uploadDocument(
                $requestId,
                $_FILES['file'],
                (string) ($_POST['document_type'] ?? 'OTHER'),
                $this->userId()
            ));
        } catch (InvalidArgumentException $exception) {
            Response::error($exception->getMessage(), 422);
        } catch (DomainException $exception) {
            Response::error($exception->getMessage(), 403);
        } catch (RuntimeException $exception) {
            Response::error($exception->getMessage(), 500);
        }
    }

    public function downloadDocument(int $documentId): void
    {
        $this->requireView();
        $document = $this->service->downloadDocument(
            $documentId,
            $this->userId()
        );
        if ($document === null) {
            Response::error('Document not found', 404);
        }

        $absolutePath = (string) $document['absolute_path'];
        $fileName = basename(str_replace(
            '\\',
            '/',
            (string) $document['file_name']
        ));
        $mimeType = (string) ($document['mime_type'] ?? 'application/octet-stream');
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header_remove('Content-Type');
        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . (string) filesize($absolutePath));
        header('Content-Disposition: attachment; filename="document"; filename*=UTF-8\'\'' . rawurlencode($fileName));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store, max-age=0');
        readfile($absolutePath);
        exit;
    }

    public function deleteDocument(int $documentId): void
    {
        $this->requireView();
        try {
            if (!$this->service->deleteDocument($documentId, $this->userId())) {
                Response::error('Document not found', 404);
            }
        } catch (DomainException $exception) {
            Response::error($exception->getMessage(), 403);
        }
        Response::success(['message' => 'Document deleted']);
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
        return ApiRequest::json();
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
