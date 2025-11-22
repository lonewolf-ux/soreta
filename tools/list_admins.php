<?php
require_once __DIR__ . '/../includes/config.php';
try {
    $db = new Database();
    $pdo = $db->getConnection();
    $stmt = $pdo->query("SELECT id, email, name FROM users WHERE role='admin'");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($rows)) {
        echo "No admin users found\n";
    } else {
        foreach ($rows as $r) {
            echo $r['id'] . " | " . $r['email'] . " | " . $r['name'] . "\n";
        }
    }
} catch (Exception $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n";
}
