<?php

// services/NotificationService.php

declare(strict_types=1);

require_once __DIR__ . '/EmailService.php';

class NotificationService
{
    private $db;
    private string $lang;

    public function __construct($dbConnection = null)
    {
        $this->db = $dbConnection;

        $this->lang = $_SESSION['lang'] ?? 'ar';

        if (!$this->db) {
            $this->db = $this->getDbConnection();
        }
    }

    /**
     * Get database connection.
     */
    public function getDbConnection()
    {
        try {
            $host = 'localhost';
            $port = '5432';
            $dbname = 'UOB_Partnership_and_Initiative';
            $user = 'postgres';
            $password = 'fatema_fruit_20&04';

            $pdo = new PDO(
                "pgsql:host=$host;port=$port;dbname=$dbname",
                $user,
                $password
            );

            $pdo->setAttribute(
                PDO::ATTR_ERRMODE,
                PDO::ERRMODE_EXCEPTION
            );

            $pdo->setAttribute(
                PDO::ATTR_DEFAULT_FETCH_MODE,
                PDO::FETCH_ASSOC
            );

            return $pdo;

        } catch (PDOException $e) {

            error_log(
                "NotificationService database connection failed: "
                . $e->getMessage()
            );

            return null;
        }
    }

    /**
     * Get user ID by email.
     */
    public function getUserIdByEmail(string $email): ?int
    {
        if (!$this->db) {
            return null;
        }

        try {

            $sql = "
                SELECT user_id
                FROM users
                WHERE email = :email
                LIMIT 1
            ";

            $stmt = $this->db->prepare($sql);

            $stmt->execute([
                ':email' => $email
            ]);

            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result
                ? (int) $result['user_id']
                : null;

        } catch (PDOException $e) {

            error_log(
                "Error getting user ID: "
                . $e->getMessage()
            );

            return null;
        }
    }

    /**
     * Get all notifications for a specific user.
     *
     * When $includeRead is true:
     *      Returns all active notifications.
     *
     * When $includeRead is false:
     *      Returns only unread active notifications.
     */
    public function getUserNotifications(
        int $userId,
        bool $includeRead = true,
        int $limit = 100
    ): array {

        if (!$this->db || $userId <= 0) {
            return [];
        }

        try {

            // Keep limit within a safe range.
            $limit = max(1, min($limit, 500));

            $sql = "
                SELECT
                    notification_id,
                    user_id,
                    title_ar,
                    title_en,
                    message_ar,
                    message_en,
                    entity_type,
                    entity_id,
                    entity_code,
                    priority,
                    reminder_count,
                    reminder_sent,
                    action_required,
                    action_url,
                    is_read,
                    is_archived,
                    is_deleted,
                    created_at
                FROM notifications
                WHERE user_id = :user_id
                  AND is_archived = FALSE
                  AND is_deleted = FALSE
            ";

            // If includeRead is false,
            // show unread notifications only.
            if (!$includeRead) {

                $sql .= "
                    AND is_read = FALSE
                ";
            }

            $sql .= "
                ORDER BY created_at DESC
                LIMIT :limit
            ";

            $stmt = $this->db->prepare($sql);

            $stmt->bindValue(
                ':user_id',
                $userId,
                PDO::PARAM_INT
            );

            $stmt->bindValue(
                ':limit',
                $limit,
                PDO::PARAM_INT
            );

            $stmt->execute();

            $notifications = $stmt->fetchAll(
                PDO::FETCH_ASSOC
            );

            return $notifications ?: [];

        } catch (PDOException $e) {

            error_log(
                "Error getting user notifications: "
                . $e->getMessage()
            );

            return [];
        }
    }

    /**
     * Get unread notification count for a user.
     */
    public function getUnreadCount(int $userId): int
    {
        if (!$this->db || $userId <= 0) {
            return 0;
        }

        try {

            $sql = "
                SELECT COUNT(*)
                FROM notifications
                WHERE user_id = :user_id
                  AND is_read = FALSE
                  AND is_archived = FALSE
                  AND is_deleted = FALSE
            ";

            $stmt = $this->db->prepare($sql);

            $stmt->execute([
                ':user_id' => $userId
            ]);

            return (int) $stmt->fetchColumn();

        } catch (PDOException $e) {

            error_log(
                "Error getting unread notification count: "
                . $e->getMessage()
            );

            return 0;
        }
    }

