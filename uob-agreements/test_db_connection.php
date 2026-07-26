<?php
// test_db_connection.php - Test database connection

echo "<h2>Database Connection Test</h2>";

try {
    // Connection details
    $host = 'localhost';
    $port = '5432';
    $dbname = 'UOB_Partnership_and_Initiative';
    $user = 'postgres';
    $password = 'fatema_fruit_20&04';
    
    echo "Connecting to: $host:$port/$dbname as $user<br>";
    
    // Try to connect
    $db = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✅ Database connection successful!<br><br>";
    
    // Check if users table exists
    $stmt = $db->query("SELECT table_name FROM information_schema.tables WHERE table_name = 'users'");
    $tableExists = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($tableExists) {
        echo "✅ Users table exists<br>";
        
        // Check if there are users
        $stmt = $db->query("SELECT COUNT(*) as count FROM users");
        $count = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "✅ Users in table: " . $count['count'] . "<br><br>";
        
        // Show all users
        $stmt = $db->query("SELECT user_id, university_id, first_name, last_name, email FROM users");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<b>All users:</b><br>";
        echo "<pre>";
        print_r($users);
        echo "</pre>";
        
    } else {
        echo "❌ Users table does not exist!<br>";
        echo "You may be connected to the wrong database.";
    }
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    echo "Please check your credentials.";
}
?>