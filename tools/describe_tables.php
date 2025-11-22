<?php
require_once __DIR__ . '/../includes/config.php';

$tables = ['users', 'technicians', 'troubleshooting_guide', 'notifications', 'login_attempts'];

try {
    $db = new Database();
    $pdo = $db->getConnection();

    foreach ($tables as $t) {
        echo "Table: $t\n";
        try {
            $stmt = $pdo->query('DESCRIBE ' . $t);
            $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($cols as $col) {
                echo " - " . $col['Field'] . " (" . $col['Type'] . ")\n";
            }
        } catch (Exception $e) {
            echo "Error describing $t: " . $e->getMessage() . "\n";
        }
        echo "\n";
    }
} catch (Exception $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n";
}
