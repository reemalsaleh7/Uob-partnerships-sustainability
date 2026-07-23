<?php
// test_debug.php - Fixed
require_once 'services/WorkflowService.php';

echo "<h1>Debug WorkflowService</h1>";

// Create direct database connection
try {
    $host = 'localhost';
    $port = '5432';
    $dbname = 'UOB_Partnership_and_Initiative';
    $user = 'postgres';
    $password = 'fatema_fruit_20&04';
    
    $db = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Database connected!<br><br>";
} catch (PDOException $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "<br>";
    exit;
}

$workflow = new WorkflowService();

// Show initiative
$initiative = $db->query("SELECT initiative_id, title, created_by FROM initiatives WHERE initiative_id = 1")->fetch(PDO::FETCH_ASSOC);
echo "<b>Initiative:</b><br>";
echo "<pre>";
print_r($initiative);
echo "</pre><br>";

// Show doctor
$doctor = $db->query("SELECT user_id, first_name, last_name FROM users WHERE email = 'doctor@uob.edu.bh'")->fetch(PDO::FETCH_ASSOC);
echo "<b>Doctor:</b><br>";
echo "<pre>";
print_r($doctor);
echo "</pre><br>";

// Show user level
if ($doctor) {
    // Try to call the method - it might be private, so let's check
    echo "<b>User level check:</b><br>";
    try {
        $reflection = new ReflectionMethod($workflow, 'getUserLevel');
        $reflection->setAccessible(true);
        $userLevel = $reflection->invoke($workflow, $doctor['user_id']);
        echo "User level: " . $userLevel . "<br><br>";
    } catch (Exception $e) {
        echo "Could not get user level: " . $e->getMessage() . "<br><br>";
    }
}

// Show workflow template steps
$steps = $db->query("SELECT * FROM workflow_template_steps WHERE workflow_template_id = 2 ORDER BY step_order")->fetchAll(PDO::FETCH_ASSOC);
echo "<b>Workflow Template Steps:</b><br>";
echo "<pre>";
print_r($steps);
echo "</pre><br>";

// Show workflow templates
$templates = $db->query("SELECT * FROM workflow_templates")->fetchAll(PDO::FETCH_ASSOC);
echo "<b>Workflow Templates:</b><br>";
echo "<pre>";
print_r($templates);
echo "</pre><br>";

// Try to submit with error reporting
if ($initiative && $doctor) {
    echo "<br><b>Attempting to submit initiative #" . $initiative['initiative_id'] . "...</b><br>";
    try {
        $result = $workflow->submitInitiative($initiative['initiative_id'], $doctor['user_id']);
        echo $result ? "✅ Success!" : "❌ Failed";
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "<br>";
        echo "File: " . $e->getFile() . ":" . $e->getLine() . "<br>";
    }
}
?>