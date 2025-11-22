<?php
require_once __DIR__ . '/../includes/config.php';
try {
    $db = new Database();
    $pdo = $db->getConnection();
    $stmt = $pdo->query('DESCRIBE settings');
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Columns in 'settings' table:\n";
    foreach ($rows as $r) {
        echo $r['Field'] . "\t" . $r['Type'] . "\t" . $r['Null'] . "\t" . $r['Key'] . "\t" . $r['Default'] . "\n";
    }
} catch (Exception $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n";
}
