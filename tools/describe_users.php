<?php
require_once __DIR__ . '/../includes/config.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();

    $stmt = $pdo->query('DESCRIBE users');
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Columns in users table:\n";
    foreach ($cols as $col) {
        echo "- " . $col['Field'] . " (Type: " . $col['Type'] . ")\n";
    }
} catch (Exception $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n";
}
