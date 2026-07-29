<?php
// debug_notifications.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔍 Debug Notifications</h1>";

try {
    require_once __DIR__ . '/includes/config.php';
    require_once __DIR__ . '/includes/functions.php';
    require_once __DIR__ . '/services/NotificationService.php';
    
    echo "✅ Files loaded<br>";
    
    if (session_status() === PHP_SESSION_NONE) session_start();
    
    if (empty($_SESSION['user_email'])) {
        echo "❌ No user logged in<br>";
        echo "<a href='login.php'>Login</a>";
        exit;
    }
    
    echo "✅ User: " . $_SESSION['user_email'] . "<br>";
    
    $notificationService = new NotificationService();
    echo "✅ NotificationService created<br>";
    
    $email = $_SESSION['user_email'];
    $userId = $notificationService->getUserIdByEmail($email);
    echo "✅ User ID: " . $userId . "<br>";
    
    if (!$userId) {
        echo "❌ User not found in database<br>";
        exit;
    }
    
    $unreadCount = $notificationService->getUnreadCount($userId);
    echo "✅ Unread count: " . $unreadCount . "<br>";
    
    $notifications = $notificationService->getUserNotifications($userId, false, 10);
    echo "✅ Notifications found: " . count($notifications) . "<br>";
    
    echo "<h3>Notifications:</h3>";
    echo "<pre>";
    print_r($notifications);
    echo "</pre>";
    
    echo "<h3>✅ All tests passed!</h3>";
    echo "<p>Click here to view notifications: <a href='notifications.php'>Notifications Page</a></p>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "<br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>