    /**
     * Mark one notification as read.
     *
     * The notification must belong to the specified user.
     */
    public function markAsRead(
        int $notificationId,
        int $userId
    ): bool {

        if (
            !$this->db ||
            $notificationId <= 0 ||
            $userId <= 0
        ) {
            return false;
        }

        try {

            $sql = "
                UPDATE notifications
                SET is_read = TRUE
                WHERE notification_id = :notification_id
                  AND user_id = :user_id
            ";

            $stmt = $this->db->prepare($sql);

            $stmt->execute([
                ':notification_id' => $notificationId,
                ':user_id' => $userId
            ]);

            return $stmt->rowCount() > 0;

        } catch (PDOException $e) {

            error_log(
                "Error marking notification as read: "
                . $e->getMessage()
            );

            return false;
        }
    }

    /**
     * Mark all active notifications as read
     * for the specified user.
     */
    public function markAllAsRead(int $userId): bool
    {
        if (!$this->db || $userId <= 0) {
            return false;
        }

        try {

            $sql = "
                UPDATE notifications
                SET is_read = TRUE
                WHERE user_id = :user_id
                  AND is_read = FALSE
                  AND is_archived = FALSE
                  AND is_deleted = FALSE
            ";

            $stmt = $this->db->prepare($sql);

            $stmt->execute([
                ':user_id' => $userId
            ]);

            return true;

        } catch (PDOException $e) {

            error_log(
                "Error marking all notifications as read: "
                . $e->getMessage()
            );

            return false;
        }
    }

    /**
     * Build HTML email content.
     */
    private function buildEmailContent(
        $title,
        $message,
        $actionUrl = null,
        $lang = 'ar'
    ): string {

        $isArabic = ($lang === 'ar');

        $appName = $isArabic
            ? 'الشراكات والأثر المستدام – جامعة البحرين'
            : 'UOB Partnerships & Sustainable Impact';

        $buttonText = $isArabic
            ? 'عرض التفاصيل'
            : 'View Details';

        $footerText = $isArabic
            ? '© جامعة البحرين - جميع الحقوق محفوظة'
            : '© University of Bahrain - All rights reserved';

        $direction = $isArabic
            ? 'rtl'
            : 'ltr';

        $safeTitle = htmlspecialchars(
            (string) $title,
            ENT_QUOTES,
            'UTF-8'
        );

        $safeMessage = nl2br(
            htmlspecialchars(
                (string) $message,
                ENT_QUOTES,
                'UTF-8'
            )
        );

        $safeActionUrl = $actionUrl
            ? htmlspecialchars(
                (string) $actionUrl,
                ENT_QUOTES,
                'UTF-8'
            )
            : null;

        $platformUrl = $safeActionUrl
            ?: 'https://uob.edu.bh';

        $html = '
        <!DOCTYPE html>
        <html dir="' . $direction . '">

        <head>

            <meta charset="UTF-8">

            <meta
                name="viewport"
                content="width=device-width, initial-scale=1.0"
            >

            <title>' . $safeTitle . '</title>

            <style>

                body {
                    font-family:
                        "Cairo",
                        Arial,
                        sans-serif;

                    background-color: #f4f7fb;

                    margin: 0;

                    padding: 20px;

                    color: #0f172a;
                }

                .email-container {
                    max-width: 600px;

                    margin: 0 auto;

                    background: #ffffff;

                    border-radius: 16px;

                    box-shadow:
                        0 8px 24px
                        rgba(0,0,0,0.08);

                    overflow: hidden;
                }

                .email-header {
                    background: #0b1f3a;

                    padding: 20px 30px;

                    text-align: center;
                }

                .email-header h1 {
                    color: #ffffff;

                    font-size: 20px;

                    font-weight: 700;

                    margin: 0;
                }

                .email-header .subtitle {
                    color: #c9a227;

                    font-size: 14px;

                    margin-top: 4px;
                }

                .email-body {
                    padding: 30px;
                }

                .email-title {
                    font-size: 18px;

                    font-weight: 700;

                    color: #0b1f3a;

                    margin: 0 0 12px 0;
                }

                .email-message {
                    color: #334155;

                    line-height: 1.8;

                    margin: 12px 0 20px 0;
                }

                .email-button {
                    display: inline-block;

                    background: #0b1f3a;

                    color: #ffffff !important;

                    padding: 12px 28px;

                    border-radius: 8px;

                    text-decoration: none;

                    font-weight: 600;
                }

                .email-footer {
                    background: #f8fafc;

                    padding: 16px 30px;

                    text-align: center;

                    color: #64748b;

                    font-size: 12px;

                    border-top:
                        1px solid #e6ebf2;
                }

                .email-footer a {
                    color: #0b1f3a;

                    text-decoration: none;
                }

                .text-center {
                    text-align: center;
                }

            </style>

        </head>

        <body>

            <div class="email-container">

                <div class="email-header">

                    <h1>
                        🔔 ' . $appName . '
                    </h1>

                    <div class="subtitle">
                        ' .
                        (
                            $isArabic
                            ? 'نظام الإشعارات'
                            : 'Notification System'
                        )
                        . '
                    </div>

                </div>

                <div class="email-body">

                    <div class="email-title">
                        ' . $safeTitle . '
                    </div>

                    <div class="email-message">
                        ' . $safeMessage . '
                    </div>';

        if ($safeActionUrl) {

            $html .= '
                    <div class="text-center">

                        <a
                            href="' . $safeActionUrl . '"
                            class="email-button"
                        >
                            ' . $buttonText . '
                        </a>

                    </div>';
        }

        $html .= '

                </div>

                <div class="email-footer">

                    ' . $footerText . '

                    <br>

                    <a href="' . $platformUrl . '">

                        ' .
                        (
                            $isArabic
                            ? 'زيارة المنصة'
                            : 'Visit Platform'
                        )
                        . '

                    </a>

                </div>

            </div>

        </body>

        </html>';

        return $html;
    }

