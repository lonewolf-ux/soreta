<?php
require_once 'includes/config.php';
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['user_role'] = 'admin';
$_SESSION['csrf_token'] = CSRFProtection::generateToken();
$db = new Database();
$pdo = $db->getConnection();
$stmt = $pdo->query('SELECT COUNT(*) as count FROM notifications');
$result = $stmt->fetch();
echo 'Total notifications: ' . $result['count'] . PHP_EOL;
$stmt = $pdo->query('SELECT COUNT(*) as count FROM notifications WHERE is_read = 0');
$result = $stmt->fetch();
echo 'Unread notifications: ' . $result['count'] . PHP_EOL;
?>
