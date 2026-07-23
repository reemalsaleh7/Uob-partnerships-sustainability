<?php
// test_workflow.php - Fixed Version
require_once 'services/WorkflowService.php';

echo "<h1>🧪 Workflow System Test</h1>";

$workflow = new WorkflowService();

// Create a direct database connection
try {
    $host = 'localhost';
    $port = '5432';
    $dbname = 'UOB_Partnership_and_Initiative';
    $user = 'postgres';
    $password = 'fatema_fruit_20&04';
    
    $db = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Database connected! (UOB_Partnership_and_Initiative)<br><br>";
} catch (PDOException $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "<br>";
    exit;
}

// ------------------------------------------------------------
// 1. Show All Users
// ------------------------------------------------------------
echo "<h2>📋 1. User List</h2>";
$users = $db->query("SELECT user_id, first_name, last_name, email FROM users")->fetchAll(PDO::FETCH_ASSOC);
echo "<table border='1' cellpadding='8'>";
echo "<tr><th>ID</th><th>Name</th><th>Email</th></tr>";
foreach ($users as $u) {
    echo "<tr><td>{$u['user_id']}</td><td>{$u['first_name']} {$u['last_name']}</td><td>{$u['email']}</td></tr>";
}
echo "</table><br>";

// ------------------------------------------------------------
// 2. Show Initiatives
// ------------------------------------------------------------
echo "<h2>📋 2. Initiatives</h2>";
$initiatives = $db->query("SELECT initiative_id, title, status FROM initiatives")->fetchAll(PDO::FETCH_ASSOC);
if (empty($initiatives)) {
    echo "⚠️ No initiatives found.<br>";
} else {
    echo "<table border='1' cellpadding='8'>";
    echo "<tr><th>ID</th><th>Title</th><th>Status</th></tr>";
    foreach ($initiatives as $i) {
        echo "<tr><td>{$i['initiative_id']}</td><td>{$i['title']}</td><td>{$i['status']}</td></tr>";
    }
    echo "</table><br>";
}

// ------------------------------------------------------------
// 3. Show Agreements
// ------------------------------------------------------------
echo "<h2>📋 3. Agreements</h2>";
$agreements = $db->query("SELECT agreement_id, title, status FROM agreements")->fetchAll(PDO::FETCH_ASSOC);
if (empty($agreements)) {
    echo "⚠️ No agreements found.<br>";
} else {
    echo "<table border='1' cellpadding='8'>";
    echo "<tr><th>ID</th><th>Title</th><th>Status</th></tr>";
    foreach ($agreements as $a) {
        echo "<tr><td>{$a['agreement_id']}</td><td>{$a['title']}</td><td>{$a['status']}</td></tr>";
    }
    echo "</table><br>";
}

// ------------------------------------------------------------
// 4. Test Submit Initiative (Using ID 1)
// ------------------------------------------------------------
echo "<h2>📋 4. Test Submit Initiative</h2>";

// Use initiative ID 1
$initiativeId = 1;
$doctor = $db->query("SELECT user_id FROM users WHERE email = 'doctor@uob.edu.bh' LIMIT 1")->fetch(PDO::FETCH_ASSOC);

if ($doctor) {
    echo "👨‍⚕️ Doctor (ID: " . $doctor['user_id'] . ") submitting initiative #{$initiativeId}...<br>";
    try {
        $result = $workflow->submitInitiative($initiativeId, $doctor['user_id']);
        echo $result ? "✅ Initiative submitted for approval!<br>" : "❌ Failed to submit initiative<br>";
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "<br>";
    }
} else {
    echo "⚠️ Doctor user not found.<br>";
}

// ------------------------------------------------------------
// 5. Test Submit Agreement (Using ID 1)
// ------------------------------------------------------------
echo "<h2>📋 5. Test Submit Agreement</h2>";

$agreementId = 1;
$dean = $db->query("SELECT user_id FROM users WHERE email = 'dean@uob.edu.bh' LIMIT 1")->fetch(PDO::FETCH_ASSOC);

if ($dean) {
    echo "👨‍🏫 Dean (ID: " . $dean['user_id'] . ") submitting agreement #{$agreementId}...<br>";
    try {
        $result = $workflow->submitPartnership($agreementId, $dean['user_id']);
        echo $result ? "✅ Agreement submitted for approval!<br>" : "❌ Failed to submit agreement<br>";
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "<br>";
    }
} else {
    echo "⚠️ Dean user not found.<br>";
}

// ------------------------------------------------------------
// 6. Show Workflow Status
// ------------------------------------------------------------
echo "<h2>📋 6. Workflow Status</h2>";

// Show workflow_instances
$instances = $db->query("SELECT workflow_instance_id, entity_type, entity_id, current_step, status FROM workflow_instances")->fetchAll(PDO::FETCH_ASSOC);
if (empty($instances)) {
    echo "⚠️ No workflow instances found<br>";
} else {
    echo "<h3>Workflow Instances:</h3>";
    echo "<table border='1' cellpadding='8'>";
    echo "<tr><th>ID</th><th>Type</th><th>Reference</th><th>Step</th><th>Status</th></tr>";
    foreach ($instances as $inst) {
        echo "<tr><td>{$inst['workflow_instance_id']}</td><td>{$inst['entity_type']}</td><td>{$inst['entity_id']}</td><td>{$inst['current_step']}</td><td>{$inst['status']}</td></tr>";
    }
    echo "</table><br>";
}

// Show workflow_instance_steps
$steps = $db->query("SELECT instance_step_id, workflow_instance_id, step_order, status, comments FROM workflow_instance_steps")->fetchAll(PDO::FETCH_ASSOC);
if (empty($steps)) {
    echo "⚠️ No workflow steps found<br>";
} else {
    echo "<h3>Workflow Steps:</h3>";
    echo "<table border='1' cellpadding='8'>";
    echo "<tr><th>ID</th><th>Instance</th><th>Step</th><th>Status</th><th>Comments</th></tr>";
    foreach ($steps as $s) {
        echo "<tr><td>{$s['instance_step_id']}</td><td>{$s['workflow_instance_id']}</td><td>{$s['step_order']}</td><td>{$s['status']}</td><td>{$s['comments']}</td></tr>";
    }
    echo "</table><br>";
}

// ------------------------------------------------------------
// 7. Show Notifications
// ------------------------------------------------------------
echo "<h2>📋 7. Notifications</h2>";
$notifications = $db->query("SELECT notification_id, user_id, title_ar, is_read, created_at FROM notifications ORDER BY created_at DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
if (empty($notifications)) {
    echo "⚠️ No notifications found<br>";
} else {
    echo "<table border='1' cellpadding='8'>";
    echo "<tr><th>ID</th><th>User</th><th>Title</th><th>Read</th><th>Date</th></tr>";
    foreach ($notifications as $n) {
        echo "<tr><td>{$n['notification_id']}</td><td>{$n['user_id']}</td><td>{$n['title_ar']}</td><td>" . ($n['is_read'] ? '✅' : '❌') . "</td><td>{$n['created_at']}</td></tr>";
    }
    echo "</table><br>";
}

echo "<hr>";
echo "✅ Test completed!";
?>