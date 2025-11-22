<?php
require_once '../includes/config.php';

$db = new Database();
$pdo = $db->getConnection();

try {
    // Add or modify each column individually to handle errors more gracefully
    $columns = [
        "ALTER TABLE appointments MODIFY COLUMN status ENUM('scheduled', 'rescheduled', 'in-progress', 'completed', 'cancelled') DEFAULT 'scheduled'",
        "ALTER TABLE appointments ADD COLUMN IF NOT EXISTS customer_id INT NULL",
        "ALTER TABLE appointments ADD COLUMN IF NOT EXISTS user_id INT NULL",
        "ALTER TABLE appointments ADD COLUMN IF NOT EXISTS reschedule_reason TEXT NULL",
        "ALTER TABLE appointments ADD COLUMN IF NOT EXISTS cancellation_reason TEXT NULL",
        "ALTER TABLE appointments ADD COLUMN IF NOT EXISTS cancelled_at TIMESTAMP NULL",
        "ALTER TABLE appointments ADD COLUMN IF NOT EXISTS last_modified TIMESTAMP NULL",
        "ALTER TABLE appointments ADD COLUMN IF NOT EXISTS previous_date DATE NULL",
        "ALTER TABLE appointments ADD COLUMN IF NOT EXISTS previous_time TIME NULL"
    ];

    foreach ($columns as $sql) {
        try {
            $pdo->exec($sql);
            echo "Executed: $sql\n";
        } catch (PDOException $e) {
            echo "Warning: " . $e->getMessage() . "\n";
        }
    }

    // Update existing appointments
    $pdo->exec("
        UPDATE appointments a 
        JOIN users u ON a.customer_id IS NULL 
        SET a.user_id = u.id, 
            a.customer_id = u.id 
        WHERE u.role = 'customer'
    ");

    // Drop existing foreign keys if they exist
    try {
        $pdo->exec("ALTER TABLE appointments DROP FOREIGN KEY IF EXISTS fk_appointment_user");
        $pdo->exec("ALTER TABLE appointments DROP FOREIGN KEY IF EXISTS fk_appointment_customer");
    } catch (PDOException $e) {
        // Ignore errors when dropping non-existent keys
    }

    // Add foreign key constraints
    $pdo->exec("
        ALTER TABLE appointments
        ADD CONSTRAINT fk_appointment_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    ");

    $pdo->exec("
        ALTER TABLE appointments
        ADD CONSTRAINT fk_appointment_customer
        FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE SET NULL
    ");

    echo "Database structure updated successfully!\n";
} catch (PDOException $e) {
    echo "Error updating database structure: " . $e->getMessage() . "\n";
}
?>