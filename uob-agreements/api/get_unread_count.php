<?php
// api/get_unread_count.php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_email'])) {
    echo json_encode(['count' => 0]);
    exit;
}

require_once __DIR__ . '/../services/NotificationService.php';

$notificationService = new NotificationService();
$userId = $notificationService->getUserIdByEmail($_SESSION['user_email']);

$count = $userId ? $notificationService->getUnreadCount($userId) : 0;

echo json_encode(['count' => $count]);
?>