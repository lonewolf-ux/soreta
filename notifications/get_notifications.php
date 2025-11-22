<?php
require_once '../includes/config.php';
checkAuth();

$db = new Database();
$pdo = $db->getConnection();

// Get unread notifications
$userRole = $_SESSION['user_role'] ?? null;
if ($userRole === 'admin') {
    // Admins: return recent notifications (both read and unread) and compute unread count separately
    $stmt = $pdo->prepare("SELECT * FROM notifications ORDER BY created_at DESC LIMIT 10");
    $stmt->execute();
} else {
    // Other users: return recent notifications for the user (both read and unread)
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
    $stmt->execute([$_SESSION['user_id']]);
}
$notifications = $stmt->fetchAll();

// Format notifications for display
$formattedNotifications = [];
foreach ($notifications as $notification) {
    $formattedNotifications[] = [
        'id' => $notification['id'],
        'message' => htmlspecialchars($notification['message'] ?? ''),
        'link' => getNotificationLink($notification),
        'time' => timeAgo($notification['created_at'] ?? date('Y-m-d H:i:s')),
        'created_at' => $notification['created_at'] ?? null,
        'related_id' => $notification['related_id'] ?? null,
        'related_type' => $notification['related_type'] ?? null,
        'is_read' => (bool)($notification['is_read'] ?? 0),
    ];
}

// Get unread count
if ($userRole === 'admin') {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE is_read = 0");
    $stmt->execute();
} else {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$_SESSION['user_id']]);
}
$unreadCount = $stmt->fetch()['count'];

jsonResponse(true, '', [
    'notifications' => $formattedNotifications,
    'unread_count' => $unreadCount
]);

// Debug logging
error_log("Notifications API called by user_id: " . ($_SESSION['user_id'] ?? 'unknown') . ", role: " . ($userRole ?? 'unknown'));
error_log("Found " . count($formattedNotifications) . " notifications, unread_count: $unreadCount");

function getNotificationLink($notification) {
    $type = $notification['related_type'] ?? null;
    switch ($type) {
        case 'appointment':
            return 'admin/appointments.php';
        case 'feedback':
            return 'admin/feedback.php';
        default:
            return '#';
    }
}

function timeAgo($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;

    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        return floor($diff / 60) . ' minutes ago';
    } elseif ($diff < 86400) {
        return floor($diff / 3600) . ' hours ago';
    } else {
        return floor($diff / 86400) . ' days ago';
    }
}
?>