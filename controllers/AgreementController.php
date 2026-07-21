<?php
require_once __DIR__ . '/../services/AgreementService.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../middleware/PermissionMiddleware.php';
require_once __DIR__ . '/../helpers/Response.php';

class AgreementController {
    private AgreementService $agreementService;

    public function __construct() {
        $this->agreementService = new AgreementService();
    }

    public function index(): void {
        AuthMiddleware::handle();
        PermissionMiddleware::require('VIEW_AGREEMENT');

        Response::success(
            $this->agreementService->findAll((int) $_SESSION['user_id'])
        );
    }

    public function show(int $agreementId): void {
        AuthMiddleware::handle();
        PermissionMiddleware::require('VIEW_AGREEMENT');

        $agreement = $this->agreementService->findByIdForUser(
            $agreementId,
            (int) $_SESSION['user_id']
        );
        if (!$agreement) {
            Response::error('Agreement not found', 404);
        }

        Response::success($agreement);
    }

    public function create(): void {
        AuthMiddleware::handle();
        PermissionMiddleware::require('CREATE_AGREEMENT');

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $data = [
            'title' => $input['title'] ?? null,
            'agreement_type' => $input['agreement_type'] ?? null,
            'description' => $input['description'] ?? null,
            'partner_id' => $input['partner_id'] ?? null,
            'created_by' => (int) ($_SESSION['user_id'] ?? 0),
        ];

        $result = $this->agreementService->createAgreement($data);
        if (!$result['success']) {
            Response::error(implode(', ', $result['errors']), 422);
        }

        Response::success(['agreement_id' => $result['agreement_id']]);
    }

    public function update(int $agreementId): void {
        AuthMiddleware::handle();
        PermissionMiddleware::require('EDIT_AGREEMENT');

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $data = [
            'title' => $input['title'] ?? null,
            'agreement_type' => $input['agreement_type'] ?? null,
            'description' => $input['description'] ?? null,
            'partner_id' => $input['partner_id'] ?? null,
            'change_summary' => $input['change_summary'] ?? null,
            'updated_by' => (int) ($_SESSION['user_id'] ?? 0),
        ];

        $result = $this->agreementService->updateAgreement($agreementId, $data);
        if (!$result['success']) {
            Response::error(implode(', ', $result['errors']), 422);
        }

        Response::success(['message' => 'Agreement updated']);
    }

    public function submit(int $agreementId): void
{
    AuthMiddleware::handle();

    PermissionMiddleware::require(
        'SUBMIT_AGREEMENT'
    );

    $result =
        $this->agreementService
            ->submitAgreement(
                $agreementId,
                (int) ($_SESSION['user_id'] ?? 0)
            );

    if (!$result['success']) {
        Response::error(
            implode(', ', $result['errors']),
            422
        );
    }

    Response::success($result);
}

    public function resubmit(int $agreementId): void
    {
        AuthMiddleware::handle();
        PermissionMiddleware::require('SUBMIT_AGREEMENT');

        $input = json_decode(
            file_get_contents('php://input'),
            true
        ) ?? [];

        $comments = isset($input['comments'])
            ? trim((string) $input['comments'])
            : null;

        $result = $this->agreementService->resubmitAgreement(
            $agreementId,
            (int) ($_SESSION['user_id'] ?? 0),
            $comments === '' ? null : $comments
        );

        if (!$result['success']) {
            Response::error(implode(', ', $result['errors']), 422);
        }

        Response::success($result);
    }

    public function delete(int $agreementId): void {
        AuthMiddleware::handle();
        PermissionMiddleware::require('DELETE_AGREEMENT');

        $this->agreementService->deleteAgreement($agreementId, (int) $_SESSION['user_id']);
        Response::success(['message' => 'Agreement deleted']);
    }

    public function versions(int $agreementId): void {
        AuthMiddleware::handle();
        PermissionMiddleware::require('VIEW_AGREEMENT');

        if (!$this->agreementService->findByIdForUser(
            $agreementId,
            (int) $_SESSION['user_id']
        )) {
            Response::error('Agreement not found', 404);
        }

        Response::success($this->agreementService->findVersions($agreementId));
    }

    public function version(int $agreementId, int $versionNumber): void {
        AuthMiddleware::handle();
        PermissionMiddleware::require('VIEW_AGREEMENT');

        if (!$this->agreementService->findByIdForUser(
            $agreementId,
            (int) $_SESSION['user_id']
        )) {
            Response::error('Agreement not found', 404);
        }

        $version = $this->agreementService->findVersion($agreementId, $versionNumber);
        if (!$version) {
            Response::error('Agreement version not found', 404);
        }

        Response::success($version);
    }

    public function uploadDocument(int $agreementId): void {
        AuthMiddleware::handle();
        PermissionMiddleware::require('VIEW_AGREEMENT');

        if (!$this->agreementService->findByIdForUser(
            $agreementId,
            (int) $_SESSION['user_id']
        )) {
            Response::error('Agreement not found', 404);
        }

        if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
            Response::error('Choose a document to upload', 422);
        }

        try {
            $result = $this->agreementService->uploadDocument(
                $agreementId,
                $_FILES['file'],
                (string) ($_POST['document_type'] ?? 'OTHER'),
                (int) ($_SESSION['user_id'] ?? 0)
            );
        } catch (InvalidArgumentException $exception) {
            Response::error($exception->getMessage(), 422);
        } catch (DomainException $exception) {
            Response::error($exception->getMessage(), 403);
        } catch (RuntimeException $exception) {
            Response::error($exception->getMessage(), 500);
        }

        Response::success($result);
    }

    public function documents(int $agreementId): void {
        AuthMiddleware::handle();
        PermissionMiddleware::require('VIEW_AGREEMENT');

        if (!$this->agreementService->findByIdForUser(
            $agreementId,
            (int) $_SESSION['user_id']
        )) {
            Response::error('Agreement not found', 404);
        }

        Response::success(
            $this->agreementService->listDocuments(
                $agreementId,
                (int) $_SESSION['user_id']
            )
        );
    }

    public function downloadDocument(int $documentId): void {
        AuthMiddleware::handle();
        PermissionMiddleware::require('VIEW_AGREEMENT');

        $document = $this->agreementService->downloadDocument(
            $documentId,
            (int) $_SESSION['user_id']
        );

        if (!$document) {
            Response::error('Document not found', 404);
        }

        $absolutePath = (string) $document['absolute_path'];
        $fileName = basename(
            str_replace('\\', '/', (string) $document['file_name'])
        );
        $mimeType = (string) (
            $document['mime_type']
            ?? 'application/octet-stream'
        );

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

    public function deleteDocument(int $documentId): void {
        AuthMiddleware::handle();
        PermissionMiddleware::require('VIEW_AGREEMENT');

        try {
            if (!$this->agreementService->deleteDocument(
                $documentId,
                (int) $_SESSION['user_id']
            )) {
                Response::error('Document not found', 404);
            }
        } catch (DomainException $exception) {
            Response::error($exception->getMessage(), 403);
        }

        Response::success(['message' => 'Document deleted']);
    }
}
