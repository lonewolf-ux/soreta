<?php
require_once 'includes/config.php';
try {
    $db = new Database();
    $pdo = $db->getConnection();
    $stmt = $pdo->query('SELECT * FROM settings');
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($rows)) {
        echo "No settings found\n";
    } else {
        foreach ($rows as $r) {
            echo "ID: " . $r['id'] . "\n";
            if (isset($r['key'])) echo "key: '" . $r['key'] . "'\n";
            if (isset($r['setting_key'])) echo "setting_key: '" . $r['setting_key'] . "'\n";
            if (isset($r['value'])) echo "value: '" . substr($r['value'], 0, 100) . "...'\n";
            if (isset($r['setting_value'])) echo "setting_value: '" . substr($r['setting_value'], 0, 100) . "...'\n";
            echo "---\n";
        }
    }
} catch (Exception $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n";
}
