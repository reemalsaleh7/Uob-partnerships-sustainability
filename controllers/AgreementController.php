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

        Response::success($this->agreementService->findAll());
    }

    public function show(int $agreementId): void {
        AuthMiddleware::handle();
        PermissionMiddleware::require('VIEW_AGREEMENT');

        $agreement = $this->agreementService->findById($agreementId);
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
        $data = $input;
        $data['updated_by'] = (int) ($_SESSION['user_id'] ?? 0);

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

    public function delete(int $agreementId): void {
        AuthMiddleware::handle();
        PermissionMiddleware::require('DELETE_AGREEMENT');

        $this->agreementService->deleteAgreement($agreementId, (int) $_SESSION['user_id']);
        Response::success(['message' => 'Agreement deleted']);
    }

    public function versions(int $agreementId): void {
        AuthMiddleware::handle();
        PermissionMiddleware::require('VIEW_AGREEMENT');

        Response::success($this->agreementService->findVersions($agreementId));
    }

    public function version(int $agreementId, int $versionNumber): void {
        AuthMiddleware::handle();
        PermissionMiddleware::require('VIEW_AGREEMENT');

        $version = $this->agreementService->findVersion($agreementId, $versionNumber);
        if (!$version) {
            Response::error('Agreement version not found', 404);
        }

        Response::success($version);
    }

    public function uploadDocument(int $agreementId): void {
        AuthMiddleware::handle();
        PermissionMiddleware::require('CREATE_AGREEMENT');

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $result = $this->agreementService->uploadDocument($agreementId, [
            'file_name' => $input['file_name'] ?? null,
            'file_path' => $input['file_path'] ?? null,
            'document_type' => $input['document_type'] ?? 'GENERAL',
            'uploaded_by' => (int) ($_SESSION['user_id'] ?? 0),
        ]);

        Response::success($result);
    }

    public function documents(int $agreementId): void {
        AuthMiddleware::handle();
        PermissionMiddleware::require('VIEW_AGREEMENT');

        Response::success($this->agreementService->listDocuments($agreementId));
    }

    public function deleteDocument(int $documentId): void {
        AuthMiddleware::handle();
        PermissionMiddleware::require('DELETE_AGREEMENT');

        if (!$this->agreementService->deleteDocument($documentId, (int) $_SESSION['user_id'])) {
            Response::error('Document not found', 404);
        }

        Response::success(['message' => 'Document deleted']);
    }
}
