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

            // ----------------------------------------
            // Resolve user ID
            // ----------------------------------------

            $userId = $data['user_id'] ?? null;

            if (
                !$userId &&
                !empty($data['email'])
            ) {

                $userId = $this->getUserIdByEmail(
                    (string) $data['email']
                );
            }

            if (!$userId) {

                error_log(
                    "Notification creation failed: "
                    . "User ID could not be resolved."
                );

                return null;
            }

            $userId = (int) $userId;

            // ----------------------------------------
            // Get user email
            // ----------------------------------------

            $userEmail = null;

            $stmt = $this->db->prepare("
                SELECT email
                FROM users
                WHERE user_id = :user_id
                LIMIT 1
            ");

            $stmt->execute([
                ':user_id' => $userId
            ]);

            $user = $stmt->fetch(
                PDO::FETCH_ASSOC
            );

            $userEmail =
                $user['email'] ?? null;

            // ----------------------------------------
            // Determine priority
            // ----------------------------------------

            $entityType =
                $data['entity_type'] ?? '';

            $priority =
                $data['priority'] ?? null;

            if (empty($priority)) {

                if (
                    $entityType === 'agreement' ||
                    $entityType === 'partnership'
                ) {

                    $priority = 'HIGH';

                } elseif (
                    $entityType === 'initiative'
                ) {

                    $priority = 'LOW';

                } else {

                    $priority = 'NORMAL';
                }
            }

            $allowedPriorities = [
                'HIGH',
                'MEDIUM',
                'NORMAL',
                'LOW'
            ];

            if (
                !in_array(
                    $priority,
                    $allowedPriorities,
                    true
                )
            ) {

                $priority = 'NORMAL';
            }

            // ----------------------------------------
            // Action required
            // ----------------------------------------

            $actionRequired = true;

            if (array_key_exists('action_required', $data)) {
                $value = $data['action_required'];

                if (
                    $value === false ||
                    $value === 0 ||
                    $value === '0' ||
                    $value === 'false' ||
                    $value === ''
                ) {
                    $actionRequired = false;
                } else {
                    $actionRequired = true;
                }
            }

            // ----------------------------------------
            // Prepare notification content
            // ----------------------------------------

            $titleAr =
                $data['title_ar']
                ?? $data['title']
                ?? '';

            $titleEn =
                $data['title_en']
                ?? $data['title']
                ?? '';

            $messageAr =
                $data['message_ar']
                ?? $data['message']
                ?? '';

            $messageEn =
                $data['message_en']
                ?? $data['message']
                ?? '';

            $actionUrl =
                $data['action_url']
                ?? null;

            // ----------------------------------------
            // Insert notification
            // ----------------------------------------

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
                $titleAr,
                PDO::PARAM_STR
            );

            $stmt->bindValue(
                ':title_en',
                $titleEn,
                PDO::PARAM_STR
            );

            $stmt->bindValue(
                ':message_ar',
                $messageAr,
                PDO::PARAM_STR
            );

            $stmt->bindValue(
                ':message_en',
                $messageEn,
                PDO::PARAM_STR
            );

            $stmt->bindValue(
                ':entity_type',
                $entityType,
                PDO::PARAM_STR
            );

            $stmt->bindValue(
                ':entity_id',
                $data['entity_id'] ?? null,
                $data['entity_id'] === null
                    ? PDO::PARAM_NULL
                    : PDO::PARAM_INT
            );

            $stmt->bindValue(
                ':entity_code',
                $data['entity_code'] ?? null,
                $data['entity_code'] === null
                    ? PDO::PARAM_NULL
                    : PDO::PARAM_STR
            );

            $stmt->bindValue(
                ':priority',
                $priority,
                PDO::PARAM_STR
            );

            /*
            * PostgreSQL boolean.
            *
            * Send TRUE/FALSE as strings rather than an empty string.
            */
            $stmt->bindValue(
                ':action_required',
                $actionRequired ? 'TRUE' : 'FALSE',
                PDO::PARAM_STR
            );

            $stmt->bindValue(
                ':action_url',
                $actionUrl,
                $actionUrl === null
                    ? PDO::PARAM_NULL
                    : PDO::PARAM_STR
            );

            $stmt->execute();

            $result = $stmt->fetch(
                PDO::FETCH_ASSOC
            );

            $notificationId =
                $result
                ? (int) $result['notification_id']
                : null;

            // ----------------------------------------
            // Automatically send email
            // ----------------------------------------

            if (
                $notificationId &&
                $userEmail
            ) {

                $lang =
                    $_SESSION['lang']
                    ?? $this->lang
                    ?? 'ar';

                $isArabic =
                    ($lang === 'ar');

                $emailTitle =
                    $isArabic
                    ? $titleAr
                    : $titleEn;

                $emailMessage =
                    $isArabic
                    ? $messageAr
                    : $messageEn;

                // If the selected language content is empty,
                // use the other language as fallback.
                if (
                    trim((string) $emailTitle) === ''
                ) {

                    $emailTitle =
                        $isArabic
                        ? $titleEn
                        : $titleAr;
                }

                if (
                    trim((string) $emailMessage) === ''
                ) {

                    $emailMessage =
                        $isArabic
                        ? $messageEn
                        : $messageAr;
                }

                $this->sendNotificationEmail(
                    (string) $userEmail,
                    (string) $emailTitle,
                    (string) $emailMessage,
                    $actionUrl
                );
            } else {

                if (!$userEmail) {

                    error_log(
                        "Notification created but email was not sent: "
                        . "No email found for user_id="
                        . $userId
                    );
                }
            }

            // ----------------------------------------
            // Return notification ID
            // ----------------------------------------

            return $notificationId;

        } catch (PDOException $e) {

            error_log(
                "Notification creation error: "
                . $e->getMessage()
            );

            return null;

        } catch (Throwable $e) {

            error_log(
                "Unexpected notification error: "
                . $e->getMessage()
            );

            return null;
        }
    }
}
?>