<?php
require_once '../includes/config.php';

$db = new Database();
$pdo = $db->getConnection();

try {
    // Drop existing notifications table if it exists
    $pdo->exec("DROP TABLE IF EXISTS notifications");

    // Create notifications table with correct structure
    $pdo->exec("
        CREATE TABLE notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            type VARCHAR(50) NOT NULL,
            message TEXT NOT NULL,
            related_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            read_at TIMESTAMP NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");

    // Add indexes for better performance
    $pdo->exec("CREATE INDEX idx_notifications_user ON notifications(user_id)");
    $pdo->exec("CREATE INDEX idx_notifications_read ON notifications(read_at)");

    echo "Notifications table created successfully!\n";
} catch (PDOException $e) {
    echo "Error creating notifications table: " . $e->getMessage() . "\n";
}
?>