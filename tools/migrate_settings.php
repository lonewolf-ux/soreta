<?php
require_once __DIR__ . '/../includes/config.php';
try {
    $db = new Database();
    $pdo = $db->getConnection();

    // Inspect columns
    $colsStmt = $pdo->query("DESCRIBE settings");
    $cols = $colsStmt->fetchAll(PDO::FETCH_COLUMN, 0);

    $hasOld = in_array('key', $cols) && in_array('value', $cols);
    $hasNew = in_array('setting_key', $cols) && in_array('setting_value', $cols);

    if (!$hasOld && !$hasNew) {
        echo "No recognizable settings columns found.\n";
        exit(1);
    }

    if ($hasNew) {
        // Copy from old columns if present and new values are empty
        if ($hasOld) {
            $updateSql = "UPDATE settings SET setting_key = `key`, setting_value = `value` WHERE (setting_key IS NULL OR setting_key = '') AND (`key` IS NOT NULL AND `key` <> '')";
            $affected = $pdo->exec($updateSql);
            echo "Copied $affected rows from old key/value into setting_key/setting_value.\n";
        } else {
            echo "No old columns to migrate; new columns already present.\n";
        }
    } else {
        // Add new columns and copy
        $pdo->exec("ALTER TABLE settings ADD COLUMN setting_key VARCHAR(100) NOT NULL DEFAULT '' AFTER value");
        $pdo->exec("ALTER TABLE settings ADD COLUMN setting_value TEXT NOT NULL AFTER setting_key");
        $affected = 0;
        if ($hasOld) {
            $affected = $pdo->exec("UPDATE settings SET setting_key = `key`, setting_value = `value` WHERE (`key` IS NOT NULL AND `key` <> '')");
        }
        echo "Added new columns and copied $affected rows.\n";
    }

    // Show current settings
    $stmt = $pdo->query("SELECT COALESCE(NULLIF(setting_key,''), NULLIF(`key`,'')) AS sk, COALESCE(NULLIF(setting_value,''), NULLIF(`value`,'')) AS sv FROM settings");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "\nCurrent settings (first 200 chars of value):\n";
    foreach ($rows as $r) {
        $val = $r['sv'] ?? '';
        if (strlen($val) > 200) $val = substr($val,0,200) . '...';
        echo ($r['sk'] ?? '(empty)') . ": " . $val . "\n";
    }

} catch (Exception $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n";
}
