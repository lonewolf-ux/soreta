<?php
require_once 'includes/config.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();
    $tables = [
        'users' => "ADD COLUMN is_active BOOLEAN DEFAULT TRUE",
        'troubleshooting_guide' => "ADD COLUMN is_active BOOLEAN DEFAULT TRUE",
        'technicians' => "ADD COLUMN is_active BOOLEAN DEFAULT TRUE"
    ];

    foreach ($tables as $table => $alter) {
        try {
            $stmt = $pdo->query("DESCRIBE {$table}");
            $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (!in_array('is_active', $cols)) {
                $sql = "ALTER TABLE {$table} {$alter}";
                $pdo->exec($sql);
                echo "✓ Added 'is_active' column to {$table} table<br>";
            } else {
                echo "✓ 'is_active' column already exists on {$table}<br>";
            }
        } catch (Exception $e) {
            echo "✗ Could not check/modify table {$table}: " . $e->getMessage() . "<br>";
        }
    }

    // Additional checks for troubleshooting_guide fields
    try {
        $stmt = $pdo->query("DESCRIBE troubleshooting_guide");
        $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // display_order
        if (!in_array('display_order', $cols)) {
            // If an old `display` column exists, migrate
            if (in_array('display', $cols)) {
                $pdo->exec("ALTER TABLE troubleshooting_guide ADD COLUMN display_order INT DEFAULT 0 AFTER preventive_tip");
                $pdo->exec("UPDATE troubleshooting_guide SET display_order = IFNULL(display,0)");
                echo "✓ Added 'display_order' and migrated from 'display'<br>";
            } else {
                $pdo->exec("ALTER TABLE troubleshooting_guide ADD COLUMN display_order INT DEFAULT 0 AFTER preventive_tip");
                echo "✓ Added 'display_order' column to troubleshooting_guide<br>";
            }
        } else {
            echo "✓ 'display_order' already exists on troubleshooting_guide<br>";
        }

        // fix_steps (new name) — migrate from old 'fix' if exists
        if (!in_array('fix_steps', $cols)) {
            if (in_array('fix', $cols)) {
                $pdo->exec("ALTER TABLE troubleshooting_guide ADD COLUMN fix_steps TEXT AFTER description");
                $pdo->exec("UPDATE troubleshooting_guide SET fix_steps = fix");
                echo "✓ Added 'fix_steps' and migrated from 'fix'<br>";
            } else {
                $pdo->exec("ALTER TABLE troubleshooting_guide ADD COLUMN fix_steps TEXT AFTER description");
                echo "✓ Added 'fix_steps' column to troubleshooting_guide<br>";
            }
        } else {
            echo "✓ 'fix_steps' already exists on troubleshooting_guide<br>";
        }

        // image_path — migrate from old 'image' if exists
        if (!in_array('image_path', $cols)) {
            if (in_array('image', $cols)) {
                $pdo->exec("ALTER TABLE troubleshooting_guide ADD COLUMN image_path VARCHAR(500) AFTER preventive_tip");
                $pdo->exec("UPDATE troubleshooting_guide SET image_path = image");
                echo "✓ Added 'image_path' and migrated from 'image'<br>";
            } else {
                $pdo->exec("ALTER TABLE troubleshooting_guide ADD COLUMN image_path VARCHAR(500) AFTER preventive_tip");
                echo "✓ Added 'image_path' column to troubleshooting_guide<br>";
            }
        } else {
            echo "✓ 'image_path' already exists on troubleshooting_guide<br>";
        }

    } catch (Exception $e) {
        echo "✗ Troubleshooting guide schema update failed: " . $e->getMessage() . "<br>";
    }

    // Check for appointments table admin_notes column
    try {
        $stmt = $pdo->query("DESCRIBE appointments");
        $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (!in_array('admin_notes', $cols)) {
            $pdo->exec("ALTER TABLE appointments ADD COLUMN admin_notes TEXT");
            echo "✓ Added 'admin_notes' column to appointments table<br>";
        } else {
            echo "✓ 'admin_notes' column already exists on appointments<br>";
        }
    } catch (Exception $e) {
        echo "✗ Could not check/modify appointments table: " . $e->getMessage() . "<br>";
    }

    // Check for notifications table related_type column
    try {
        $stmt = $pdo->query("DESCRIBE notifications");
        $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (!in_array('related_type', $cols)) {
            $pdo->exec("ALTER TABLE notifications ADD COLUMN related_type ENUM('appointment', 'system') DEFAULT 'system'");
            echo "✓ Added 'related_type' column to notifications table<br>";
        } else {
            echo "✓ 'related_type' column already exists on notifications<br>";
        }

        if (!in_array('related_id', $cols)) {
            $pdo->exec("ALTER TABLE notifications ADD COLUMN related_id INT DEFAULT NULL");
            echo "✓ Added 'related_id' column to notifications table<br>";
        } else {
            echo "✓ 'related_id' column already exists on notifications<br>";
        }
    } catch (Exception $e) {
        echo "✗ Could not check/modify notifications table: " . $e->getMessage() . "<br>";
    }

    echo "<strong>Database fix completed successfully!</strong>";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
