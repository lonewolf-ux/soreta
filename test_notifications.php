<?php
require_once 'includes/config.php';
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['user_role'] = 'admin';
$_SESSION['csrf_token'] = CSRFProtection::generateToken();

$db = new Database();
$pdo = $db->getConnection();

try {
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM notifications');
    $result = $stmt->fetch();
    echo 'Total notifications: ' . $result['count'] . PHP_EOL;

    $stmt = $pdo->query('SELECT COUNT(*) as count FROM notifications WHERE is_read = 0');
    $result = $stmt->fetch();
    echo 'Unread notifications: ' . $result['count'] . PHP_EOL;

    // Test get_notifications.php
    echo PHP_EOL . 'Testing get_notifications.php:' . PHP_EOL;
    ob_start();
    include 'notifications/get_notifications.php';
    $output = ob_get_clean();
    echo 'Output: ' . substr($output, 0, 200) . '...' . PHP_EOL;

    // Test get_notification_count.php
    echo PHP_EOL . 'Testing get_notification_count.php:' . PHP_EOL;
    ob_start();
    include 'admin/get_notification_count.php';
    $output = ob_get_clean();
    echo 'Output: ' . substr($output, 0, 200) . '...' . PHP_EOL;

} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
}
?>
