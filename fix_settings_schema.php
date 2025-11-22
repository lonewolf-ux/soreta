<?php
require_once 'includes/config.php';
try {
    $db = new Database();
    $pdo = $db->getConnection();

    // First, ensure all data is copied to new columns
    $pdo->exec("UPDATE settings SET setting_key = `key` WHERE (setting_key IS NULL OR setting_key = '') AND (`key` IS NOT NULL AND `key` <> '')");
    $pdo->exec("UPDATE settings SET setting_value = `value` WHERE (setting_value IS NULL OR setting_value = '') AND (`value` IS NOT NULL AND `value` <> '')");

    // Add unique to setting_key if not exists
    $pdo->exec("ALTER TABLE settings ADD UNIQUE KEY uk_setting_key (setting_key)");

    // Drop old columns
    $pdo->exec("ALTER TABLE settings DROP COLUMN `key`");
    $pdo->exec("ALTER TABLE settings DROP COLUMN `value`");

    // Add setting_type if not exists
    $pdo->exec("ALTER TABLE settings ADD COLUMN setting_type ENUM('text', 'html', 'json') DEFAULT 'text'");

    echo "Settings table migrated to new schema.\n";

} catch (Exception $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n";
}
