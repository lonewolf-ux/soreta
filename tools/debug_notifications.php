<?php
require_once __DIR__ . '/../includes/config.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();

    echo "=== NOTIFICATIONS DEBUG ===\n\n";

    // Check total notifications
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM notifications");
    $total = $stmt->fetch()['total'];
    echo "Total notifications: $total\n";

    // Check unread notifications
    $stmt = $pdo->query("SELECT COUNT(*) as unread FROM notifications WHERE is_read = 0");
    $unread = $stmt->fetch()['unread'];
    echo "Unread notifications: $unread\n\n";

    // Show recent notifications
    $stmt = $pdo->query("SELECT * FROM notifications ORDER BY created_at DESC LIMIT 5");
    $recent = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Recent notifications:\n";
    foreach ($recent as $notif) {
        echo sprintf(
            "ID: %d | User: %d | Type: %s | Read: %s | Created: %s\nMessage: %s\n\n",
            $notif['id'],
            $notif['user_id'],
            $notif['type'] ?? 'null',
            $notif['is_read'] ? 'yes' : 'no',
            $notif['created_at'],
            substr($notif['message'], 0, 100) . (strlen($notif['message']) > 100 ? '...' : '')
        );
    }

    // Test admin query
    echo "=== ADMIN QUERY TEST ===\n";
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE is_read = 0 ORDER BY created_at DESC LIMIT 10");
    $stmt->execute();
    $adminNotifs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Admin sees " . count($adminNotifs) . " notifications\n";

    // Test customer query
    echo "=== CUSTOMER QUERY TEST ===\n";
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 10");
    $stmt->execute([11]); // Test with user ID 11
    $customerNotifs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Customer (ID 11) sees " . count($customerNotifs) . " notifications\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
