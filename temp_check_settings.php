<?php
require_once 'includes/config.php';
try {
    $db = new Database();
    $pdo = $db->getConnection();
    $stmt = $pdo->query('SELECT setting_key, setting_value FROM settings');
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($rows)) {
        echo "No settings found\n";
    } else {
        foreach ($rows as $r) {
            echo $r['setting_key'] . ': ' . substr($r['setting_value'], 0, 100) . "...\n";
        }
    }
} catch (Exception $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n";
}
