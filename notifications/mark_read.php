<?php
require_once '../includes/config.php';
checkAuth();

$db = new Database();
$pdo = $db->getConnection();

$input = json_decode(file_get_contents('php://input'), true);
$markAll = $input['mark_all'] ?? false;
$notificationId = $input['notification_id'] ?? null;

if ($markAll) {
    $userRole = $_SESSION['user_role'] ?? null;
    if ($userRole === 'admin') {
        // Admins can mark all unread notifications as read
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE is_read = 0");
        $stmt->execute();
    } else {
        // Other users mark only their own
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$_SESSION['user_id']]);
    }
} elseif ($notificationId) {
    // Mark specific notification as read (admins can mark any, others only their own)
    $userRole = $_SESSION['user_role'] ?? null;
    if ($userRole === 'admin') {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND is_read = 0");
        $stmt->execute([$notificationId]);
    } else {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ? AND is_read = 0");
        $stmt->execute([$notificationId, $_SESSION['user_id']]);
    }
}

jsonResponse(true, 'Notifications marked as read');
?>
