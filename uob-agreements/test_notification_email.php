<?php
// test_notification_email.php
require_once 'services/NotificationService.php';

echo "<h2>📧 Test Notification for 202208354@stu.uob.edu.bh</h2>";

$notifService = new NotificationService();

// Get user ID by email
$userId = $notifService->getUserIdByEmail('202208354@stu.uob.edu.bh');

if (!$userId) {
    echo "❌ User not found: 202208354@stu.uob.edu.bh<br>";
    exit;
}

echo "✅ User ID: " . $userId . "<br>";

// Create notification
$result = $notifService->createNotification([
    'user_id' => $userId,
    'title_ar' => '📌 Notification test',
    'title_en' => '📌 Notification test',
    'message_ar' => 'This is a notification-module test for 202208354@stu.uob.edu.bh',
    'message_en' => 'This is a notification-module test for 202208354@stu.uob.edu.bh',
    'priority' => 'HIGH',
    'action_required' => false,
    'action_url' => '/notifications.php',
    'send_email' => false  // No email delivery yet
]);

if ($result) {
    echo "✅ Notification created successfully! ID: " . $result . "<br>";
    echo "📋 Check the notifications page: <a href='notifications.php'>Notifications</a><br>";
} else {
    echo "❌ Failed to create notification<br>";
}

// Show all notifications for this user
echo "<h3>All notifications for user " . $userId . ":</h3>";
$db = $notifService->getDbConnection();
$stmt = $db->query("SELECT notification_id, title_ar, is_read, created_at FROM notifications WHERE user_id = " . $userId . " ORDER BY created_at DESC");
$notifs = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table border='1' cellpadding='8'>";
echo "<tr><th>ID</th><th>Title</th><th>Read</th><th>Created</th></tr>";
foreach ($notifs as $n) {
    echo "<tr>";
    echo "<td>" . $n['notification_id'] . "</td>";
    echo "<td>" . $n['title_ar'] . "</td>";
    echo "<td>" . ($n['is_read'] ? '✅' : '❌') . "</td>";
    echo "<td>" . $n['created_at'] . "</td>";
    echo "</tr>";
}
echo "</table>";
?>