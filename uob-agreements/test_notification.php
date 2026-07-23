<?php
// test_notification.php - Final Version
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
        
        // SIMPLE DIRECT INSERT - Skip the service class for now
        echo "<b>Attempting direct insert...</b><br>";
        
        try {
            $sql = "INSERT INTO notifications (
                        user_id, notification_type, 
                        title_ar, title_en, 
                        message_ar, message_en,
                        action_required, is_read, is_archived, is_deleted
                    ) VALUES (
                        :user_id, 'TEST',
                        'اختبار مباشر', 'Direct Test',
                        'تم إنشاء هذا الإشعار مباشرة', 'This notification was created directly',
                        TRUE, FALSE, FALSE, FALSE
                    ) RETURNING notification_id";
            
            $stmt2 = $db->prepare($sql);
            $stmt2->execute([':user_id' => $user['user_id']]);
            $result = $stmt2->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                echo "✅ Direct insert successful! ID: " . $result['notification_id'] . "<br>";
            } else {
                echo "❌ Direct insert failed<br>";
            }
        } catch (PDOException $e) {
            echo "❌ Direct insert error: " . $e->getMessage() . "<br>";
        }
        
        // Now try using the service class
        echo "<br><b>Attempting using service class...</b><br>";
        
        try {
            $result = $notif->createNotification([
                'user_id' => $user['user_id'],
                'notification_type' => 'TEST',
                'title_ar' => 'اختبار الإشعارات (خدمة)',
                'title_en' => 'Test Notification (Service)',
                'message_ar' => 'تم إعداد نظام الإشعارات بنجاح!',
                'message_en' => 'Notification system setup successful!',
                'priority' => 'HIGH',
                'action_required' => true,
                'action_url' => '/notifications.php'
            ]);
            
            if ($result) {
                echo "✅ Service class successful! ID: " . $result . "<br>";
            } else {
                echo "❌ Service class failed<br>";
            }
        } catch (Exception $e) {
            echo "❌ Service class error: " . $e->getMessage() . "<br>";
        }
        
        // Show all notifications
        echo "<br><b>All notifications in database:</b><br>";
        $stmt3 = $db->query("SELECT notification_id, title_ar, title_en, is_read, created_at FROM notifications");
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