<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/helpers/ApiSession.php';
require_once dirname(__DIR__, 2) . '/helpers/response.php';
require_once dirname(__DIR__, 2) . '/middleware/AuthMiddleware.php';
require_once __DIR__ . '/InitiativeWorkflowRepository.php';
require_once __DIR__ . '/InitiativeRevisionAudienceService.php';
require_once __DIR__ . '/InitiativeAccessPolicy.php';
require_once __DIR__ . '/InitiativeConversionRepository.php';
require_once __DIR__ . '/InitiativeFinalFormRepository.php';

ApiSession::start();
AuthMiddleware::handle();

$repository = new InitiativeWorkflowRepository();
$revisionAudienceService = new InitiativeRevisionAudienceService();
$accessPolicy = new InitiativeAccessPolicy();
$conversionRepository = new InitiativeConversionRepository();
$finalFormRepository = new InitiativeFinalFormRepository();
$uri = '/' . ltrim((string) parse_url(
    $_SERVER['REQUEST_URI'] ?? '/',
    PHP_URL_PATH
), '/');
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$userId = (int) $_SESSION['user_id'];

try {

    // INITIATIVE_REVISION_SELECTED_VIEWERS_V1
    if (
        $method === 'GET'
        && preg_match(
            '#^/initiative-requests/([0-9]+)/revision-note-viewers$#',
            $uri,
            $matches
        )
    ) {
        $requestId = (int) $matches[1];
        $request = $repository->requestDetail($requestId, $userId);
        if ($request === null) {
            Response::error('Initiative request not found.', 404);
        }

        Response::success([
            'items' => $revisionAudienceService->searchViewers(
                $requestId,
                $userId,
                (string) ($_GET['q'] ?? '')
            ),
        ]);
    }

    if (
        $method === 'POST'
        && preg_match(
            '#^/initiative-requests/([0-9]+)/revision-discussions/([0-9]+)/selected-comments$#',
            $uri,
            $matches
        )
    ) {
        $requestId = (int) $matches[1];
        $threadId = (int) $matches[2];
        $request = $repository->requestDetail($requestId, $userId);
        if ($request === null) {
            Response::error('Initiative request not found.', 404);
        }

        $payload = json_decode(
            (string) file_get_contents('php://input'),
            true
        );
        if (!is_array($payload)) {
            Response::error('A valid JSON request body is required.', 422);
        }

        Response::success(
            $revisionAudienceService->addSelectedComment(
                $requestId,
                $threadId,
                $userId,
                $payload
            ),
            201
        );
    }
    if ($method === 'GET' && $uri === '/initiative-access') {
        Response::success([
            'can_create_initiative' =>
                $accessPolicy->canCreate($userId),
        ]);
    }

    if (
        $method === 'GET'
        && $uri === '/initiative-requests/requester-profile'
    ) {
        Response::success(
            $repository->requesterFormProfile($userId)
        );
    }

    if (
        $method === 'GET'
        && $uri === '/initiative-requests/legacy-initiative'
    ) {
        if (!$accessPolicy->canCreate($userId)) {
            Response::error(
                'You are not allowed to register an existing Initiative.',
                403
            );
        }

        $draftId = isset($_GET['draft_id'])
            ? (int) $_GET['draft_id']
            : null;

        Response::success(
            $finalFormRepository->legacyData(
                $draftId && $draftId > 0 ? $draftId : null,
                $userId
            )
        );
    }

    if (
        $method === 'POST'
        && $uri === '/initiative-requests/legacy-initiative/draft'
    ) {
        if (!$accessPolicy->canCreate($userId)) {
            Response::error(
                'You are not allowed to register an existing Initiative.',
                403
            );
        }

        $payload = json_decode(
            (string) file_get_contents('php://input'),
            true
        );
        if (!is_array($payload)) {
            Response::error('A valid JSON request body is required.', 422);
        }

        $draftId = isset($payload['draft_id'])
            ? (int) $payload['draft_id']
            : null;
        $formData = $payload['form_data'] ?? $payload;

        Response::success(
            $finalFormRepository->saveLegacyDraft(
                $draftId && $draftId > 0 ? $draftId : null,
                $userId,
                is_array($formData) ? $formData : []
            )
        );
    }

    if (
        $method === 'POST'
        && $uri === '/initiative-requests/legacy-initiative/finalize'
    ) {
        if (!$accessPolicy->canCreate($userId)) {
            Response::error(
                'You are not allowed to register an existing Initiative.',
                403
            );
        }

        $payload = json_decode(
            (string) file_get_contents('php://input'),
            true
        );
        if (!is_array($payload)) {
            Response::error('A valid JSON request body is required.', 422);
        }

        $draftId = isset($payload['draft_id'])
            ? (int) $payload['draft_id']
            : null;
        $formData = $payload['form_data'] ?? $payload;

        Response::success(
            $finalFormRepository->finalizeLegacy(
                $draftId && $draftId > 0 ? $draftId : null,
                $userId,
                is_array($formData) ? $formData : []
            ),
            201
        );
    }

    if (
        $method === 'POST'
        && preg_match(
            '#^/initiative-requests/legacy-initiative/([0-9]+)/attachments$#',
            $uri,
            $matches
        )
    ) {
        if (!$accessPolicy->canCreate($userId)) {
            Response::error('You are not allowed to upload evidence.', 403);
        }
        if (!isset($_FILES['attachments'])) {
            Response::error('Select at least one attachment.', 422);
        }

        Response::success(
            $finalFormRepository->uploadLegacyAttachments(
                (int) $matches[1],
                $userId,
                $_FILES['attachments']
            ),
            201
        );
    }

    if (
        $method === 'DELETE'
        && preg_match(
            '#^/initiative-requests/legacy-initiative/([0-9]+)/attachments/([0-9]+)$#',
            $uri,
            $matches
        )
    ) {
        $deleted = $finalFormRepository->deleteLegacyAttachment(
            (int) $matches[1],
            (int) $matches[2],
            $userId
        );
        if (!$deleted) {
            Response::error('Attachment not found.', 404);
        }
        Response::success(['attachment_id' => (int) $matches[2]]);
    }

    if (
        $method === 'GET'
        && preg_match(
            '#^/initiative-requests/legacy-initiative/([0-9]+)/attachments/([0-9]+)/download$#',
            $uri,
            $matches
        )
    ) {
        $attachment = $finalFormRepository->legacyAttachmentForDownload(
            (int) $matches[1],
            (int) $matches[2],
            $userId
        );
        if ($attachment === null) {
            Response::error('Attachment not found.', 404);
        }

        $absolutePath = (string) ($attachment['absolute_path'] ?? '');
        if ($absolutePath === '' || !is_file($absolutePath)) {
            Response::error('The attachment file is missing.', 404);
        }

        $downloadName = preg_replace(
            '/[\\r\\n"]+/',
            '_',
            (string) $attachment['original_name']
        );
        header('Content-Type: ' . ($attachment['mime_type'] ?: 'application/octet-stream'));
        header('Content-Length: ' . filesize($absolutePath));
        header('Content-Disposition: attachment; filename="' . $downloadName . '"');
        readfile($absolutePath);
        exit;
    }

    if ($method === 'GET' && $uri === '/initiative-requests') {
        Response::success($repository->visibleRequests($userId));
    }

    if (
        $method === 'GET'
        && $uri === '/initiative-requests/notifications'
    ) {
        $unreadOnly = filter_var(
            $_GET['unread_only'] ?? false,
            FILTER_VALIDATE_BOOLEAN
        );
        $limit = isset($_GET['limit'])
            ? (int) $_GET['limit']
            : 100;

        Response::success(
            $repository->notificationsForUser(
                $userId,
                $unreadOnly,
                $limit
            )
        );
    }

    if (
        $method === 'GET'
        && $uri === '/initiative-requests/notifications/unread-count'
    ) {
        Response::success([
            'unread_count' =>
                $repository->unreadNotificationCount($userId),
        ]);
    }

    if (
        $method === 'POST'
        && $uri === '/initiative-requests/notifications/read-all'
    ) {
        Response::success([
            'updated_count' =>
                $repository->markAllNotificationsRead($userId),
        ]);
    }

    if (
        $method === 'POST'
        && preg_match(
            '#^/initiative-requests/notifications/([0-9]+)/read$#',
            $uri,
            $matches
        )
    ) {
        $updated = $repository->markNotificationRead(
            (int) $matches[1],
            $userId
        );

        if (!$updated) {
            Response::error('Notification not found.', 404);
        }

        Response::success(['notification_id' => (int) $matches[1]]);
    }

    if ($method === 'POST' && $uri === '/initiative-requests') {
        if (!$accessPolicy->canCreate($userId)) {
            Response::error(
                'You are not allowed to create an Initiative request.',
                403
            );
        }
        
        $payload = json_decode((string) file_get_contents('php://input'), true);

        if (!is_array($payload)) {
            Response::error('A valid JSON request body is required.', 422);
        }

        $requestId = $repository->createRequest($userId, $payload);

        Response::success(
            ['request_id' => $requestId],
            201
        );
    }


    if (
        $method === 'PUT'
        && preg_match(
            '#^/initiative-requests/([0-9]+)$#',
            $uri,
            $matches
        )
    ) {
        $payload = json_decode(
            (string) file_get_contents('php://input'),
            true
        );

        if (!is_array($payload)) {
            Response::error(
                'A valid JSON request body is required.',
                422
            );
        }

        $requestId = (int) $matches[1];

        $repository->updateDraft(
            $requestId,
            $userId,
            $payload
        );

        Response::success([
            'request_id' => $requestId,
            'status' => 'DRAFT',
        ]);
    }

    if (
        $method === 'POST'
        && preg_match(
            '#^/initiative-requests/([0-9]+)/attachments$#',
            $uri,
            $matches
        )
    ) {
        if (
            !isset($_FILES['attachments'])
            || !is_array($_FILES['attachments'])
        ) {
            Response::error(
                'Select at least one attachment.',
                422
            );
        }

        Response::success(
            $repository->uploadAttachments(
                (int) $matches[1],
                $userId,
                $_FILES['attachments']
            ),
            201
        );
    }

    if (
        $method === 'DELETE'
        && preg_match(
            '#^/initiative-requests/([0-9]+)/attachments/([0-9]+)$#',
            $uri,
            $matches
        )
    ) {
        $deleted = $repository->deleteAttachment(
            (int) $matches[1],
            (int) $matches[2],
            $userId
        );

        if (!$deleted) {
            Response::error(
                'Attachment not found.',
                404
            );
        }

        Response::success([
            'attachment_id' => (int) $matches[2],
        ]);
    }

    if (
        $method === 'GET'
        && preg_match(
            '#^/initiative-requests/([0-9]+)/attachments/([0-9]+)/download$#',
            $uri,
            $matches
        )
    ) {
        $attachment = $repository->attachmentForDownload(
            (int) $matches[1],
            (int) $matches[2],
            $userId
        );

        if ($attachment === null) {
            Response::error(
                'Attachment not found.',
                404
            );
        }

        $absolutePath = (string) (
            $attachment['absolute_path'] ?? ''
        );

        if (
            $absolutePath === ''
            || !is_file($absolutePath)
        ) {
            Response::error(
                'The attachment file is missing.',
                404
            );
        }

        $downloadName = preg_replace(
            '/[\r\n"]+/',
            '_',
            (string) $attachment['original_name']
        );

        header(
            'Content-Type: '
            . (
                trim(
                    (string) (
                        $attachment['mime_type']
                        ?? ''
                    )
                ) !== ''
                    ? (string) $attachment['mime_type']
                    : 'application/octet-stream'
            )
        );
        header(
            'Content-Length: '
            . (string) filesize($absolutePath)
        );
        header(
            "Content-Disposition: attachment; filename*=UTF-8''"
            . rawurlencode($downloadName)
        );
        header('Cache-Control: private, no-store');

        readfile($absolutePath);
        exit;
    }

    if (
        $method === 'GET'
        && $uri === '/initiative-requests/converted-initiatives'
    ) {
        Response::success(
            $conversionRepository->visibleInitiatives($userId)
        );
    }

    if (
        $method === 'GET'
        && preg_match(
            '#^/initiative-requests/converted-initiatives/([0-9]+)$#',
            $uri,
            $matches
        )
    ) {
        $initiativeId = (int) $matches[1];

        if (
            !$conversionRepository->canViewInitiative(
                $initiativeId,
                $userId
            )
        ) {
            Response::error(
                'You are not allowed to view this Initiative.',
                403
            );
        }

        $initiative =
            $conversionRepository->initiativeDetail(
                $initiativeId
            );

        if ($initiative === null) {
            Response::error('Initiative not found.', 404);
        }

        Response::success($initiative);
    }

    if (
        $method === 'POST'
        && preg_match(
            '#^/initiative-requests/([0-9]+)/conversion/attachments$#',
            $uri,
            $matches
        )
    ) {
        if (
            !isset($_FILES['attachments'])
            || !is_array($_FILES['attachments'])
        ) {
            Response::error(
                'Select at least one evidence file.',
                422
            );
        }

        Response::success(
            $finalFormRepository->uploadAttachments(
                (int) $matches[1],
                $userId,
                $_FILES['attachments']
            ),
            201
        );
    }

    if (
        $method === 'DELETE'
        && preg_match(
            '#^/initiative-requests/([0-9]+)/conversion/attachments/([0-9]+)$#',
            $uri,
            $matches
        )
    ) {
        $deleted = $finalFormRepository->deleteAttachment(
            (int) $matches[1],
            (int) $matches[2],
            $userId
        );

        if (!$deleted) {
            Response::error(
                'Evidence file not found.',
                404
            );
        }

        Response::success([
            'attachment_id' => (int) $matches[2],
        ]);
    }

    if (
        $method === 'GET'
        && preg_match(
            '#^/initiative-requests/([0-9]+)/conversion/attachments/([0-9]+)/download$#',
            $uri,
            $matches
        )
    ) {
        $attachment =
            $finalFormRepository->attachmentForDownload(
                (int) $matches[1],
                (int) $matches[2],
                $userId
            );

        if ($attachment === null) {
            Response::error(
                'Evidence file not found.',
                404
            );
        }

        $absolutePath = (string) (
            $attachment['absolute_path'] ?? ''
        );

        if (
            $absolutePath === ''
            || !is_file($absolutePath)
        ) {
            Response::error(
                'The evidence file is missing.',
                404
            );
        }

        $downloadName = preg_replace(
            '/[\r\n"]+/',
            '_',
            (string) $attachment['original_name']
        );

        header(
            'Content-Type: '
            . (
                trim(
                    (string) (
                        $attachment['mime_type']
                        ?? ''
                    )
                ) !== ''
                    ? (string) $attachment['mime_type']
                    : 'application/octet-stream'
            )
        );
        header(
            'Content-Length: '
            . (string) filesize($absolutePath)
        );
        header(
            "Content-Disposition: attachment; filename*=UTF-8''"
            . rawurlencode($downloadName)
        );
        header('Cache-Control: private, no-store');

        readfile($absolutePath);
        exit;
    }

    if (
        $method === 'GET'
        && preg_match(
            '#^/initiative-requests/([0-9]+)/conversion$#',
            $uri,
            $matches
        )
    ) {
        $requestId = (int) $matches[1];

        if (!$finalFormRepository->canConvert($requestId, $userId)) {
            Response::error(
                'You are not allowed to prepare this approved Initiative.',
                403
            );
        }

        Response::success(
            $finalFormRepository->conversionData(
                $requestId,
                $userId
            )
        );
    }

    if (
        $method === 'POST'
        && preg_match(
            '#^/initiative-requests/([0-9]+)/conversion/draft$#',
            $uri,
            $matches
        )
    ) {
        $payload = json_decode(
            (string) file_get_contents('php://input'),
            true
        );

        if (!is_array($payload)) {
            Response::error(
                'A valid JSON request body is required.',
                422
            );
        }

        Response::success(
            $finalFormRepository->saveDraft(
                (int) $matches[1],
                $userId,
                $payload
            )
        );
    }

    if (
        $method === 'POST'
        && preg_match(
            '#^/initiative-requests/([0-9]+)/conversion/finalize$#',
            $uri,
            $matches
        )
    ) {
        $payload = json_decode(
            (string) file_get_contents('php://input'),
            true
        );

        if (!is_array($payload)) {
            Response::error(
                'A valid JSON request body is required.',
                422
            );
        }

        Response::success(
            $finalFormRepository->finalize(
                (int) $matches[1],
                $userId,
                $payload
            ),
            201
        );
    }

    if (
        $method === 'POST'
        && preg_match(
            '#^/initiative-requests/([0-9]+)/revision-discussions/([0-9]+)/comments$#',
            $uri,
            $matches
        )
    ) {
        $payload = json_decode(
            (string) file_get_contents('php://input'),
            true
        );

        if (!is_array($payload)) {
            Response::error(
                'A valid JSON request body is required.',
                422
            );
        }

        $comment = $repository->addRevisionComment(
            (int) $matches[1],
            (int) $matches[2],
            $userId,
            (string) ($payload['comment_text'] ?? ''),
            (string) ($payload['visibility'] ?? 'PUBLIC')
        );

        Response::success($comment, 201);
    }

    if (
        $method === 'POST'
        && preg_match(
            '#^/initiative-requests/([0-9]+)/admin-skip$#',
            $uri,
            $matches
        )
    ) {
        if (!$repository->canAdministerInitiatives($userId)) {
            Response::error(
                'Only a System Administrator can skip an Initiative approval stage.',
                403
            );
        }

        $payload = json_decode(
            (string) file_get_contents('php://input'),
            true
        );

        if (!is_array($payload)) {
            Response::error(
                'A valid JSON request body is required.',
                422
            );
        }

        $result = $repository->adminSkipStage(
            (int) $matches[1],
            $userId,
            (string) ($payload['reason'] ?? '')
        );

        Response::success($result);
    }

    if (
        $method === 'POST'
        && preg_match(
            '#^/initiative-requests/([0-9]+)/decision$#',
            $uri,
            $matches
        )
    ) {
        $payload = json_decode(
            (string) file_get_contents('php://input'),
            true
        );

        if (!is_array($payload)) {
            Response::error(
                'A valid JSON request body is required.',
                422
            );
        }

        $result = $repository->decideRequest(
            (int) $matches[1],
            $userId,
            (string) ($payload['action'] ?? ''),
            isset($payload['comment'])
                ? (string) $payload['comment']
                : null
        );

        Response::success($result);
    }

    if (
        $method === 'POST'
        && preg_match(
            '#^/initiative-requests/([0-9]+)/submit$#',
            $uri,
            $matches
        )
    ) {
        $requestId = (int) $matches[1];

        $repository->submitDraft(
            $requestId,
            $userId
        );

        Response::success([
            'request_id' => $requestId,
            'status' => 'UNDER_REVIEW',
        ]);
    }

    if (
        $method === 'GET'
        && preg_match('#^/initiative-requests/([0-9]+)$#', $uri, $matches)
    ) {
        $request = $repository->requestDetail(
            (int) $matches[1],
            $userId
        );

        if ($request === null) {
            Response::error('Initiative request not found.', 404);
        }

        $request = $revisionAudienceService->filterRequestDetail(
            $request,
            $userId
        );

        Response::success($request);
    }

    if (
        $method === 'GET'
        && $uri === '/initiative-eligible-collaborators'
    ) {
        Response::success(
            $repository->eligibleCollaborators($userId)
        );
    }

    Response::error('Route not found', 404);
} catch (InvalidArgumentException | DomainException $exception) {
    Response::error($exception->getMessage(), 422);
} catch (PDOException $exception) {
    error_log('[Initiative workflow database] ' . $exception->getMessage());

    if ($exception->getCode() === '42P01') {
        Response::error(
            'The Initiative workflow database migration has not been installed.',
            503
        );
    }

    Response::error('The Initiative request could not be processed.', 500);
} catch (Throwable $exception) {
    error_log('[Initiative workflow] ' . $exception->getMessage());

    Response::error(
        'The Initiative request could not be processed.',
        500
    );
}
