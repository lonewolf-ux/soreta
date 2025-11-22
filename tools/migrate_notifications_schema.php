<?php
require_once __DIR__ . '/../includes/config.php';

echo "Checking notifications table schema...\n";

try {
    $db = new Database();
    $pdo = $db->getConnection();

    // Check if table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'notifications'");
    $exists = $stmt->rowCount() > 0;

    if (!$exists) {
        echo "Notifications table does not exist — creating...\n";
        $pdo->exec(
            "CREATE TABLE notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                type VARCHAR(100) DEFAULT NULL,
                title VARCHAR(255) DEFAULT NULL,
                message TEXT NOT NULL,
                related_type VARCHAR(50) DEFAULT NULL,
                related_id INT DEFAULT NULL,
                is_read TINYINT(1) DEFAULT 0,
                read_at TIMESTAMP NULL DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
        $pdo->exec("CREATE INDEX idx_notifications_user ON notifications(user_id)");
        $pdo->exec("CREATE INDEX idx_notifications_read ON notifications(is_read)");
        echo "Notifications table created.\n";
        exit(0);
    }

    // Inspect existing columns
    $colsStmt = $pdo->query("DESCRIBE notifications");
    $cols = $colsStmt->fetchAll(PDO::FETCH_COLUMN);
    $cols = array_map('strtolower', $cols);

    $alterQueries = [];

    if (!in_array('type', $cols)) {
        $alterQueries[] = "ADD COLUMN type VARCHAR(100) DEFAULT NULL";
    }
    if (!in_array('title', $cols)) {
        $alterQueries[] = "ADD COLUMN title VARCHAR(255) DEFAULT NULL";
    }
    if (!in_array('message', $cols)) {
        $alterQueries[] = "ADD COLUMN message TEXT";
    }
    if (!in_array('related_type', $cols)) {
        $alterQueries[] = "ADD COLUMN related_type VARCHAR(50) DEFAULT NULL";
    }
    if (!in_array('related_id', $cols)) {
        $alterQueries[] = "ADD COLUMN related_id INT DEFAULT NULL";
    }
    if (!in_array('is_read', $cols)) {
        $alterQueries[] = "ADD COLUMN is_read TINYINT(1) DEFAULT 0";
    }
    if (!in_array('read_at', $cols)) {
        $alterQueries[] = "ADD COLUMN read_at TIMESTAMP NULL DEFAULT NULL";
    }
    if (!in_array('created_at', $cols)) {
        $alterQueries[] = "ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
    }

    if (!empty($alterQueries)) {
        $sql = "ALTER TABLE notifications " . implode(', ', $alterQueries);
        echo "Applying: $sql\n";
        $pdo->exec($sql);
        echo "Schema updated successfully.\n";
    } else {
        echo "No schema changes required.\n";
    }

    // Ensure indexes
    $idxStmt = $pdo->query("SHOW INDEX FROM notifications WHERE Key_name = 'idx_notifications_user'");
    if ($idxStmt->rowCount() === 0) {
        $pdo->exec("CREATE INDEX idx_notifications_user ON notifications(user_id)");
        echo "Created index idx_notifications_user\n";
    }

    $idxStmt2 = $pdo->query("SHOW INDEX FROM notifications WHERE Key_name = 'idx_notifications_read'");
    if ($idxStmt2->rowCount() === 0) {
        // If is_read exists use it, otherwise try read_at
        if (in_array('is_read', $cols)) {
            $pdo->exec("CREATE INDEX idx_notifications_read ON notifications(is_read)");
        } else {
            $pdo->exec("CREATE INDEX idx_notifications_read ON notifications(read_at)");
        }
        echo "Created index idx_notifications_read\n";
    }

    echo "Migration complete.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

?>
