<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/services/NotificationService.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$_SESSION['lang'] = 'en';

$service = new NotificationService();

$notificationId = $service->createNotification([
    'user_id' => 31,

    'title_ar' => '🔔 اختبار الإشعارات',
    'title_en' => '🔔 Notification Test',

    'message_ar' => 'تم إنشاء إشعار تجريبي بنجاح.',
    'message_en' => 'This is a test notification from the UOB portal.',

    'entity_type' => 'test',
    'entity_id' => null,
    'entity_code' => 'TEST-EMAIL-001',

    'priority' => 'NORMAL',

    'action_required' => false,

    'action_url' => 'http://localhost/Uob-partnerships-sustainability/uob-agreements/notifications.php'
]);

if ($notificationId !== null) {

    echo '<h1>SUCCESS</h1>';

    echo '<p>';
    echo 'Notification ID: '
        . htmlspecialchars((string) $notificationId);
    echo '</p>';

    echo '<p>';
    echo 'The notification was created successfully.';
    echo '</p>';

    echo '<p>';
    echo 'The system also attempted to send the email.';
    echo '</p>';

} else {

    echo '<h1>FAILED</h1>';

    echo '<p>';
    echo 'The notification could not be created.';
    echo '</p>';
}