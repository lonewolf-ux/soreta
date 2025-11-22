<?php
require_once __DIR__ . '/../includes/config.php';
try {
    $db = new Database();
    $pdo = $db->getConnection();
    $stmt = $pdo->query('SELECT COALESCE(NULLIF(setting_key,\'\'), NULLIF(`key`,\'\')) AS setting_key, COALESCE(NULLIF(setting_value,\'\'), NULLIF(`value`,\'\')) AS setting_value FROM settings');
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($rows)) {
        echo "No settings found\n";
    } else {
        foreach ($rows as $r) {
            echo $r['setting_key'] . ": " . (strlen($r['setting_value'])>200 ? substr($r['setting_value'],0,200).'...' : $r['setting_value']) . "\n";
        }
    }
} catch (Exception $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n";
}
