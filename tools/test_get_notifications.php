<?php
require_once __DIR__ . '/../includes/config.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();
    $userId = 1; // test for admin 1

    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 10");
    $stmt->execute([$userId]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($notifications, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