    /**
     * Send notification email automatically.
     *
     * Uses the existing EmailService.php.
     *
     * IMPORTANT:
     * Email failure does NOT cancel the database notification.
     */
    private function sendNotificationEmail(
        string $userEmail,
        string $title,
        string $message,
        ?string $actionUrl = null
    ): bool {

        try {

            if (
                !filter_var(
                    $userEmail,
                    FILTER_VALIDATE_EMAIL
                )
            ) {

                error_log(
                    "Notification email skipped: "
                    . "Invalid user email: "
                    . $userEmail
                );

                return false;
            }

            $lang = $_SESSION['lang'] ?? $this->lang ?? 'ar';

            $isArabic = ($lang === 'ar');

            $emailTitle = $title;

            $emailMessage = $message;

            $emailHtml = $this->buildEmailContent(
                $emailTitle,
                $emailMessage,
                $actionUrl,
                $lang
            );

            $emailText = strip_tags(
                str_replace(
                    [
                        '<br>',
                        '<br/>',
                        '<br />'
                    ],
                    PHP_EOL,
                    $emailHtml
                )
            );

            $emailService = new EmailService();

            $sent = $emailService->send(
                $userEmail,
                '',
                $emailTitle,
                $emailHtml,
                $emailText
            );

            if ($sent) {

                error_log(
                    "Notification email sent successfully to: "
                    . $userEmail
                );

            } else {

                error_log(
                    "Notification email failed for: "
                    . $userEmail
                );
            }

            return $sent;

        } catch (Throwable $e) {

            error_log(
                "Notification email exception for "
                . $userEmail
                . ": "
                . $e->getMessage()
            );

            return false;
        }
    }

