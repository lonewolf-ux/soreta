<?php
require_once '../includes/config.php';
checkAuth();

if (!isAdmin()) {
    jsonResponse(false, "Unauthorized access.");
}

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    // Count unread notifications
    $userRole = $_SESSION['user_role'] ?? null;
    if ($userRole === 'admin') {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as unread_count 
            FROM notifications 
            WHERE is_read = FALSE
        ");
        $stmt->execute();
    } else {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as unread_count 
            FROM notifications 
            WHERE user_id = ? AND is_read = FALSE
        ");
        $stmt->execute([$_SESSION['user_id']]);
    }
    $result = $stmt->fetch();
    
    jsonResponse(true, "", ['unread_count' => $result['unread_count']]);
    
} catch (Exception $e) {
    jsonResponse(false, $e->getMessage());
}
?>