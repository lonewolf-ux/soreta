<?php
/**
 * Notification Cleanup Script
 * Deletes notifications older than 30 days
 * Run manually or via cron job
 */

require_once '../includes/config.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();

    // Delete notifications older than 30 days
    $stmt = $pdo->prepare("
        DELETE FROM notifications
        WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stmt->execute();

    $deletedCount = $stmt->rowCount();

    echo "Cleanup completed. Deleted $deletedCount old notifications.\n";

} catch (Exception $e) {
    echo "Error during cleanup: " . $e->getMessage() . "\n";
    exit(1);
}
?>
