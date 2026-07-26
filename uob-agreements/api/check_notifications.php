<?php
// api/check_notifications.php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_email'])) {
    echo json_encode([
        'success' => false,
        'error' => 'User not logged in'
    ]);
    exit;
}

require_once __DIR__ . '/../services/NotificationService.php';

$notificationService = new NotificationService();
$email = $_SESSION['user_email'];
$userId = $notificationService->getUserIdByEmail($email);

if (!$userId) {
    echo json_encode([
        'success' => false,
        'error' => 'User not found'
    ]);
    exit;
}

$lastCheck = isset($_GET['last_check']) ? (int)$_GET['last_check'] : 0;
$notifications = $notificationService->getUserNotifications($userId, true, 5);

$newNotifications = [];
foreach ($notifications as $notif) {
    $createdAt = strtotime($notif['created_at']);
    if ($createdAt > $lastCheck) {
        $newNotifications[] = $notif;
    }
}

if (!empty($newNotifications)) {
    $latest = $newNotifications[0];
    
    $type = 'system';
    if (strpos($latest['title_ar'], 'موافقة') !== false || strpos($latest['title_en'], 'Approval') !== false) {
        $type = 'approval';
    } elseif (strpos($latest['title_ar'], 'طلب') !== false || strpos($latest['title_en'], 'Request') !== false) {
        $type = 'workflow';
    } elseif (strpos($latest['title_ar'], 'رفض') !== false || strpos($latest['title_en'], 'Rejected') !== false) {
        $type = 'rejected';
    } elseif (strpos($latest['title_ar'], 'نجاح') !== false || strpos($latest['title_en'], 'Success') !== false) {
        $type = 'success';
    }
    
    echo json_encode([
        'success' => true,
        'has_new' => true,
        'notification' => [
            'id' => $latest['notification_id'],
            'title_ar' => $latest['title_ar'] ?? 'إشعار جديد',
            'title_en' => $latest['title_en'] ?? 'New Notification',
            'message_ar' => $latest['message_ar'] ?? '',
            'message_en' => $latest['message_en'] ?? '',
            'type' => $type,
            'created_at' => $latest['created_at'],
            'timestamp' => strtotime($latest['created_at'])
        ]
    ]);
} else {
    echo json_encode([
        'success' => true,
        'has_new' => false
    ]);
}
?>