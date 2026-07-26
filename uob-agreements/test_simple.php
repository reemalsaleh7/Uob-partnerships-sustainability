<?php
// test_simple.php - Simple direct insert test
echo "<h2>Simple Notification Test</h2>";

try {
    $host = 'localhost';
    $port = '5432';
    $dbname = 'UOB_Partnership_and_Initiative';
    $user = 'postgres';
    $password = 'fatema_fruit_20&04';
    
    $db = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✅ Database connected!<br><br>";
    
    // Simple insert
    $sql = "INSERT INTO notifications (
                user_id, title_ar, title_en, 
                message_ar, message_en,
                action_required, is_read, is_archived, is_deleted
            ) VALUES (
                1, 
                'اختبار بسيط', 
                'Simple Test',
                'تم إنشاء هذا الإشعار من PHP مباشرة',
                'This notification was created directly from PHP',
                TRUE, FALSE, FALSE, FALSE
            ) RETURNING notification_id";
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        echo "✅ Notification created successfully! ID: " . $result['notification_id'] . "<br>";
    } else {
        echo "❌ Failed to create notification<br>";
    }
    
    // Show all notifications
    echo "<br><b>All notifications:</b><br>";
    $stmt2 = $db->query("SELECT notification_id, title_ar, is_read, created_at FROM notifications ORDER BY created_at DESC LIMIT 5");
    $notifs = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>";
    print_r($notifs);
    echo "</pre>";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}
?>