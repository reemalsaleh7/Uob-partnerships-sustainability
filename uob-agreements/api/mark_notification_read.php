<?php
// api/mark_notification_read.php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_email'])) {
    echo json_encode([
        'success' => false,
        'error' => 'User not logged in'
    ]);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$notificationId = $data['notification_id'] ?? 0;

if (!$notificationId) {
    echo json_encode([
        'success' => false,
        'error' => 'Notification ID required'
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

$result = $notificationService->markAsRead($notificationId, $userId);

echo json_encode([
    'success' => $result,
    'message' => $result ? 'Notification marked as read' : 'Failed to mark as read'
]);
?>