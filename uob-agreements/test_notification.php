<?php
// test_notification.php - Updated Version (No notification_type)
echo "test_notification.php is loading!<br>";

require_once 'services/NotificationService.php';

echo "NotificationService loaded!<br>";

$notif = new NotificationService();
echo "NotificationService instantiated!<br>";

try {
    $db = $notif->getDbConnection();
    
    if (!$db) {
        echo "❌ Database connection failed!<br>";
        exit;
    }
    
    echo "✅ Database connection successful!<br><br>";
    
    // Get users
    $stmt = $db->query("SELECT user_id, first_name, last_name, email FROM users");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<b>Users found: " . count($users) . "</b><br>";
    
    if (!empty($users)) {
        $user = $users[0];
        echo "✅ Using user: " . $user['first_name'] . " " . $user['last_name'] . " (ID: " . $user['user_id'] . ")<br>";
        echo "Email: " . $user['email'] . "<br><br>";
        
        // Now try using the service class (this works!)
        echo "<b>Creating notification using service class...</b><br>";
        
        try {
            $result = $notif->createNotification([
                'user_id' => $user['user_id'],
                'title_ar' => '📌 اختبار الإشعارات',
                'title_en' => '📌 Test Notification',
                'message_ar' => 'تم إعداد نظام الإشعارات بنجاح! 🎉',
                'message_en' => 'Notification system setup successful! 🎉',
                'action_required' => false,
                'action_url' => '/notifications.php'
            ]);
            
            if ($result) {
                echo "✅ Notification created successfully! ID: " . $result . "<br>";
                echo "💡 Toast notification should appear in the top corner!<br>";
            } else {
                echo "❌ Failed to create notification<br>";
            }
        } catch (Exception $e) {
            echo "❌ Error: " . $e->getMessage() . "<br>";
        }
        
        // Show all notifications
        echo "<br><b>All notifications in database:</b><br>";
        $stmt3 = $db->query("SELECT notification_id, title_ar, title_en, is_read, created_at FROM notifications ORDER BY created_at DESC LIMIT 10");
        $notifs = $stmt3->fetchAll(PDO::FETCH_ASSOC);
        echo "<pre>";
        print_r($notifs);
        echo "</pre>";
        
    } else {
        echo "❌ No users found!";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}
?>