    /**
     * Create a notification in the database.
     *
     * After successful database insertion,
     * an email is automatically sent to the user.
     *
     * The email is sent through EmailService.php.
     *
     * You NO LONGER need to pass:
     *
     * 'send_email' => true
     *
     * Every newly created notification will automatically
     * attempt to send an email.
     */
    public function createNotification(array $data): ?int
{
    if (!$this->db) {
        return null;
    }

    try {
        $userId = isset($data['user_id'])
            ? (int) $data['user_id']
            : 0;

        if ($userId <= 0) {
            error_log(
                'Notification creation failed: invalid user_id'
            );

            return null;
        }

        /*
         * Get recipient email.
         */
        $stmt = $this->db->prepare("
            SELECT email
            FROM users
            WHERE user_id = :user_id
            AND is_active = TRUE
            LIMIT 1
        ");

        $stmt->execute([
            ':user_id' => $userId
        ]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        $userEmail = $user['email'] ?? null;

        /*
         * Content.
         */
        $titleAr = trim((string) (
            $data['title_ar']
            ?? $data['title']
            ?? ''
        ));

        $titleEn = trim((string) (
            $data['title_en']
            ?? $data['title']
            ?? ''
        ));

        $messageAr = trim((string) (
            $data['message_ar']
            ?? $data['message']
            ?? ''
        ));

        $messageEn = trim((string) (
            $data['message_en']
            ?? $data['message']
            ?? ''
        ));

        /*
         * Priority.
         */
        $priority = strtoupper(
            (string) (
                $data['priority']
                ?? 'NORMAL'
            )
        );

        $allowedPriorities = [
            'HIGH',
            'MEDIUM',
            'NORMAL',
            'LOW'
        ];

        if (!in_array(
            $priority,
            $allowedPriorities,
            true
        )) {
            $priority = 'NORMAL';
        }

        /*
         * IMPORTANT:
         *
         * Always convert this to a real PHP boolean.
         *
         * Do NOT pass:
         *
         * ''
         *
         * to PostgreSQL boolean columns.
         */
        $actionRequired =
            filter_var(
                $data['action_required'] ?? false,
                FILTER_VALIDATE_BOOLEAN
            );

        $entityType =
            $data['entity_type']
            ?? null;

        $entityId =
            isset($data['entity_id'])
            ? (int) $data['entity_id']
            : null;

        $entityCode =
            $data['entity_code']
            ?? null;

        $actionUrl =
            $data['action_url']
            ?? null;

        /*
         * Insert notification.
         */
        $sql = "
            INSERT INTO notifications (
                user_id,
                title_ar,
                title_en,
                message_ar,
                message_en,
                entity_type,
                entity_id,
                entity_code,
                priority,
                reminder_count,
                reminder_sent,
                action_required,
                action_url,
                is_read,
                is_archived,
                is_deleted
            )
            VALUES (
                :user_id,
                :title_ar,
                :title_en,
                :message_ar,
                :message_en,
                :entity_type,
                :entity_id,
                :entity_code,
                :priority,
                0,
                FALSE,
                :action_required,
                :action_url,
                FALSE,
                FALSE,
                FALSE
            )
            RETURNING notification_id
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->bindValue(
            ':user_id',
            $userId,
            PDO::PARAM_INT
        );

        $stmt->bindValue(
            ':title_ar',
            $titleAr
        );

        $stmt->bindValue(
            ':title_en',
            $titleEn
        );

        $stmt->bindValue(
            ':message_ar',
            $messageAr
        );

        $stmt->bindValue(
            ':message_en',
            $messageEn
        );

        $stmt->bindValue(
            ':entity_type',
            $entityType
        );

        if ($entityId !== null) {
            $stmt->bindValue(
                ':entity_id',
                $entityId,
                PDO::PARAM_INT
            );
        } else {
            $stmt->bindValue(
                ':entity_id',
                null,
                PDO::PARAM_NULL
            );
        }

        $stmt->bindValue(
            ':entity_code',
            $entityCode
        );

        $stmt->bindValue(
            ':priority',
            $priority
        );

        $stmt->bindValue(
            ':action_required',
            $actionRequired,
            PDO::PARAM_BOOL
        );

        $stmt->bindValue(
            ':action_url',
            $actionUrl
        );

        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $notificationId = $result
            ? (int) $result['notification_id']
            : null;

        /*
         * Send email ONLY after notification
         * was successfully inserted.
         */
        if ($notificationId && $userEmail) {

            $lang =
                $_SESSION['lang']
                ?? $this->lang
                ?? 'ar';

            $isArabic =
                $lang === 'ar';

            $emailTitle = $isArabic
                ? $titleAr
                : $titleEn;

            $emailMessage = $isArabic
                ? $messageAr
                : $messageEn;

            /*
             * Fallback if selected language is empty.
             */
            if (trim($emailTitle) === '') {
                $emailTitle = $isArabic
                    ? $titleEn
                    : $titleAr;
            }

            if (trim($emailMessage) === '') {
                $emailMessage = $isArabic
                    ? $messageEn
                    : $messageAr;
            }

            $this->sendNotificationEmail(
                $userEmail,
                $emailTitle,
                $emailMessage,
                $actionUrl,
                $lang
            );
        }

        return $notificationId;

    } catch (PDOException $e) {

        error_log(
            'Notification creation error: '
            . $e->getMessage()
        );

        return null;

    } catch (Throwable $e) {

        error_log(
            'Unexpected notification error: '
            . $e->getMessage()
        );

        return null;
    }
}
}
?>