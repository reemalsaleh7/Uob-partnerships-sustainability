<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/database.php';

// INITIATIVE_REVISION_AUDIENCE_RUNTIME_FIX_V2
final class InitiativeRevisionAudienceService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    /**
     * Return active users related to this request. The client can search by
     * name, email, or University ID, but the backend remains authoritative.
     */
    public function searchViewers(
        int $requestId,
        int $actorUserId,
        string $query
    ): array {
        $this->assertRequestVisible($requestId, $actorUserId);

        $query = mb_substr(trim($query), 0, 100);
        if (mb_strlen($query) < 2) {
            return [];
        }

        $statement = $this->db->prepare(
            "WITH eligible AS (
                SELECT request.requester_id AS user_id,
                       'REQUESTER'::text AS relation_label
                FROM initiative_requests request
                WHERE request.request_id = :request_id_1

                UNION ALL

                SELECT member.user_id,
                       COALESCE(member.member_role, 'MEMBER')::text
                FROM initiative_request_members member
                WHERE member.request_id = :request_id_2

                UNION ALL

                SELECT stage.assigned_user_id,
                       'APPROVER'::text
                FROM initiative_request_stages stage
                WHERE stage.request_id = :request_id_3
                  AND stage.assigned_user_id IS NOT NULL

                UNION ALL

                SELECT stage.acted_by_user_id,
                       'REVIEWER'::text
                FROM initiative_request_stages stage
                WHERE stage.request_id = :request_id_4
                  AND stage.acted_by_user_id IS NOT NULL

                UNION ALL

                SELECT stage.acted_on_behalf_of_user_id,
                       'PRINCIPAL'::text
                FROM initiative_request_stages stage
                WHERE stage.request_id = :request_id_5
                  AND stage.acted_on_behalf_of_user_id IS NOT NULL

                UNION ALL

                SELECT request.current_assignee_id,
                       'CURRENT_APPROVER'::text
                FROM initiative_requests request
                WHERE request.request_id = :request_id_6
                  AND request.current_assignee_id IS NOT NULL
            )
            SELECT
                user_account.user_id,
                TRIM(CONCAT(
                    user_account.first_name,
                    ' ',
                    user_account.last_name
                )) AS full_name,
                user_account.email,
                user_account.university_id,
                STRING_AGG(
                    DISTINCT eligible.relation_label,
                    ', '
                    ORDER BY eligible.relation_label
                ) AS relation_label
            FROM eligible
            JOIN users user_account
              ON user_account.user_id = eligible.user_id
            WHERE user_account.is_active = TRUE
              AND (
                    LOWER(TRIM(CONCAT(
                        user_account.first_name,
                        ' ',
                        user_account.last_name
                    ))) LIKE :search
                    OR LOWER(COALESCE(user_account.email, '')) LIKE :search
                    OR LOWER(COALESCE(user_account.university_id, '')) LIKE :search
              )
            GROUP BY
                user_account.user_id,
                user_account.first_name,
                user_account.last_name,
                user_account.email,
                user_account.university_id
            ORDER BY full_name, user_account.email
            LIMIT 20"
        );

        $parameters = ['search' => '%' . mb_strtolower($query) . '%'];
        for ($index = 1; $index <= 6; $index++) {
            $parameters['request_id_' . $index] = $requestId;
        }
        $statement->execute($parameters);

        return array_map(
            static function (array $row): array {
                return [
                    'user_id' => (int) $row['user_id'],
                    'full_name' => (string) $row['full_name'],
                    'email' => $row['email'] ?? null,
                    'university_id' => $row['university_id'] ?? null,
                    'relation_label' => $row['relation_label'] ?? null,
                ];
            },
            $statement->fetchAll()
        );
    }

    /**
     * Create a private note visible only to the chosen users, its author, and
     * System Administrators for audit access.
     */
    public function addSelectedComment(
        int $requestId,
        int $threadId,
        int $actorUserId,
        array $payload
    ): array {
        $commentText = trim((string) ($payload['comment_text']
            ?? $payload['comment']
            ?? $payload['note']
            ?? ''));

        if ($commentText === '') {
            throw new InvalidArgumentException('The note text is required.');
        }

        $viewerIds = $payload['viewer_user_ids']
            ?? $payload['selected_viewer_ids']
            ?? [];

        if (!is_array($viewerIds)) {
            throw new InvalidArgumentException(
                'Selected viewers must be an array of user IDs.'
            );
        }

        $viewerIds = array_values(array_unique(array_filter(
            array_map('intval', $viewerIds),
            static fn (int $value): bool => $value > 0
        )));

        if ($viewerIds === []) {
            throw new InvalidArgumentException(
                'Choose at least one person who can view this private note.'
            );
        }

        $this->db->beginTransaction();

        try {
            $thread = $this->lockThread($requestId, $threadId);
            $this->assertRequestVisible($requestId, $actorUserId);
            $this->assertThreadParticipantOrAdministrator(
                $threadId,
                $actorUserId
            );

            $eligibleIds = $this->eligibleViewerIds($requestId);
            $allowedLookup = array_fill_keys($eligibleIds, true);

            foreach ($viewerIds as $viewerId) {
                if (!isset($allowedLookup[$viewerId])) {
                    throw new InvalidArgumentException(
                        'One or more selected people are not related to this request.'
                    );
                }
            }

            // The author must always retain access to their own note.
            $viewerIds[] = $actorUserId;
            $viewerIds = array_values(array_unique($viewerIds));

            $insert = $this->db->prepare(
                "INSERT INTO initiative_revision_comments (
                    revision_thread_id,
                    author_user_id,
                    visibility,
                    audience_mode,
                    comment_text
                 ) VALUES (
                    :revision_thread_id,
                    :author_user_id,
                    'PRIVATE',
                    'SELECTED_USERS',
                    :comment_text
                 )
                 RETURNING revision_comment_id, created_at"
            );
            $insert->execute([
                'revision_thread_id' => $threadId,
                'author_user_id' => $actorUserId,
                'comment_text' => $commentText,
            ]);
            $comment = $insert->fetch();
            $commentId = (int) $comment['revision_comment_id'];

            $viewerInsert = $this->db->prepare(
                "INSERT INTO initiative_revision_comment_viewers (
                    revision_comment_id,
                    viewer_user_id,
                    added_by_user_id
                 ) VALUES (
                    :revision_comment_id,
                    :viewer_user_id,
                    :added_by_user_id
                 )
                 ON CONFLICT (revision_comment_id, viewer_user_id)
                 DO NOTHING"
            );

            foreach ($viewerIds as $viewerId) {
                $viewerInsert->execute([
                    'revision_comment_id' => $commentId,
                    'viewer_user_id' => $viewerId,
                    'added_by_user_id' => $actorUserId,
                ]);
            }

            $this->touchThread($threadId);
            $this->recordEvent(
                $requestId,
                $actorUserId,
                $commentId,
                $threadId,
                $viewerIds
            );
            $this->notifySelectedViewers(
                $thread,
                $commentId,
                $actorUserId,
                $viewerIds
            );

            $this->db->commit();

            return $this->commentPayload($commentId, $actorUserId, true);
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    /**
     * Ensure selected-user notes are returned only to their audience. The
     * method also merges selected notes that an older repository query may
     * have omitted under its legacy PRIVATE rule.
     */
    public function filterRequestDetail(array $detail, int $userId): array
    {
        if (!$this->selectedAudienceSchemaInstalled()) {
            return $detail;
        }

        $isAdministrator = $this->isSystemAdministrator($userId);
        $detail = $this->augmentSelectedComments(
            $detail,
            $userId,
            $isAdministrator
        );

        $filtered = $this->filterValue(
            $detail,
            $userId,
            $isAdministrator
        );

        return is_array($filtered) ? $filtered : $detail;
    }

    private function augmentSelectedComments(
        array $node,
        int $userId,
        bool $isAdministrator
    ): array {
        $isThreadNode = isset($node['revision_thread_id'])
            && !isset($node['revision_comment_id'])
            && !isset($node['comment_id']);

        if ($isThreadNode) {
            $threadId = (int) $node['revision_thread_id'];
            if ($threadId > 0) {
                $selected = $this->selectedCommentsForThread(
                    $threadId,
                    $userId,
                    $isAdministrator
                );

                if ($selected !== []) {
                    $key = $this->commentListKey($node);
                    $existing = is_array($node[$key] ?? null)
                        ? $node[$key]
                        : [];
                    $known = [];
                    foreach ($existing as $comment) {
                        if (is_array($comment)) {
                            $id = (int) ($comment['revision_comment_id']
                                ?? $comment['comment_id']
                                ?? 0);
                            if ($id > 0) {
                                $known[$id] = true;
                            }
                        }
                    }
                    foreach ($selected as $comment) {
                        $id = (int) $comment['revision_comment_id'];
                        if (!isset($known[$id])) {
                            $existing[] = $comment;
                        }
                    }
                    usort(
                        $existing,
                        static fn (array $left, array $right): int =>
                            strcmp(
                                (string) ($left['created_at'] ?? ''),
                                (string) ($right['created_at'] ?? '')
                            )
                    );
                    $node[$key] = $existing;
                }
            }
        }

        foreach ($node as $key => $value) {
            if (is_array($value)) {
                $node[$key] = $this->augmentSelectedComments(
                    $value,
                    $userId,
                    $isAdministrator
                );
            }
        }

        return $node;
    }

    private function filterValue(
        mixed $value,
        int $userId,
        bool $isAdministrator
    ): mixed {
        if (!is_array($value)) {
            return $value;
        }

        $commentId = (int) ($value['revision_comment_id']
            ?? $value['comment_id']
            ?? 0);

        if ($commentId > 0) {
            $audience = $this->audienceForComment($commentId);
            if (($audience['audience_mode'] ?? '') === 'SELECTED_USERS') {
                $authorId = (int) ($audience['author_user_id'] ?? 0);
                $viewerIds = array_map(
                    static fn (array $viewer): int =>
                        (int) $viewer['user_id'],
                    $audience['viewers'] ?? []
                );
                $canView = $isAdministrator
                    || $authorId === $userId
                    || in_array($userId, $viewerIds, true);

                if (!$canView) {
                    return null;
                }

                $value['audience_mode'] = 'SELECTED_USERS';
                $value['visibility'] = 'PRIVATE';
                $value['is_private'] = true;
                $value['privacy_label'] = 'Private note';
                $value['viewer_count'] = count($audience['viewers'] ?? []);
                $value['viewers'] = $audience['viewers'] ?? [];
            }
        }

        $isList = array_is_list($value);
        $result = [];
        foreach ($value as $key => $child) {
            $filteredChild = $this->filterValue(
                $child,
                $userId,
                $isAdministrator
            );
            if ($isList && $filteredChild === null) {
                continue;
            }
            $result[$key] = $filteredChild;
        }

        return $isList ? array_values($result) : $result;
    }

    private function selectedCommentsForThread(
        int $threadId,
        int $userId,
        bool $isAdministrator
    ): array {
        $statement = $this->db->prepare(
            "SELECT
                comment.revision_comment_id,
                comment.revision_thread_id,
                comment.author_user_id,
                comment.visibility,
                comment.audience_mode,
                comment.comment_text,
                comment.created_at,
                TRIM(CONCAT(author.first_name, ' ', author.last_name))
                    AS author_name,
                author.email AS author_email
             FROM initiative_revision_comments comment
             JOIN users author
               ON author.user_id = comment.author_user_id
             WHERE comment.revision_thread_id = :revision_thread_id
               AND comment.audience_mode = 'SELECTED_USERS'
               AND (
                    CAST(:is_administrator AS INTEGER) = 1
                    OR comment.author_user_id = :author_user_id
                    OR EXISTS (
                        SELECT 1
                        FROM initiative_revision_comment_viewers viewer
                        WHERE viewer.revision_comment_id =
                              comment.revision_comment_id
                          AND viewer.viewer_user_id = :viewer_user_id
                    )
               )
             ORDER BY comment.created_at, comment.revision_comment_id"
        );
        $statement->execute([
            'revision_thread_id' => $threadId,
            'is_administrator' => $isAdministrator ? 1 : 0,
            'author_user_id' => $userId,
            'viewer_user_id' => $userId,
        ]);

        $rows = [];
        foreach ($statement->fetchAll() as $row) {
            $id = (int) $row['revision_comment_id'];
            $payload = $row;
            $audience = $this->audienceForComment($id);
            $payload['revision_comment_id'] = $id;
            $payload['revision_thread_id'] = (int) $row['revision_thread_id'];
            $payload['author_user_id'] = (int) $row['author_user_id'];
            $payload['is_private'] = true;
            $payload['privacy_label'] = 'Private note';
            $payload['viewer_count'] = count($audience['viewers'] ?? []);
            $payload['viewers'] = $audience['viewers'] ?? [];
            $rows[] = $payload;
        }

        return $rows;
    }

    private function commentListKey(array $thread): string
    {
        foreach (['comments', 'revision_comments', 'notes'] as $key) {
            if (array_key_exists($key, $thread)) {
                return $key;
            }
        }
        return 'comments';
    }

    private function commentPayload(
        int $commentId,
        int $viewerUserId,
        bool $includeViewers
    ): array {
        $statement = $this->db->prepare(
            "SELECT
                comment.revision_comment_id,
                comment.revision_thread_id,
                comment.author_user_id,
                comment.visibility,
                comment.audience_mode,
                comment.comment_text,
                comment.created_at,
                TRIM(CONCAT(author.first_name, ' ', author.last_name))
                    AS author_name,
                author.email AS author_email
             FROM initiative_revision_comments comment
             JOIN users author
               ON author.user_id = comment.author_user_id
             WHERE comment.revision_comment_id = :revision_comment_id"
        );
        $statement->execute(['revision_comment_id' => $commentId]);
        $row = $statement->fetch();
        if (!$row) {
            throw new RuntimeException('The private note could not be loaded.');
        }

        $audience = $this->audienceForComment($commentId);
        return [
            'revision_comment_id' => (int) $row['revision_comment_id'],
            'revision_thread_id' => (int) $row['revision_thread_id'],
            'author_user_id' => (int) $row['author_user_id'],
            'author_name' => (string) $row['author_name'],
            'author_email' => $row['author_email'] ?? null,
            'visibility' => 'PRIVATE',
            'audience_mode' => 'SELECTED_USERS',
            'comment_text' => (string) $row['comment_text'],
            'created_at' => (string) $row['created_at'],
            'is_private' => true,
            'privacy_label' => 'Private note',
            'viewer_count' => count($audience['viewers'] ?? []),
            'viewers' => $includeViewers
                ? ($audience['viewers'] ?? [])
                : [],
        ];
    }

    private function audienceForComment(int $commentId): array
    {
        $statement = $this->db->prepare(
            "SELECT audience_mode, author_user_id
             FROM initiative_revision_comments
             WHERE revision_comment_id = :revision_comment_id"
        );
        $statement->execute(['revision_comment_id' => $commentId]);
        $comment = $statement->fetch() ?: [];

        $viewerStatement = $this->db->prepare(
            "SELECT
                user_account.user_id,
                TRIM(CONCAT(
                    user_account.first_name,
                    ' ',
                    user_account.last_name
                )) AS full_name,
                user_account.email,
                user_account.university_id
             FROM initiative_revision_comment_viewers viewer
             JOIN users user_account
               ON user_account.user_id = viewer.viewer_user_id
             WHERE viewer.revision_comment_id = :revision_comment_id
             ORDER BY full_name, user_account.email"
        );
        $viewerStatement->execute(['revision_comment_id' => $commentId]);

        $viewers = array_map(
            static fn (array $row): array => [
                'user_id' => (int) $row['user_id'],
                'full_name' => (string) $row['full_name'],
                'email' => $row['email'] ?? null,
                'university_id' => $row['university_id'] ?? null,
            ],
            $viewerStatement->fetchAll()
        );

        return [
            'audience_mode' => $comment['audience_mode'] ?? null,
            'author_user_id' => isset($comment['author_user_id'])
                ? (int) $comment['author_user_id']
                : null,
            'viewers' => $viewers,
        ];
    }

    private function eligibleViewerIds(int $requestId): array
    {
        $statement = $this->db->prepare(
            "SELECT DISTINCT user_id
             FROM (
                SELECT request.requester_id AS user_id
                FROM initiative_requests request
                WHERE request.request_id = :request_id_1

                UNION ALL
                SELECT member.user_id
                FROM initiative_request_members member
                WHERE member.request_id = :request_id_2

                UNION ALL
                SELECT stage.assigned_user_id
                FROM initiative_request_stages stage
                WHERE stage.request_id = :request_id_3

                UNION ALL
                SELECT stage.acted_by_user_id
                FROM initiative_request_stages stage
                WHERE stage.request_id = :request_id_4

                UNION ALL
                SELECT stage.acted_on_behalf_of_user_id
                FROM initiative_request_stages stage
                WHERE stage.request_id = :request_id_5

                UNION ALL
                SELECT request.current_assignee_id
                FROM initiative_requests request
                WHERE request.request_id = :request_id_6
             ) people
             WHERE user_id IS NOT NULL"
        );
        $parameters = [];
        for ($index = 1; $index <= 6; $index++) {
            $parameters['request_id_' . $index] = $requestId;
        }
        $statement->execute($parameters);
        return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    private function lockThread(int $requestId, int $threadId): array
    {
        $statement = $this->db->prepare(
            "SELECT
                thread.*,
                request.request_code,
                request.title,
                request.requester_id
             FROM initiative_revision_threads thread
             JOIN initiative_requests request
               ON request.request_id = thread.request_id
             WHERE thread.revision_thread_id = :revision_thread_id
               AND thread.request_id = :request_id
             FOR UPDATE"
        );
        $statement->execute([
            'revision_thread_id' => $threadId,
            'request_id' => $requestId,
        ]);
        $thread = $statement->fetch();
        if (!$thread) {
            throw new InvalidArgumentException(
                'The revision discussion was not found for this request.'
            );
        }
        return $thread;
    }

    private function assertThreadParticipantOrAdministrator(
        int $threadId,
        int $actorUserId
    ): void {
        if ($this->isSystemAdministrator($actorUserId)) {
            return;
        }

        $statement = $this->db->prepare(
            "SELECT EXISTS (
                SELECT 1
                FROM initiative_revision_threads thread
                JOIN initiative_requests request
                  ON request.request_id = thread.request_id
                WHERE thread.revision_thread_id = :revision_thread_id
                  AND (
                        request.requester_id = :requester_user_id
                        OR request.current_assignee_id = :assignee_user_id
                        OR EXISTS (
                            SELECT 1
                            FROM initiative_request_members member
                            WHERE member.request_id = request.request_id
                              AND member.user_id = :member_user_id
                        )
                        OR EXISTS (
                            SELECT 1
                            FROM initiative_request_stages stage
                            WHERE stage.request_id = request.request_id
                              AND (
                                    stage.assigned_user_id = :stage_user_id_1
                                    OR stage.acted_by_user_id = :stage_user_id_2
                                    OR stage.acted_on_behalf_of_user_id =
                                       :stage_user_id_3
                              )
                        )
                  )
            )"
        );
        $statement->execute([
            'revision_thread_id' => $threadId,
            'requester_user_id' => $actorUserId,
            'assignee_user_id' => $actorUserId,
            'member_user_id' => $actorUserId,
            'stage_user_id_1' => $actorUserId,
            'stage_user_id_2' => $actorUserId,
            'stage_user_id_3' => $actorUserId,
        ]);

        if (!filter_var($statement->fetchColumn(), FILTER_VALIDATE_BOOLEAN)) {
            throw new DomainException(
                'You are not allowed to add a note to this discussion.'
            );
        }
    }

    private function assertRequestVisible(int $requestId, int $userId): void
    {
        if ($this->isSystemAdministrator($userId)) {
            return;
        }

        $statement = $this->db->prepare(
            "SELECT EXISTS (
                SELECT 1
                FROM initiative_requests request
                WHERE request.request_id = :request_id
                  AND (
                        request.requester_id = :requester_user_id
                        OR request.current_assignee_id = :assignee_user_id
                        OR EXISTS (
                            SELECT 1
                            FROM initiative_request_members member
                            WHERE member.request_id = request.request_id
                              AND member.user_id = :member_user_id
                        )
                        OR EXISTS (
                            SELECT 1
                            FROM initiative_request_stages stage
                            WHERE stage.request_id = request.request_id
                              AND (
                                    stage.assigned_user_id = :stage_user_id_1
                                    OR stage.acted_by_user_id = :stage_user_id_2
                                    OR stage.acted_on_behalf_of_user_id =
                                       :stage_user_id_3
                              )
                        )
                  )
            )"
        );
        $statement->execute([
            'request_id' => $requestId,
            'requester_user_id' => $userId,
            'assignee_user_id' => $userId,
            'member_user_id' => $userId,
            'stage_user_id_1' => $userId,
            'stage_user_id_2' => $userId,
            'stage_user_id_3' => $userId,
        ]);

        if (!filter_var($statement->fetchColumn(), FILTER_VALIDATE_BOOLEAN)) {
            throw new DomainException('Initiative request not found.');
        }
    }

    private function isSystemAdministrator(int $userId): bool
    {
        $statement = $this->db->prepare(
            "SELECT EXISTS (
                SELECT 1
                FROM user_roles user_role
                JOIN roles role
                  ON role.role_id = user_role.role_id
                WHERE user_role.user_id = :user_id
                  AND role.role_name = 'System Administrator'
            )"
        );
        $statement->execute(['user_id' => $userId]);
        return filter_var(
            $statement->fetchColumn(),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    private function touchThread(int $threadId): void
    {
        try {
            $statement = $this->db->prepare(
                "UPDATE initiative_revision_threads
                 SET last_comment_at = CURRENT_TIMESTAMP,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE revision_thread_id = :revision_thread_id"
            );
            $statement->execute(['revision_thread_id' => $threadId]);
        } catch (PDOException $exception) {
            if ($exception->getCode() !== '42703') {
                throw $exception;
            }
            $statement = $this->db->prepare(
                "UPDATE initiative_revision_threads
                 SET updated_at = CURRENT_TIMESTAMP
                 WHERE revision_thread_id = :revision_thread_id"
            );
            $statement->execute(['revision_thread_id' => $threadId]);
        }
    }

    private function recordEvent(
        int $requestId,
        int $actorUserId,
        int $commentId,
        int $threadId,
        array $viewerIds
    ): void {
        $statement = $this->db->prepare(
            "INSERT INTO initiative_request_events (
                request_id,
                actor_user_id,
                event_type,
                event_note,
                event_data
             ) VALUES (
                :request_id,
                :actor_user_id,
                'REVISION_PRIVATE_NOTE_ADDED',
                'A private revision note was added for selected viewers.',
                CAST(:event_data AS jsonb)
             )"
        );
        $statement->execute([
            'request_id' => $requestId,
            'actor_user_id' => $actorUserId,
            'event_data' => json_encode([
                'revision_comment_id' => $commentId,
                'revision_thread_id' => $threadId,
                'viewer_user_ids' => array_values($viewerIds),
            ], JSON_THROW_ON_ERROR),
        ]);
    }

    private function notifySelectedViewers(
        array $thread,
        int $commentId,
        int $actorUserId,
        array $viewerIds
    ): void {
        $insert = $this->db->prepare(
            "INSERT INTO initiative_notifications (
                request_id,
                recipient_user_id,
                notification_type,
                title,
                message,
                payload
             ) VALUES (
                :request_id,
                :recipient_user_id,
                'REVISION_PRIVATE_NOTE',
                'Private revision note',
                :message,
                CAST(:payload AS jsonb)
             )
             ON CONFLICT DO NOTHING"
        );

        foreach ($viewerIds as $viewerId) {
            if ($viewerId === $actorUserId) {
                continue;
            }
            $insert->execute([
                'request_id' => (int) $thread['request_id'],
                'recipient_user_id' => $viewerId,
                'message' => sprintf(
                    'A private note was added to %s.',
                    (string) ($thread['request_code'] ?? 'an Initiative request')
                ),
                'payload' => json_encode([
                    'revision_comment_id' => $commentId,
                    'revision_thread_id' =>
                        (int) $thread['revision_thread_id'],
                ], JSON_THROW_ON_ERROR),
            ]);
        }
    }

    private function selectedAudienceSchemaInstalled(): bool
    {
        $statement = $this->db->query(
            "SELECT EXISTS (
                SELECT 1
                FROM information_schema.columns
                WHERE table_schema = current_schema()
                  AND table_name = 'initiative_revision_comments'
                  AND column_name = 'audience_mode'
            ) AND EXISTS (
                SELECT 1
                FROM information_schema.tables
                WHERE table_schema = current_schema()
                  AND table_name =
                      'initiative_revision_comment_viewers'
            )"
        );
        return filter_var(
            $statement->fetchColumn(),
            FILTER_VALIDATE_BOOLEAN
        );
    }
}
